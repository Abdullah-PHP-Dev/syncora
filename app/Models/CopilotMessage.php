<?php

namespace App\Models;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'message_id', 'user_id', 'faq_id',
        'confidence', 'confidence_breakdown', 'resolution_type',
        'suggested_reply', 'was_sent',
    ];

    protected $casts = [
        'confidence_breakdown' => 'array',
        'was_sent'             => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
