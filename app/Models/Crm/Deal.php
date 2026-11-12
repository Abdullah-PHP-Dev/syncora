<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{

    /** G-02: tell the new owner when work is handed to them (not when they take it themselves). */
    protected static function booted(): void
    {
        static::created(fn (self $m) => $m->owner_id ? app(\App\Services\Crm\CrmNotifier::class)->dealAssigned($m) : null);
        static::updated(fn (self $m) => $m->wasChanged('owner_id') && $m->owner_id ? app(\App\Services\Crm\CrmNotifier::class)->dealAssigned($m) : null);
    }
    use BelongsToWorkspace, SoftDeletes;

    /** Columns that make a record "theirs" for teammates limited to own records. */
    public const CRM_VISIBILITY = ['owner_id'];

    protected $table = 'crm_deals';

    protected $fillable = [
        'workspace_id', 'pipeline_id', 'stage_id', 'contact_id', 'company_id', 'lead_id', 'owner_id', 'name', 'value', 'currency',
        'probability', 'expected_close_date', 'status', 'source', 'lost_reason', 'notes', 'win_reasons', 'competitor', 'won_at', 'lost_at', 'stage_changed_at', 'position', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2', 'probability' => 'integer', 'expected_close_date' => 'date:Y-m-d',
        'won_at' => 'datetime', 'lost_at' => 'datetime', 'stage_changed_at' => 'datetime', 'win_reasons' => 'array',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
