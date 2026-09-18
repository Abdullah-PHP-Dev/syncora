<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ticket extends Model
{
    use LogsActivity;

    protected $fillable = [
        'ticket_number', 'user_id', 'assigned_to', 'subject', 'category',
        'priority', 'status', 'last_activity_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'resolved_at'      => 'datetime',
        'closed_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * Customer-visible messages only - excludes agent-only internal
     * notes. Used by the seller-facing ticket thread view; the admin
     * ticket view uses messages() directly to see everything.
     */
    public function visibleMessages(): HasMany
    {
        return $this->messages()->where('is_internal_note', false);
    }

    public static function generateTicketNumber(): string
    {
        return 'TKT-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject', 'category', 'priority', 'status', 'assigned_to'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
