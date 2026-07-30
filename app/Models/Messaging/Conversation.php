<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
        'message_channel_id', 'platform', 'external_conversation_id', 'customer_external_id',
        'customer_name', 'customer_avatar_url', 'last_message_at', 'last_message_preview',
        'unread_count', 'status', 'assigned_user_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(MessageChannel::class, 'message_channel_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
