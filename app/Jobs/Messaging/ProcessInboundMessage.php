<?php

namespace App\Jobs\Messaging;

use App\Events\Messaging\MessageCreated;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageAttachment;
use App\Models\Messaging\MessageChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Stores one inbound customer message and broadcasts it - queued so the
 * webhook controller that dispatches this can ack the platform's webhook
 * request immediately (Meta/Telegram both expect a fast 200 response and
 * will retry aggressively, or eventually disable the subscription, if the
 * endpoint is slow). Each platform's webhook handler is responsible for
 * translating its own payload shape into the flat array this job expects
 * - this job only knows the generic shape, not any platform's wire format.
 */
class ProcessInboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $messageChannelId,
        public string $customerExternalId,
        public ?string $customerName = null,
        public ?string $customerAvatarUrl = null,
        public ?string $externalConversationId = null,
        public ?string $externalMessageId = null,
        public string $type = 'text',
        public ?string $body = null,
        public array $attachments = [],
        public ?array $conversationMeta = null,
    ) {
    }

    public function handle(): void
    {
        $channel = MessageChannel::find($this->messageChannelId);

        if (!$channel) {
            return;
        }

        // Duplicate delivery is a normal, expected occurrence for webhook
        // based platforms (Meta retries on anything slower than its
        // timeout, even after the first attempt actually succeeded) - skip
        // silently rather than creating a second copy of the same message.
        if ($this->externalMessageId && Message::where('external_message_id', $this->externalMessageId)->exists()) {
            return;
        }

        $message = DB::transaction(function () {
            $conversation = Conversation::firstOrCreate(
                [
                    'message_channel_id'   => $this->messageChannelId,
                    'customer_external_id' => $this->customerExternalId,
                ],
                [
                    'platform'                 => MessageChannel::find($this->messageChannelId)->platform,
                    'external_conversation_id' => $this->externalConversationId,
                    'customer_name'            => $this->customerName,
                    'customer_avatar_url'      => $this->customerAvatarUrl,
                    'meta'                     => $this->conversationMeta,
                    'status'                   => 'open',
                ]
            );

            // Keep denormalized customer display fields fresh - a platform
            // may only send the customer's name/avatar on some events, not
            // every message. Teams' serviceUrl (conversationMeta) is the
            // same story: it must track the *most recent* inbound activity,
            // not just the first one, since Microsoft's own docs warn it
            // can change between requests.
            $conversation->fill(array_filter([
                'customer_name'       => $this->customerName,
                'customer_avatar_url' => $this->customerAvatarUrl,
                'external_conversation_id' => $this->externalConversationId,
                'meta'                 => $this->conversationMeta,
            ]))->save();

            $preview = $this->body ?: (!empty($this->attachments) ? '[' . ucfirst($this->attachments[0]['type']) . ']' : '');

            $conversation->update([
                'last_message_at'      => now(),
                'last_message_preview' => \Illuminate\Support\Str::limit($preview, 120),
                'unread_count'         => $conversation->unread_count + 1,
                'status'               => 'open',
            ]);

            $message = Message::create([
                'conversation_id'     => $conversation->id,
                'external_message_id' => $this->externalMessageId,
                'direction'           => 'inbound',
                'sender_type'         => 'customer',
                'type'                => $this->type,
                'body'                => $this->body,
                'status'              => 'delivered',
                'sent_at'             => now(),
                'delivered_at'        => now(),
            ]);

            foreach ($this->attachments as $attachment) {
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'type'       => $attachment['type'] ?? 'file',
                    'url'        => $attachment['url'],
                    'mime_type'  => $attachment['mime_type'] ?? null,
                    'file_name'  => $attachment['file_name'] ?? null,
                    'file_size'  => $attachment['file_size'] ?? null,
                ]);
            }

            return $message;
        });

        broadcast(new MessageCreated($message->load('attachments', 'conversation.channel')));
    }
}
