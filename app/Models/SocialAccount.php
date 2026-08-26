<?php

namespace App\Models;

use App\Models\Admin\AdCampaign;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\MessageChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'platform',
        'platform_account_id',
        'name',
        'username',
        'avatar_url',
        'followers_count',
        'subscribers_count',
        'likes_count',
        'category',
        'account_type',
        'metadata',
        'access_token',
        'refresh_token',
        'token_type',
        'scopes',
        'expires_at',
        'is_token_valid',
        'has_posting_permission',
        'has_messaging_permission',
        'has_ads_permission',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'is_token_valid' => 'boolean',
        'has_posting_permission' => 'boolean',
        'has_messaging_permission' => 'boolean',
        'has_ads_permission' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->posts()->where('status', 'scheduled');
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->where('status', 'published');
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function postComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messageChannel(): HasOne
    {
        return $this->hasOne(MessageChannel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_token_valid', true);
    }

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeWithPostingPermission($query)
    {
        return $query->where('has_posting_permission', true);
    }

    public function scopeWithMessagingPermission($query)
    {
        return $query->where('has_messaging_permission', true);
    }

    public function scopeWithAdsPermission($query)
    {
        return $query->where('has_ads_permission', true);
    }

    public function hasValidToken(): bool
    {
        return (bool) $this->access_token
            && $this->is_token_valid
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isTokenExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getPlatformDisplayNameAttribute(): string
    {
        $platforms = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'snapchat' => 'Snapchat',
            'youtube' => 'YouTube',
            'google' => 'Google',
            'pinterest' => 'Pinterest',
            'threads' => 'Threads',
            'whatsapp' => 'WhatsApp',
        ];

        return $platforms[$this->platform] ?? ucfirst($this->platform);
    }

    public static function getUserAccountsGroupedByPlatform(int $userId): array
    {
        return static::where('user_id', $userId)
            ->active()
            ->get()
            ->groupBy('platform')
            ->toArray();
    }
}
