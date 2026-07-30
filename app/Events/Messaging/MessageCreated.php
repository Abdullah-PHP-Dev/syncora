<?php

namespace App\Events\Messaging;

use App\Models\Messaging\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for both inbound (customer) and outbound (agent) messages - the
 * inbox UI reacts the same way either way (append to the open thread,
 * update the sidebar preview), so one event covers both directions rather
 * than duplicating MessageReceived/MessageSent classes with identical
 * payload shapes.
 */
class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    /**
     * Broadcasts on the specific thread (for anyone with it open) and on
     * the owning agent's inbox channel (for the sidebar conversation list
     * to update even when that thread isn't open).
     */
    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        return [
            new PrivateChannel('conversation.' . $conversation->id),
            new PrivateChannel('inbox.' . $conversation->channel->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;

        return [
            'message' => [
                'id'           => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'direction'    => $this->message->direction,
                'sender_type'  => $this->message->sender_type,
                'type'         => $this->message->type,
                'body'         => $this->message->body,
                'status'       => $this->message->status,
                'attachments'  => $this->message->attachments->map(fn($a) => [
                    'type' => $a->type,
                    'url'  => $a->url,
                ]),
                'created_at'   => $this->message->created_at->toIso8601String(),
            ],
            'conversation' => [
                'id'                    => $conversation->id,
                'platform'              => $conversation->platform,
                'customer_name'         => $conversation->customer_name,
                'customer_avatar_url'   => $conversation->customer_avatar_url,
                'last_message_preview'  => $conversation->last_message_preview,
                'last_message_at'       => optional($conversation->last_message_at)->toIso8601String(),
                'unread_count'          => $conversation->unread_count,
            ],
        ];
    }
}
