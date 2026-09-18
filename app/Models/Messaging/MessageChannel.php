<?php

namespace App\Models\Messaging;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageChannel extends Model
{
    protected $table = 'message_channels';

    /**
     * Identity/token/profile fields (user_id, name, username, avatar_url,
     * access_token, refresh_token, status) now live on the parent
     * SocialAccount - reach them via ->socialAccount. This table keeps only
     * platform-specific operational state that doesn't belong on a generic
     * account row.
     */
    protected $fillable = [
        'social_account_id', 'platform', 'external_id', 'verify_token', 'meta',
        'last_synced_at', 'expires_at', 'webhook_subscribed',
    ];

    protected $casts = [
        'meta'                => 'array',
        'last_synced_at'      => 'datetime',
        'expires_at'          => 'datetime',
        'webhook_subscribed'  => 'boolean',
    ];

    protected $hidden = ['verify_token'];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'social_account_id', 'social_account_id');
    }
}
