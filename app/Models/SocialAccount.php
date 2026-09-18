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
    /**
     * followers_count/subscribers_count/likes_count/views_count/
     * impressions_count/following_count/media_count are NOT real columns
     * on this table (see social_account_post_details) - they're kept in
     * $fillable and given accessor/mutator pairs below purely so every
     * existing SocialAccount::updateOrCreate([...]) / $account->update([...])
     * call across the connect flows and stat-sync services keeps working
     * unchanged, while the actual storage is properly split by capability.
     */
    protected array $pendingPostDetails = [];
    protected array $pendingAdDetails = [];

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
        'views_count',
        'impressions_count',
        'following_count',
        'media_count',
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
        // 'access_token' => 'encrypted',
        // 'refresh_token' => 'encrypted',
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

    protected static function booted(): void
    {
        // fill()/save() can't write straight to social_account_post_details
        // or social_account_ad_details (they're a separate table, and on a
        // brand-new SocialAccount there's no id to key them by yet until
        // the insert completes) - the accessor/mutator pairs below buffer
        // those values here instead, and this flushes the buffer into the
        // right child table right after the parent row has an id.
        static::saved(function (self $account) {
            if (!empty($account->pendingPostDetails)) {
                $account->postDetails()->updateOrCreate([], $account->pendingPostDetails);
                $account->pendingPostDetails = [];
            }

            if (!empty($account->pendingAdDetails)) {
                $account->adDetails()->updateOrCreate([], $account->pendingAdDetails);
                $account->pendingAdDetails = [];
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postDetails(): HasOne
    {
        return $this->hasOne(SocialAccountPostDetails::class);
    }

    public function adDetails(): HasOne
    {
        return $this->hasOne(SocialAccountAdDetails::class);
    }

    /**
     * Explicit write path for ads-side detail columns (currency, business
     * id, account status, spend) - there's no single existing key set in
     * use for these the way there is for the posting stats below, so
     * connect flows call this directly instead of relying on magic
     * mutators. Safe to call before or after the parent row is saved.
     */
    public function syncAdDetails(array $data): void
    {
        if ($this->exists) {
            $this->adDetails()->updateOrCreate([], $data);
        } else {
            $this->pendingAdDetails = array_merge($this->pendingAdDetails, $data);
        }
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

    public function getFollowersCountAttribute()
    {
        return $this->postDetails?->followers_count;
    }

    public function setFollowersCountAttribute($value): void
    {
        $this->pendingPostDetails['followers_count'] = $value;
    }

    public function getSubscribersCountAttribute()
    {
        return $this->postDetails?->subscribers_count;
    }

    public function setSubscribersCountAttribute($value): void
    {
        $this->pendingPostDetails['subscribers_count'] = $value;
    }

    public function getLikesCountAttribute()
    {
        return $this->postDetails?->likes_count;
    }

    public function setLikesCountAttribute($value): void
    {
        $this->pendingPostDetails['likes_count'] = $value;
    }

    public function getViewsCountAttribute()
    {
        return $this->postDetails?->views_count;
    }

    public function setViewsCountAttribute($value): void
    {
        $this->pendingPostDetails['views_count'] = $value;
    }

    public function getImpressionsCountAttribute()
    {
        return $this->postDetails?->impressions_count;
    }

    public function setImpressionsCountAttribute($value): void
    {
        $this->pendingPostDetails['impressions_count'] = $value;
    }

    public function getFollowingCountAttribute()
    {
        return $this->postDetails?->following_count;
    }

    public function setFollowingCountAttribute($value): void
    {
        $this->pendingPostDetails['following_count'] = $value;
    }

    public function getMediaCountAttribute()
    {
        return $this->postDetails?->media_count;
    }

    public function setMediaCountAttribute($value): void
    {
        $this->pendingPostDetails['media_count'] = $value;
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
