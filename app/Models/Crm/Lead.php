<?php

namespace App\Models\Crm;

use App\Models\Admin\AdCampaign;
use App\Models\Crm\Concerns\BelongsToWorkspace;
use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted'];

    public const DISQUALIFY_REASONS = ['budget', 'not_interested', 'spam', 'duplicate', 'competitor', 'no_response', 'outside_area', 'other'];

    /** Columns that make a record "theirs" for teammates limited to own records. */
    public const CRM_VISIBILITY = ['owner_id'];

    protected $table = 'crm_leads';

    protected $fillable = [
        'workspace_id', 'contact_id', 'owner_id', 'status', 'source', 'campaign', 'ad_campaign_id', 'conversation_id',
        'interest', 'estimated_value', 'currency', 'score', 'disqualify_reason', 'disqualify_note', 'converted_at', 'deal_id', 'created_by', 'external_lead_id', 'meta',
    ];

    protected $casts = ['estimated_value' => 'decimal:2', 'score' => 'integer', 'converted_at' => 'datetime', 'meta' => 'array'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
