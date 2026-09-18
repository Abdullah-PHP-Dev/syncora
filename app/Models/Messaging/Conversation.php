<?php

namespace App\Models\Messaging;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
        'social_account_id', 'platform', 'external_conversation_id', 'customer_external_id',
        'customer_name', 'customer_avatar_url', 'last_message_at', 'last_message_preview',
        'unread_count', 'status', 'assigned_user_id', 'meta',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'meta'             => 'array',
    ];

    /**
     * The connected account this conversation belongs to. Named `channel`
     * (rather than `socialAccount`) to keep every existing
     * `$conversation->channel->...` call site working unchanged - only the
     * underlying FK and target model changed, not the relation's shape.
     * Platform-specific operational fields (verify_token, meta,
     * webhook_subscribed) live one hop further via `channel->messageChannel`.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
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
