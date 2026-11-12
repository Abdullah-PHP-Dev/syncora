<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A task, call, meeting, note or email log, or a history entry (type "event"). */
class Activity extends Model
{
    use BelongsToWorkspace;

    public const TYPES = ['task', 'call', 'meeting', 'note', 'email', 'event'];

    /** Columns that make a record "theirs" for teammates limited to own records. */
    public const CRM_VISIBILITY = ['owner_id', 'created_by'];

    protected $table = 'crm_activities';

    protected $fillable = [
        'workspace_id', 'type', 'subject', 'body', 'due_at', 'completed_at', 'owner_id', 'created_by',
        'contact_id', 'company_id', 'lead_id', 'deal_id', 'conversation_id', 'meta',
    ];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime', 'meta' => 'array'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
