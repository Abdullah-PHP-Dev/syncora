<?php

namespace App\Models\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageChannel extends Model
{
    protected $table = 'message_channels';

    protected $fillable = [
        'user_id', 'platform', 'name', 'external_id', 'username', 'avatar_url',
        'access_token', 'refresh_token', 'verify_token', 'meta', 'status',
        'last_synced_at', 'expires_at', 'webhook_subscribed',
    ];

    protected $casts = [
        'meta'                => 'array',
        'status'              => 'boolean',
        'last_synced_at'      => 'datetime',
        'expires_at'          => 'datetime',
        'webhook_subscribed'  => 'boolean',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'verify_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
