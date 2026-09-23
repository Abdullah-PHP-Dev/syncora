<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per seller - the isolated SendGrid subuser + API key that every
 * other Email Marketing table (domains, senders, campaigns) sends through.
 * api_key is encrypted at rest, same pattern as UserIntegration::credentials
 * (the only other encrypted-cast column in this app) - it is never the
 * parent SendGrid account's own key, which lives only in
 * adminSetting('email_marketing.sendgrid.parent_api_key') and is used
 * solely to provision this row in the first place.
 */
class EmailSubaccount extends Model
{
    protected $fillable = [
        'user_id', 'sendgrid_user_id', 'sendgrid_username', 'sendgrid_email',
        'status', 'api_key', 'webhook_public_key', 'region', 'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'api_key'         => 'encrypted:array',
        'last_synced_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(VerifiedDomain::class);
    }

    public function senderIdentities(): HasMany
    {
        return $this->hasMany(SenderIdentity::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !empty($this->api_key['key'] ?? null);
    }

    /**
     * The Bearer token for every SendGrid call made as this seller - never
     * the parent key. Null when not yet provisioned/active.
     */
    public function apiKeyValue(): ?string
    {
        return $this->api_key['key'] ?? null;
    }
}
