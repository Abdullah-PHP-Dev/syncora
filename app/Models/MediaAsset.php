<?php

namespace App\Models;

use App\Models\Admin\AdCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One file in a seller's Media Gallery - see the create_media_assets_table
 * migration for how it relates to post_media / ad campaigns.
 */
class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'disk', 'path', 'url', 'thumbnail_path', 'thumbnail_url',
        'original_name', 'extension', 'mime_type', 'size', 'width', 'height',
        'duration', 'video_codec', 'compatibility', 'compatible_platforms',
    ];

    protected $casts = [
        'size'                 => 'integer',
        'width'                => 'integer',
        'height'               => 'integer',
        'duration'             => 'float',
        'compatibility'        => 'array',
        'compatible_platforms' => 'array',
    ];

    // Storage internals never need to reach the browser.
    protected $hidden = ['disk', 'path', 'thumbnail_path', 'deleted_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function adCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(AdCampaign::class, 'ad_campaign_media_asset')->withTimestamps();
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * The same shape PostController::uploadQuickPostMedia() returns per
     * file - every *PostService::store() reads $data['uploaded_media'] in
     * this shape, so a gallery asset slots in with no re-upload.
     */
    public function toUploadedMedia(): array
    {
        return [
            'success'          => true,
            'media_type'       => $this->type === 'image' && $this->extension === 'gif' ? 'gif' : $this->type,
            'file_name'        => basename($this->path),
            'original_name'    => $this->original_name,
            'extension'        => $this->extension,
            'mime_type'        => $this->mime_type,
            'file_size'        => $this->size,
            'file_size_mb'     => round($this->size / 1024 / 1024, 2),
            'width'            => $this->width,
            'height'           => $this->height,
            'duration_seconds' => $this->duration,
            'thumbnail_url'    => $this->thumbnail_url,
            'alt_text'         => pathinfo($this->original_name, PATHINFO_FILENAME),
            'url'              => $this->url,
            'path'             => $this->path,
            'media_asset_id'   => $this->id,
        ];
    }

    /**
     * Whether anything outside the gallery still points at this file.
     * Posts reuse the asset's own R2 object (see toUploadedMedia()), so
     * deleting it would break their media. Ad campaigns uploaded their own
     * copy to the ad platform, so they don't hold the file.
     */
    public function isReferenced(): bool
    {
        return PostMedia::where('media_asset_id', $this->id)
            ->orWhere('media_url', $this->url)
            ->exists();
    }
}
