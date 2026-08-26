<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMedia extends Model
{
    protected $fillable = [
        'post_id',
        'user_id',
        'platform',
        'social_account_id',
        'post_category_id',
        'media_url',
        'media_type',
        'file_name',
        'file_size',
        'mime_type',
        'width',
        'height',
        'duration_seconds',
        'thumbnail_url',
        'alt_text',
        'media_id',
        'sort_order',
        'is_primary',
        'platform_metadata',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'platform_metadata' => 'array',
        'metadata' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'sort_order' => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }

    public function duplicate(array $overrides = []): self
    {
        $newMedia = $this->replicate();
        $newMedia->fill($overrides);
        $newMedia->save();
        return $newMedia;
    }
}