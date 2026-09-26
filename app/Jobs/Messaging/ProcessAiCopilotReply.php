<?php

namespace App\Jobs\Messaging;

use App\Events\Messaging\MessageCreated;
use App\Models\AiCopilotSetting;
use App\Models\CopilotMessage;
use App\Models\KnowledgeGapReport;
use App\Models\Messaging\Message;
use App\Services\AiCopilotService;
use App\Services\MessagingServices\MessagingManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * The automatic half of the AI Copilot - dispatched from
 * ProcessInboundMessage right after a customer message is persisted.
 * Everything CopilotController::findAnswer() does for a human-triggered
 * "Find Answer" click, this does unattended, gated by the seller's own
 * AiCopilotSetting, and actually sends the reply through
 * MessagingManagerService when confidence clears their auto-reply
 * threshold - the one thing the manual flow deliberately never does (see
 * AiCopilotService's docblock on that original scope boundary).
 *
 * Queued (not run inline in ProcessInboundMessage) because it makes a
 * real Gemini call and, on a match, a real platform-send call - neither
 * should hold up the webhook-ack-speed guarantee ProcessInboundMessage
 * itself exists for.
 */
class ProcessAiCopilotReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId)
    {
    }

    public function handle(AiCopilotService $copilot, MessagingManagerService $messaging): void
    {
        $message = Message::with('conversation.channel')->find($this->messageId);

        if (!$message || !$message->conversation) {
            return;
        }

        $conversation = $message->conversation;

        // A human agent has taken this conversation over - never reply
        // underneath them. Cleared via the conversation header's "Resume
        // AI" action.
        if ($conversation->ai_paused_at) {
            return;
        }

        // The customer may have sent a second message before this job
        // ran (fast double-send, or queue backlog) - answering the stale
        // one instead of their latest would read as the bot ignoring what
        // they just said. Bail and let the newer message's own queued job
        // handle it.
        $latestInboundId = $conversation->messages()->where('direction', 'inbound')->latest('id')->value('id');

        if ($latestInboundId !== $message->id) {
            return;
        }

        $sellerUserId = $conversation->channel->user_id;
        $settings = AiCopilotSetting::forSeller($sellerUserId);

        if (!$settings->ai_enabled) {
            return;
        }

        $recentMessages = $copilot->recentMessagesFor($conversation, $message->id);

        $result = $copilot->findBestMatch(
            $message->body ?? '',
            $sellerUserId,
            $recentMessages,
            $settings->confidence_threshold_auto,
            $settings->confidence_threshold_suggested,
        );

        $wasSent = false;

        if ($result['auto_reply_eligible'] && $settings->auto_reply_enabled) {
            $wasSent = $this->sendReply($conversation, $messaging, $result['suggested_reply']);
        }

        CopilotMessage::create([
            'conversation_id'      => $conversation->id,
            'message_id'           => $message->id,
            'user_id'              => $sellerUserId,
            'faq_id'               => $result['faq']?->id,
            'confidence'           => $result['confidence'],
            'confidence_breakdown' => $result['breakdown'],
            // auto_replied is a distinct outcome from the manual flow's
            // 'suggested'/'no_match' - it's what the analytics/knowledge-
            // gap phases will key off of to tell "the bot answered" apart
            // from "a human reviewed a suggestion".
            'resolution_type'      => $wasSent ? 'auto_replied' : $result['status'],
            'suggested_reply'      => $result['suggested_reply'],
            'was_sent'             => $wasSent,
        ]);

        if ($result['status'] === 'no_match') {
            $this->recordKnowledgeGap($sellerUserId, $message->body ?? '');
        }
    }

    /**
     * A genuine "the AI Copilot found nothing" result, deduplicated per
     * seller by normalized question text (KnowledgeGapReport::normalize())
     * - a 'suggested' result already has a scored candidate FAQ attached
     * and is visible via the CopilotMessage audit trail above, so it isn't
     * tracked as a gap.
     */
    private function recordKnowledgeGap(int $sellerUserId, string $question): void
    {
        $question = trim($question);

        if ($question === '') {
            return;
        }

        $hash = KnowledgeGapReport::normalize($question);

        if ($hash === '') {
            return;
        }

        $gap = KnowledgeGapReport::where('user_id', $sellerUserId)->where('question_hash', $hash)->first();

        if ($gap) {
            $gap->update([
                'question'         => $question,
                'occurrence_count' => $gap->occurrence_count + 1,
                'last_occurred_at' => now(),
                // A gap already being worked on (or already turned into a
                // FAQ) shouldn't silently flip back to 'new' just because
                // the same unanswered question recurred once more - only
                // an ignored/resolved gap gets reopened by a fresh
                // occurrence.
                'status'           => in_array($gap->status, ['ignored', 'resolved'], true) ? 'new' : $gap->status,
            ]);

            return;
        }

        KnowledgeGapReport::create([
            'user_id'          => $sellerUserId,
            'question'         => $question,
            'question_hash'    => $hash,
            'occurrence_count' => 1,
            'last_occurred_at' => now(),
            'status'           => 'new',
        ]);
    }

    /**
     * Mirrors ChatController::store()'s human-reply flow exactly (create
     * the outbound row queued -> send -> reconcile -> broadcast -> update
     * the conversation preview), so a bot reply is indistinguishable from
     * a human one everywhere except sender_type. Grounding constraint
     * from AiCopilotService's docblock applies here too: $body is the
     * matched FAQ's answer verbatim, never rephrased.
     */
    private function sendReply($conversation, MessagingManagerService $messaging, ?string $body): bool
    {
        if (!$body) {
            return false;
        }

        $outbound = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'sender_type'     => 'bot',
            'user_id'         => $conversation->channel->user_id,
            'type'            => 'text',
            'body'            => $body,
            'status'          => 'queued',
        ]);

        $result = $messaging->send($conversation, ['body' => $body]);

        if ($result['success'] ?? false) {
            $outbound->update([
                'status'  => 'sent',
                'sent_at' => now(),
                'external_message_id' => $result['external_message_id'] ?? null,
            ]);
        } else {
            $outbound->update(['status' => 'failed', 'error_message' => $result['error'] ?? 'Send failed.']);
        }

        $conversation->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($body, 120),
        ]);

        broadcast(new MessageCreated($outbound->load('attachments', 'conversation.channel')));

        return $result['success'] ?? false;
    }
}
