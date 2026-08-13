<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PostAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'platform',
        'name',
        'user_id',
        'account_id',
        'parent_account_id',
        'username',
        'account_url',
        'image',
        'description',
        'is_default',
        'is_active',
        'webhook_subscriptions',
        'client_id',
        'client_secret',
        'token_secret',
        'status',
        'access_token',
        'refresh_token',
        'token_expires_at',
        // The real column is `expires_in` (see the create_post_accounts_table
        // migration) - `token_expires_at` above doesn't exist in the
        // database at all, so every *PostService's ensureValidToken()
        // token-refresh `update(['expires_in' => ...])` call was silently
        // dropped by mass-assignment protection until this was added.
        'expires_in',
        'platform_user_id',
        'profile_picture_url',
        'permissions',
        'platform_username',
        'last_sync_at',
        'settings',
        'metadata',
        'follower_count',
        'likes_count',
        'following_count',
        'media_count',
        'views_count',
        'insights',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'webhook_subscriptions' => 'boolean',
        'permissions' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
        'insights' => 'array',
        'token_expires_at' => 'datetime',
        'expires_in' => 'datetime',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
        'client_secret',
        'token_secret',
    ];

    /**
     * Get the user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent account (for connected accounts like Facebook pages).
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(PostAccount::class, 'parent_account_id');
    }

    /**
     * Get the child accounts (like pages under a Facebook account).
     */
    public function childAccounts(): HasMany
    {
        return $this->hasMany(PostAccount::class, 'parent_account_id');
    }

    /**
     * Get all posts associated with this account.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'social_account_id');
    }

    /**
     * Get the posts scheduled for this account.
     */
    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'social_account_id')
            ->where('status', 'scheduled');
    }

    /**
     * Get the published posts for this account.
     */
    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'social_account_id')
            ->where('status', 'published');
    }


    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default accounts.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include accounts by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include accounts with valid tokens.
     */
    public function scopeWithValidToken($query)
    {
        return $query->where('token_expires_at', '>', now())
            ->orWhereNull('token_expires_at');
    }

    /**
     * Check if the account has a valid token.
     */
    public function hasValidToken(): bool
    {
        return $this->access_token && 
               ($this->token_expires_at === null || 
                $this->token_expires_at->isFuture());
    }

    /**
     * Check if the account token is expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && 
               $this->token_expires_at->isPast();
    }

    /**
     * Get the platform display name.
     */
    public function getPlatformDisplayNameAttribute(): string
    {
        $platforms = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'twitter' => 'Twitter / X',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'snapchat' => 'Snapchat',
            'youtube' => 'YouTube',
            'google' => 'Google',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
        ];

        return $platforms[$this->platform] ?? ucfirst($this->platform);
    }

    /**
     * Get the account's URL for the platform.
     */
    public function getProfileUrlAttribute(): ?string
    {
        if ($this->account_url) {
            return $this->account_url;
        }

        $urls = [
            'facebook' => "https://facebook.com/{$this->username}",
            'instagram' => "https://instagram.com/{$this->username}",
            'twitter' => "https://twitter.com/{$this->username}",
            'linkedin' => "https://linkedin.com/in/{$this->username}",
            'tiktok' => "https://tiktok.com/@{$this->username}",
            'youtube' => "https://youtube.com/@{$this->username}",
        ];

        return $urls[$this->platform] ?? null;
    }

    /**
     * Check if the account supports webhooks.
     */
    public function supportsWebhooks(): bool
    {
        $supportedPlatforms = ['facebook', 'instagram', 'youtube', 'twitter'];
        return in_array($this->platform, $supportedPlatforms);
    }

    /**
     * Get all accounts for a user grouped by platform.
     */
    public static function getUserAccountsGroupedByPlatform($userId): array
    {
        $accounts = self::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        return $accounts->groupBy('platform')->toArray();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-set default account if it's the first one
        static::creating(function ($account) {
            if ($account->is_default === null) {
                $existingAccounts = self::where('user_id', $account->user_id)->count();
                $account->is_default = $existingAccounts === 0;
            }

            if ($account->is_active === null) {
                $account->is_active = true;
            }

            if ($account->status === null) {
                $account->status = 'active';
            }
        });

        // Prevent deleting the only default account without setting a new one
        static::deleting(function ($account) {
            if ($account->is_default) {
                $otherAccount = self::where('user_id', $account->user_id)
                    ->where('id', '!=', $account->id)
                    ->first();

                if ($otherAccount) {
                    $otherAccount->update(['is_default' => true]);
                }
            }
        });
    }
}