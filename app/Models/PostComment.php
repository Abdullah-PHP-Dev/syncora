<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
  //  use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Identification
        'platform',
        'comment_id',
        'parent_comment_id',
        'platform_parent_id',
        'thread_id',
        
        // Relationships
        'user_id',
        'post_account_id',
        'post_id',
        
        // User Information
        'user_name',
        'user_avatar_url',
        'user_platform_id',
        'user_email',
        'user_url',
        
        // Content
        'content',
        'content_html',
        'raw_content',
        
        // Engagement
        'likes',
        'replies',
        'shares',
        'reactions',
        'sentiment_score',
        'sentiment_label',
        
        // Status & Flags
        'is_flagged',
        'is_spam',
        'is_approved',
        'is_pinned',
        'is_hidden',
        'is_reply',
        'status',
        'error_message',
        
        // Timestamps
        'posted_at',
        'imported_at',
        'last_sync_at',
        
        // Additional Data
        'metadata',
        'platform_metadata',
        'location',
        'language',
        
        // Moderation
        'moderated_by',
        'moderated_at',
        'moderation_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Booleans
        'is_flagged' => 'boolean',
        'is_spam' => 'boolean',
        'is_approved' => 'boolean',
        'is_pinned' => 'boolean',
        'is_hidden' => 'boolean',
        'is_reply' => 'boolean',
        
        // Integers
        'likes' => 'integer',
        'replies' => 'integer',
        'shares' => 'integer',
        
        // Floats
        'sentiment_score' => 'float',
        
        // Arrays/JSON
        'reactions' => 'array',
        'metadata' => 'array',
        'platform_metadata' => 'array',
        'location' => 'array',
        
        // DateTime
        'posted_at' => 'datetime',
        'imported_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'moderated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
        static::creating(function ($comment) {
            // Set default status if not provided
            if (empty($comment->status)) {
                $comment->status = 'pending';
            }
            
            // Set posted_at if not provided
            if (empty($comment->posted_at)) {
                $comment->posted_at = now();
            }
            
            // Set imported_at if not provided
            if (empty($comment->imported_at)) {
                $comment->imported_at = now();
            }
        });

        static::updating(function ($comment) {
            // Update sentiment label based on score if changed
            if ($comment->isDirty('sentiment_score')) {
                $score = $comment->sentiment_score;
                if ($score > 0.5) {
                    $comment->sentiment_label = 'positive';
                } elseif ($score < -0.5) {
                    $comment->sentiment_label = 'negative';
                } else {
                    $comment->sentiment_label = 'neutral';
                }
            }
        });
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user that owns the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the post account this comment belongs to.
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(PostAccount::class, 'post_account_id');
    }

    /**
     * Get the post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the parent comment (for nested replies).
     */
    public function parentComment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_comment_id');
    }

    /**
     * Get the child comments (replies).
     */
    public function childComments(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_comment_id');
    }

    /**
     * Get all replies (including nested).
     */
    public function replies(): HasMany
    {
        return $this->childComments()->with('replies');
    }

    /**
     * Get the thread this comment belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'thread_id');
    }

    /**
     * Get the moderator who moderated this comment.
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include approved comments.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include pending comments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('is_approved', false);
    }

    /**
     * Scope a query to only include flagged comments.
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope a query to only include spam comments.
     */
    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    /**
     * Scope a query to only include top-level comments (not replies).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_comment_id')
            ->where('is_reply', false);
    }

    /**
     * Scope a query to only include replies.
     */
    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_comment_id')
            ->orWhere('is_reply', true);
    }

    /**
     * Scope a query to only include comments by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include comments with positive sentiment.
     */
    public function scopePositiveSentiment($query)
    {
        return $query->where('sentiment_score', '>', 0.5);
    }

    /**
     * Scope a query to only include comments with negative sentiment.
     */
    public function scopeNegativeSentiment($query)
    {
        return $query->where('sentiment_score', '<', -0.5);
    }

    /**
     * Scope a query to only include comments with neutral sentiment.
     */
    public function scopeNeutralSentiment($query)
    {
        return $query->whereBetween('sentiment_score', [-0.5, 0.5]);
    }

    /**
     * Scope a query to search comments.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('content', 'LIKE', "%{$search}%")
            ->orWhere('user_name', 'LIKE', "%{$search}%");
    }

    /**
     * Scope a query to get comments by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('posted_at', [$from, $to]);
    }

    /**
     * Scope a query to order by engagement.
     */
    public function scopeMostEngaged($query)
    {
        return $query->orderBy('likes', 'desc')
            ->orderBy('replies', 'desc');
    }

    // =============================================
    // ACCESSORS & MUTATORS
    // =============================================

    /**
     * Get the comment's status badge color.
     */
    public function getStatusBadgeAttribute(): string
    {
        $colors = [
            'pending' => 'yellow',
            'approved' => 'green',
            'spam' => 'red',
            'flagged' => 'orange',
            'hidden' => 'gray',
            'deleted' => 'dark',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Get the comment's formatted content.
     */
    public function getFormattedContentAttribute(): string
    {
        return $this->content_html ?? nl2br(e($this->content));
    }

    /**
     * Get the comment's short excerpt.
     */
    public function getExcerptAttribute(): string
    {
        return \Illuminate\Support\Str::limit(strip_tags($this->content), 100);
    }

    /**
     * Get the sentiment label.
     */
    public function getSentimentLabelAttribute(): string
    {
        if ($this->sentiment_score > 0.5) {
            return 'positive';
        } elseif ($this->sentiment_score < -0.5) {
            return 'negative';
        }
        return 'neutral';
    }

    /**
     * Get the sentiment emoji.
     */
    public function getSentimentEmojiAttribute(): string
    {
        return match ($this->sentiment_label) {
            'positive' => '😊',
            'negative' => '😞',
            'neutral' => '😐',
            default => '❓',
        };
    }

    /**
     * Get total engagement (likes + replies).
     */
    public function getTotalEngagementAttribute(): int
    {
        return ($this->likes ?? 0) + ($this->replies ?? 0) + ($this->shares ?? 0);
    }

    /**
     * Check if comment is a reply.
     */
    public function getIsReplyAttribute(): bool
    {
        return !is_null($this->parent_comment_id);
    }

    /**
     * Get the comment age in hours.
     */
    public function getAgeInHoursAttribute(): float
    {
        return $this->posted_at ? $this->posted_at->diffInHours(now()) : 0;
    }

    /**
     * Get the platform icon.
     */
    public function getPlatformIconAttribute(): string
    {
        $icons = [
            'facebook' => 'fab fa-facebook',
            'instagram' => 'fab fa-instagram',
            'twitter' => 'fab fa-twitter',
            'linkedin' => 'fab fa-linkedin',
            'tiktok' => 'fab fa-tiktok',
            'youtube' => 'fab fa-youtube',
        ];

        return $icons[$this->platform] ?? 'fas fa-comment';
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Approve the comment.
     */
    public function approve(?int $moderatorId = null): bool
    {
        $this->is_approved = true;
        $this->status = 'approved';
        $this->moderated_by = $moderatorId;
        $this->moderated_at = now();
        $this->is_flagged = false;
        
        return $this->save();
    }

    /**
     * Reject the comment.
     */
    public function reject(?int $moderatorId = null, ?string $notes = null): bool
    {
        $this->is_approved = false;
        $this->status = 'rejected';
        $this->moderated_by = $moderatorId;
        $this->moderated_at = now();
        $this->moderation_notes = $notes;
        
        return $this->save();
    }

    /**
     * Flag the comment.
     */
    public function flag(?string $reason = null): bool
    {
        $this->is_flagged = true;
        $this->status = 'flagged';
        $this->moderation_notes = $reason;
        
        return $this->save();
    }

    /**
     * Mark as spam.
     */
    public function markAsSpam(): bool
    {
        $this->is_spam = true;
        $this->is_flagged = true;
        $this->status = 'spam';
        $this->is_approved = false;
        
        return $this->save();
    }

    /**
     * Hide the comment.
     */
    public function hide(): bool
    {
        $this->is_hidden = true;
        $this->status = 'hidden';
        
        return $this->save();
    }

    /**
     * Pin the comment.
     */
    public function pin(): bool
    {
        $this->is_pinned = true;
        return $this->save();
    }

    /**
     * Unpin the comment.
     */
    public function unpin(): bool
    {
        $this->is_pinned = false;
        return $this->save();
    }

    /**
     * Add a reply to this comment.
     */
    public function addReply(array $data): self
    {
        $data['parent_comment_id'] = $this->id;
        $data['post_id'] = $this->post_id;
        $data['platform'] = $this->platform;
        $data['thread_id'] = $this->thread_id ?? $this->id;
        $data['is_reply'] = true;
        
        $reply = self::create($data);
        $this->increment('replies');
        
        return $reply;
    }

    /**
     * Get the comment thread.
     */
    public function getThread(): \Illuminate\Support\Collection
    {
        $thread = collect();
        $current = $this;
        
        // Get all ancestors
        while ($current->parentComment) {
            $thread->push($current->parentComment);
            $current = $current->parentComment;
        }
        
        // Get siblings and children
        $thread = $thread->reverse();
        $thread->push($this);
        
        // Get all replies
        if ($this->replies()->exists()) {
            $thread = $thread->merge($this->replies);
        }
        
        return $thread;
    }

    /**
     * Update sentiment analysis.
     */
    public function analyzeSentiment(): void
    {
        // This would typically call an external API or service
        // For demonstration, we'll use a simple keyword-based approach
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'love', 'thanks', 'awesome'];
        $negativeWords = ['bad', 'terrible', 'awful', 'hate', 'disappointed', 'poor', 'worst'];
        
        $content = strtolower($this->content);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (str_contains($content, $word)) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (str_contains($content, $word)) {
                $negativeCount++;
            }
        }
        
        $total = $positiveCount + $negativeCount;
        if ($total > 0) {
            $this->sentiment_score = ($positiveCount - $negativeCount) / $total;
        } else {
            $this->sentiment_score = 0;
        }
        
        $this->save();
    }

    /**
     * Get comment statistics.
     */
    public static function getStats($postId = null): array
    {
        $query = self::query();
        
        if ($postId) {
            $query->where('post_id', $postId);
        }

        return [
            'total' => $query->count(),
            'approved' => (clone $query)->where('is_approved', true)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'flagged' => (clone $query)->where('is_flagged', true)->count(),
            'spam' => (clone $query)->where('is_spam', true)->count(),
            'replies' => (clone $query)->where('is_reply', true)->count(),
            'total_likes' => (clone $query)->sum('likes'),
            'avg_sentiment' => (clone $query)->avg('sentiment_score'),
            'positive_sentiment' => (clone $query)->positiveSentiment()->count(),
            'negative_sentiment' => (clone $query)->negativeSentiment()->count(),
            'neutral_sentiment' => (clone $query)->neutralSentiment()->count(),
        ];
    }

    /**
     * Get nested comment tree.
     */
    public static function getCommentTree($postId): \Illuminate\Support\Collection
    {
        $comments = self::where('post_id', $postId)
            ->whereNull('parent_comment_id')
            ->where('is_approved', true)
            ->with(['replies' => function ($query) {
                $query->where('is_approved', true)
                    ->orderBy('created_at');
            }])
            ->orderBy('created_at')
            ->get();

        return $comments;
    }

    /**
     * Export comments to CSV.
     */
    public static function exportToCsv($postId): string
    {
        $comments = self::where('post_id', $postId)
            ->with(['user', 'post'])
            ->get();

        $filename = storage_path("app/comments_{$postId}_" . date('Y-m-d') . '.csv');
        $handle = fopen($filename, 'w');
        
        // Add headers
        fputcsv($handle, [
            'ID', 'User', 'Content', 'Platform', 'Likes', 'Replies',
            'Sentiment', 'Status', 'Posted At', 'Post Title'
        ]);
        
        // Add data
        foreach ($comments as $comment) {
            fputcsv($handle, [
                $comment->id,
                $comment->user_name,
                $comment->content,
                $comment->platform,
                $comment->likes,
                $comment->replies,
                $comment->sentiment_label,
                $comment->status,
                $comment->posted_at,
                $comment->post->title ?? 'N/A'
            ]);
        }
        
        fclose($handle);
        return $filename;
    }
}