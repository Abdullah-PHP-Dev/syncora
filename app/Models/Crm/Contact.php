<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use App\Models\Messaging\Conversation;
use App\Models\User;
use App\Services\Crm\ContactMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    public const LIFECYCLES = ['subscriber', 'lead', 'qualified', 'opportunity', 'customer', 'churned'];

    /** Columns that make a record "theirs" for teammates limited to own records. */
    public const CRM_VISIBILITY = ['owner_id'];

    protected $table = 'crm_contacts';

    protected $fillable = [
        'workspace_id', 'owner_id', 'company_id', 'first_name', 'last_name', 'arabic_name', 'email', 'phone',
        'job_title', 'lifecycle', 'source', 'language', 'preferred_channel', 'score', 'whatsapp_consent',
        'tags', 'custom_fields', 'last_activity_at', 'created_by',
    ];

    protected $casts = [
        'tags' => 'array', 'custom_fields' => 'array', 'whatsapp_consent' => 'boolean',
        'score' => 'integer', 'last_activity_at' => 'datetime',
    ];

    protected $appends = ['full_name'];

    protected static function booted(): void
    {
        // The match key always follows the phone, whoever sets it.
        // Plan capacity, whichever path creates the contact (forms, Inbox, import, lead ads).
        static::creating(function (Contact $contact) {
            if ($contact->workspace_id && ($account = \App\Models\User::find($contact->workspace_id))) {
                \App\Services\Crm\CrmLimits::ensureRoom($account);
            }
        });

        static::saving(function (Contact $contact) {
            if ($contact->isDirty('phone')) {
                $contact->phone_key = ContactMatcher::phoneKey($contact->phone);
            }
            if ($contact->isDirty('email') && $contact->email) {
                $contact->email = mb_strtolower(trim($contact->email));
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'crm_contact_id');
    }
}
