<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content-posting-side profile statistics for a social_accounts row -
 * followers, subscribers, likes, views, impressions, following, media
 * count. Never populated for a row that only has has_ads_permission.
 */
class SocialAccountPostDetails extends Model
{
    protected $table = 'social_account_post_details';

    protected $fillable = [
        'social_account_id',
        'followers_count',
        'subscribers_count',
        'likes_count',
        'views_count',
        'impressions_count',
        'following_count',
        'media_count',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
