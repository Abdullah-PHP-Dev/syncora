<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A single FAQ entry - System (user_id null, managed by an 'admin'-role
 * user, powers every seller's read-only Help Center) or a seller's own
 * business FAQ (user_id set, powers that seller's AI Copilot / their
 * customer-facing knowledge base once that phase is built). Only
 * 'published' FAQs are ever shown to a seller's Help Center or matched
 * against by the (future) AI Copilot retrieval - 'draft' is authoring-only,
 * matching the BRD's "suggestion engine proposes, it never auto-publishes"
 * principle even at this manual-authoring stage.
 */
class Faq extends Model
{
    use LogsActivity;

    protected $fillable = [
        'faq_category_id', 'user_id', 'question', 'answer', 'language',
        'status', 'tags', 'helpful_count', 'unhelpful_count',
        'embedding', 'embedding_model',
    ];

    protected $casts = [
        'tags'      => 'array',
        'embedding' => 'array',
    ];

    // A 3072-float vector per row is useless to (and needlessly bloats)
    // every FaqManager.vue list/edit payload - AiCopilotService reads it
    // directly off the model server-side, it never needs to leave PHP.
    protected $hidden = [
        'embedding',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['question', 'answer', 'status', 'faq_category_id', 'language'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
