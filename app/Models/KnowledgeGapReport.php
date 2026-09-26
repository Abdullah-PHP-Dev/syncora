<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeGapReport extends Model
{
    protected $fillable = [
        'user_id', 'question', 'question_hash', 'occurrence_count',
        'last_occurred_at', 'status', 'suggested_faq_id',
    ];

    protected $casts = [
        'last_occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'suggested_faq_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Lowercase, strip punctuation, collapse whitespace - a deliberately
     * simple exact-phrasing dedup key (see this table's migration
     * docblock for why it's not semantic clustering).
     */
    public static function normalize(string $question): string
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
}
