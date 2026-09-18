<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category for FAQs - null user_id means it's a Socialeaz System
 * category (managed by an 'admin'-role user, shown to every seller's
 * Help Center); a set user_id means it belongs to that one seller's own
 * business Knowledge Base. See faqs_table migration for why this is one
 * table per concept instead of two.
 */
class FaqCategory extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'sort_order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
