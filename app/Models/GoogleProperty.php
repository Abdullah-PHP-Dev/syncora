<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A GA4 property or Search Console site the workspace's Google account can
 * read, found by discovery. At most one per service is selected - that one
 * feeds the dashboards (docs: Google Suite, Analytics & Insights).
 */
class GoogleProperty extends Model
{
    public const GA4 = 'ga4';
    public const SEARCH_CONSOLE = 'search_console';

    public const SERVICES = [self::GA4, self::SEARCH_CONSOLE];

    protected $fillable = [
        'user_id', 'social_connection_id', 'service', 'external_id', 'display_name', 'account_name',
        'meta', 'is_selected', 'last_synced_at', 'last_error_code', 'last_error', 'last_error_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_selected' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class, 'social_connection_id');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSelected(Builder $query, string $service): Builder
    {
        return $query->where(['service' => $service, 'is_selected' => true]);
    }

    /** Numeric id the Data API wants ("properties/123" -> "123"). */
    public function ga4PropertyId(): string
    {
        return str_replace('properties/', '', $this->external_id);
    }

    public function recordSuccess(): void
    {
        $this->forceFill(['last_synced_at' => now(), 'last_error_code' => null, 'last_error' => null, 'last_error_at' => null])->save();
    }

    public function recordFailure(string $code, string $message): void
    {
        $this->forceFill(['last_error_code' => $code, 'last_error' => mb_substr($message, 0, 250), 'last_error_at' => now()])->save();
    }
}
