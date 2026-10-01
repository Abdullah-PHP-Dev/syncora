<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

/** A base64 conversation_key_change_event (keys wrapped per participant - ciphertext). */
class XChatConversationKeyEvent extends Model
{
    protected $fillable = ['conversation_id', 'key_version', 'event_hash', 'encoded_event'];
}
