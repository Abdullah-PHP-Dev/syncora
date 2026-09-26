<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-seller AI Copilot configuration. Always read through forSeller()
 * rather than the relation/query directly, so every caller gets sane
 * defaults (AI off) instead of having to null-check a seller who has
 * never opened the settings page.
 */
class AiCopilotSetting extends Model
{
    protected $fillable = [
        'user_id', 'ai_enabled', 'auto_reply_enabled',
        'confidence_threshold_auto', 'confidence_threshold_suggested',
    ];

    protected $casts = [
        'ai_enabled'         => 'boolean',
        'auto_reply_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns the seller's saved settings row, or an unsaved instance
     * carrying the migration's own defaults (AI disabled) if they've
     * never configured it. Never persists the unsaved instance - a read
     * must not have a write side effect.
     */
    public static function forSeller(int $userId): self
    {
        return static::firstWhere('user_id', $userId) ?? new static([
            'user_id'                        => $userId,
            'ai_enabled'                      => false,
            'auto_reply_enabled'              => false,
            'confidence_threshold_auto'       => 80,
            'confidence_threshold_suggested'  => 50,
        ]);
    }
}
