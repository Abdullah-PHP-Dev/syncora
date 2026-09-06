<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CopilotMessage;
use App\Models\Messaging\Conversation;
use App\Services\AiCopilotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The AI Copilot's entry point from the existing Unified Inbox
 * (admin.chats.dashboard) - see AiCopilotService's docblock for the
 * scope boundary: this finds and scores a match from the seller's own
 * Knowledge Base for an agent to review and send, it does not send
 * anything on its own.
 */
class CopilotController extends Controller
{
    public function __construct(private AiCopilotService $copilot)
    {
    }

    /**
     * Scores the conversation's latest inbound (customer) message against
     * the owning seller's own published Knowledge Base. Same ownership
     * check ChatController::dashboard() uses (a conversation belongs to
     * whichever seller owns its channel/SocialAccount) - a seller can
     * only ever run this against their own conversations and their own
     * Knowledge Base, never another seller's (AiCopilotService::
     * findBestMatch() is itself scoped by user_id too, so this is
     * defense in depth, not the only check).
     */
    public function findAnswer(Request $request, Conversation $conversation)
    {
        $conversation->loadMissing('channel');
        abort_unless($conversation->channel && $conversation->channel->user_id === Auth::id(), 403);

        $latestInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('id')
            ->first();

        abort_if(!$latestInbound, 422, 'This conversation has no customer message to answer yet.');

        $recentMessages = $conversation->messages()
            ->where('id', '<', $latestInbound->id)
            ->latest('id')
            ->take(4)
            ->pluck('body')
            ->filter()
            ->reverse()
            ->values()
            ->all();

        $result = $this->copilot->findBestMatch($latestInbound->body ?? '', Auth::id(), $recentMessages);

        $log = CopilotMessage::create([
            'conversation_id'      => $conversation->id,
            'message_id'           => $latestInbound->id,
            'user_id'              => Auth::id(),
            'faq_id'               => $result['faq']?->id,
            'confidence'           => $result['confidence'],
            'confidence_breakdown' => $result['breakdown'],
            'resolution_type'      => $result['status'],
            'suggested_reply'      => $result['suggested_reply'],
        ]);

        return response()->json([
            'success'             => true,
            'copilot_message_id'  => $log->id,
            'status'              => $result['status'],
            'confidence'          => $result['confidence'],
            'auto_reply_eligible' => $result['auto_reply_eligible'],
            'breakdown'           => $result['breakdown'],
            'suggested_reply'     => $result['suggested_reply'],
            'matched_faq'         => $result['faq'] ? [
                'id'       => $result['faq']->id,
                'question' => $result['faq']->question,
            ] : null,
        ]);
    }

    /**
     * "Was this suggestion actually useful" feedback - feeds
     * AiCopilotService::historicalAccuracy()'s per-FAQ helpful/unhelpful
     * ratio (BRD 5.2's "historical accuracy" component) and marks
     * whether the agent actually sent it, for the BRD's own
     * auto-resolution-rate KPI (section 12) once real volume exists.
     */
    public function feedback(Request $request, CopilotMessage $copilotMessage)
    {
        abort_unless($copilotMessage->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'helpful'  => ['required', 'boolean'],
            'was_sent' => ['nullable', 'boolean'],
        ]);

        if ($copilotMessage->faq_id) {
            $column = $validated['helpful'] ? 'helpful_count' : 'unhelpful_count';
            $copilotMessage->faq()->increment($column);
        }

        $copilotMessage->update(['was_sent' => $request->boolean('was_sent') || $copilotMessage->was_sent]);

        return response()->json(['success' => true]);
    }
}
