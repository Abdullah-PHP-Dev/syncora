<?php

use App\Models\Messaging\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A single open thread - only the agent who owns the connected channel
// (the Page/number/bot it belongs to) can listen for new messages on it.
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::with('channel')->find($conversationId);

    return $conversation && (int) $conversation->channel->user_id === (int) $user->id;
});

// The sidebar conversation list - every new message across every one of
// this agent's channels updates it, not just the thread currently open.
Broadcast::channel('inbox.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
