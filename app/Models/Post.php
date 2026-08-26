<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
   // use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'posts';
    protected $fillable = [
        // Basic Information
        'platform',
        'post_id',
        'user_id',
        'group_id',
        'social_account_id',
        'post_category_id',
        'title',
        'post_url',
        'content',
        'content_html',
        
        // Engagement Metrics
        'likes',
        'shares',
        'comments',
        'saves',
        'views',
        'clicks',
        'reach',
        'impressions',
        'engagement_rate',
        
        // Visibility & Scheduling
        'visibility',
        'schedule_mode',
        'schedule_at',
        'expiry_mode',
        'expiry_at',
        'published_at',
        
        // Status & Tracking
        'status',
        'error_message',
        'retry_count',
        'last_attempt_at',
        
        // Content Type & Settings
        'post_type',
        'media_url',
        'thumbnail_url',
        'duration_seconds',
        'hashtags',
        'mentions',
        'location',
        'link_preview',
        
        // Platform Specific
        'platform_metadata',
        'settings',
        'metadata',
        'tags',
        
        // Approval & Moderation
        'is_approved',
        'approved_by',
        'approved_at',
        'is_featured',
        'is_pinned',
        
        // SEO & Discovery
        'seo_title',
        'seo_description',
        'seo_keywords',
    //    'slug',
        
        // Analytics
        'analytics_data',
        'performance_score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Date/Time
        'schedule_at' => 'datetime',
        'expiry_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        
        // Booleans
        'schedule_mode' => 'boolean',
        'expiry_mode' => 'boolean',
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        
        // Integers
        'likes' => 'integer',
        'shares' => 'integer',
        'comments' => 'integer',
        'saves' => 'integer',
        'views' => 'integer',
        'clicks' => 'integer',
        'reach' => 'integer',
        'impressions' => 'integer',
        'retry_count' => 'integer',
        'duration_seconds' => 'integer',
        
        // Floats
        'engagement_rate' => 'float',
        'performance_score' => 'float',
        
        // Arrays/JSON
        'hashtags' => 'array',
        'mentions' => 'array',
        'location' => 'array',
        'link_preview' => 'array',
        'platform_metadata' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
        'tags' => 'array',
        'analytics_data' => 'array',
        'seo_keywords' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // No sensitive data to hide by default
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($post) {
            // Generate slug if not provided
            // if (empty($post->slug) && $post->title) {
            //     $post->slug = \Illuminate\Support\Str::slug($post->title);
            // }
            
            // Set default status if not provided
            if (empty($post->status)) {
                $post->status = 'draft';
            }
            
            // Set default visibility if not provided
            if (empty($post->visibility)) {
                $post->visibility = 'public';
            }
            
            // Set default post type if not provided
            // if (empty($post->post_type)) {
            //     $post->post_type = 'text';
            // }
        });

        static::updating(function ($post) {
            // Update slug if title changes and slug is empty or auto-generated
            // if ($post->isDirty('title') && (empty($post->slug) || $post->getOriginal('slug') === \Illuminate\Support\Str::slug($post->getOriginal('title')))) {
            //     $post->slug = \Illuminate\Support\Str::slug($post->title);
            // }
            
            // Update published_at when status changes to published
            if ($post->isDirty('status') && $post->status === 'published' && empty($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user that owns the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the social account this post belongs to.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * WhatsApp-only: per-recipient delivery outcomes for a broadcast post
     * (see PostWhatsappRecipient - WhatsApp has no public feed, so a
     * "post" there is a template message sent individually to a list of
     * numbers, each with its own status).
     */
    public function whatsappRecipients(): HasMany
    {
        return $this->hasMany(PostWhatsappRecipient::class);
    }

    /**
     * Get the category this post belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * Get the categories for this post (many-to-many via pivot).
     * This is useful if a post can belong to multiple categories.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, 'post_category_posts');
    }

    /**
     * Get the media attachments for this post.
     */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    /**
     * Get the primary media for this post.
     */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(PostMedia::class)->where('is_primary', true);
    }


    /**
     * Get the comments for this post.
     */
    public function postComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    

    /**
     * Get the parent post (for thread/reply).
     */
    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'parent_post_id');
    }

    /**
     * Get the child posts (replies/threads).
     */
    public function childPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_post_id');
    }


    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include draft posts.
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include scheduled posts.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('schedule_at')
            ->where('schedule_at', '>', now());
    }

    /**
     * Scope a query to only include posts ready to publish.
     */
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'scheduled')
            ->whereNotNull('schedule_at')
            ->where('schedule_at', '<=', now())
            ->where('is_approved', true);
    }

    /**
     * Scope a query to only include failed posts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include posts by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include posts by category.
     */
    public function scopeCategory($query, $categoryId)
    {
        return $query->where('post_category_id', $categoryId);
    }

    /**
     * Scope a query to only include featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include approved posts.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include posts that are not expired.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_at')
                ->orWhere('expiry_at', '>', now());
        });
    }

    /**
     * Scope a query to search posts.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'LIKE', "%{$search}%")
            ->orWhere('content', 'LIKE', "%{$search}%")
            ->orWhere('hashtags', 'LIKE', "%{$search}%");
    }

    /**
     * Scope a query to order by performance.
     */
    public function scopeBestPerforming($query)
    {
        return $query->orderBy('engagement_rate', 'desc')
            ->orderBy('views', 'desc');
    }

    /**
     * Scope a query to get posts by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // =============================================
    // ACCESSORS & MUTATORS
    // =============================================

    /**
     * Get the post's status badge color.
     */
    public function getStatusBadgeAttribute(): string
    {
        $colors = [
            'draft' => 'gray',
            'scheduled' => 'blue',
            'pending' => 'yellow',
            'published' => 'green',
            'failed' => 'red',
            'archived' => 'gray',
            'deleted' => 'dark',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Get the post's visibility badge color.
     */
    public function getVisibilityBadgeAttribute(): string
    {
        $colors = [
            'public' => 'green',
            'private' => 'red',
            'unlisted' => 'gray',
            'followers' => 'blue',
            'custom' => 'purple',
        ];

        return $colors[$this->visibility] ?? 'gray';
    }

    /**
     * Get the post's formatted content.
     */
    public function getFormattedContentAttribute(): string
    {
        return $this->content_html ?? nl2br(e($this->content));
    }

    /**
     * Get the post's short description.
     */
    public function getExcerptAttribute(): string
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->content), 150);
    }

    /**
     * Get the post's total engagement.
     */
    public function getTotalEngagementAttribute(): int
    {
        return ($this->likes ?? 0) + 
               ($this->comments ?? 0) + 
               ($this->shares ?? 0) + 
               ($this->saves ?? 0);
    }

    /**
     * Check if post is scheduled.
     */
    public function getIsScheduledAttribute(): bool
    {
        return $this->status === 'scheduled' && $this->schedule_at && $this->schedule_at->isFuture();
    }

    /**
     * Check if post is published.
     */
    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published' && $this->published_at;
    }

    /**
     * Check if post is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_at && $this->expiry_at->isPast();
    }

    /**
     * Check if post can be edited.
     */
    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'failed']);
    }

    /**
     * Get the post's URL for the platform.
     */
    public function getPlatformUrlAttribute(): ?string
    {
        if ($this->post_url) {
            return $this->post_url;
        }
    
        $platform = strtolower($this->platform ?? '');
    
        // Handle Google / Google Business Profile (GBP) posts
        if (in_array($platform, ['google', 'gmb', 'google_my_business', 'google_business_profile'])) {
            // If your model stores the location ID separately or inside post_account metadata:
            $locationId = $this->socialAccount->platform_account_id ?? null;
    
            if ($locationId) {
                // Business Profile Manager direct post URL
                return "https://business.google.com/posts/l/{$locationId}/p/{$this->post_id}";
            }
    
            // Fallback search/maps view URL for Google posts
            return "https://www.google.com/search?q={$this->post_id}";
        }
    
        // Generate platform-specific URL map
        $urls = [
            'facebook'  => "https://facebook.com/{$this->post_id}",
            'instagram' => "https://instagram.com/p/{$this->post_id}",
            'twitter'   => "https://x.com/i/status/{$this->post_id}",
            'x'         => "https://x.com/i/status/{$this->post_id}",
            'linkedin'  => "https://linkedin.com/feed/update/urn:li:share:{$this->post_id}",
            'tiktok'    => "https://tiktok.com/video/{$this->post_id}",
            'youtube'   => "https://youtube.com/watch?v={$this->post_id}",
            'pinterest' => "https://pinterest.com/pin/{$this->post_id}",
            'threads'   => "https://threads.net/post/{$this->post_id}",
        ];
    
        return $urls[$platform] ?? null;
    }

    /**
     * Get the post's schedule status.
     */
    public function getScheduleStatusAttribute(): string
    {
        if (!$this->schedule_at) {
            return 'not_scheduled';
        }

        if ($this->schedule_at->isPast()) {
            return 'past_due';
        }

        return 'pending';
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Publish the post.
     */
    public function publish(): bool
    {
        if ($this->status === 'published') {
            return false;
        }

        $this->status = 'published';
        $this->published_at = now();
        
        return $this->save();
    }

    /**
     * Schedule the post.
     */
    public function schedule($datetime): bool
    {
        $this->status = 'scheduled';
        $this->schedule_at = $datetime;
        $this->schedule_mode = true;
        
        return $this->save();
    }

    /**
     * Mark post as failed.
     */
    public function markAsFailed(string $errorMessage = null): bool
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->retry_count = ($this->retry_count ?? 0) + 1;
        $this->last_attempt_at = now();
        
        return $this->save();
    }

    /**
     * Archive the post.
     */
    public function archive(): bool
    {
        $this->status = 'archived';
        return $this->save();
    }

    /**
     * Duplicate the post.
     */
    public function duplicate(array $overrides = []): self
    {
        $newPost = $this->replicate();
        $newPost->title = $this->title . ' (Copy)';
        $newPost->slug = $this->slug . '-copy-' . time();
        $newPost->status = 'draft';
        $newPost->published_at = null;
        $newPost->schedule_at = null;
        $newPost->post_id = null;
        $newPost->post_url = null;
        $newPost->likes = 0;
        $newPost->shares = 0;
        $newPost->comments = 0;
        $newPost->views = 0;
        $newPost->fill($overrides);
        $newPost->save();

        // Duplicate media
        foreach ($this->media as $media) {
            $media->duplicate(['post_id' => $newPost->id]);
        }

        // Duplicate categories
        foreach ($this->categories as $category) {
            $newPost->categories()->attach($category->id);
        }

        // Duplicate tags
        foreach ($this->tags as $tag) {
            $newPost->tags()->attach($tag->id);
        }

        return $newPost;
    }

    /**
     * Get engagement metrics summary.
     */
    public function getEngagementMetrics(): array
    {
        return [
            'likes' => $this->likes ?? 0,
            'comments' => $this->comments ?? 0,
            'shares' => $this->shares ?? 0,
            'saves' => $this->saves ?? 0,
            'views' => $this->views ?? 0,
            'clicks' => $this->clicks ?? 0,
            'reach' => $this->reach ?? 0,
            'impressions' => $this->impressions ?? 0,
            'total_engagement' => $this->total_engagement,
            'engagement_rate' => $this->engagement_rate ?? 0,
        ];
    }

    /**
     * Check if post has media.
     */
    public function hasMedia(): bool
    {
        return $this->media()->count() > 0;
    }

    /**
     * Get media by type.
     */
    public function getMediaByType(string $type)
    {
        return $this->media()->where('media_type', $type)->get();
    }
    /**
     * Get posts stats for dashboard.
     */
    public static function getStats($userId = null): array
    {
        $query = self::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return [
            'total' => $query->count(),
            'published' => (clone $query)->where('status', 'published')->count(),
            'scheduled' => (clone $query)->where('status', 'scheduled')->count(),
            'drafts' => (clone $query)->where('status', 'draft')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'total_engagement' => (clone $query)->sum('likes') + 
                                  (clone $query)->sum('comments') + 
                                  (clone $query)->sum('shares'),
        ];
    }
}