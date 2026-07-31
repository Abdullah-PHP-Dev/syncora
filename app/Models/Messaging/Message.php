<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id', 'external_message_id', 'direction', 'sender_type', 'user_id',
        'type', 'body', 'status', 'error_message', 'sent_at', 'delivered_at', 'read_at',
        'edited_at', 'deleted_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'edited_at'    => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }
}
