<?php

namespace App\Events\Messaging;

use App\Models\Messaging\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a message is edited or deleted through the platforms that
 * support it (see MessagingManagerService's capability map) - separate
 * from MessageCreated since the UI reacts differently (update an
 * already-rendered bubble in place, not append a new one), broadcast on
 * the same channels for the same reason: any open tab on this thread, or
 * this agent's inbox in general, should reflect the change without a
 * manual refresh.
 */
class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message)
    {
    }

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
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;

        return [
            'message' => [
                'id'              => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'body'            => $this->message->body,
                'status'          => $this->message->status,
                'edited_at'       => optional($this->message->edited_at)->toIso8601String(),
                'deleted_at'      => optional($this->message->deleted_at)->toIso8601String(),
            ],
            // Only meaningful to the sidebar when the edited/deleted
            // message was the conversation's most recent one - the
            // controller only updates this when that's the case, so an
            // edit to an older message just carries the unchanged preview.
            'conversation' => [
                'id'                   => $conversation->id,
                'last_message_preview' => $conversation->last_message_preview,
            ],
        ];
    }
}
