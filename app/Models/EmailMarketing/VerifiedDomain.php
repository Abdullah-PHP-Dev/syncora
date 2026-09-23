<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifiedDomain extends Model
{
    protected $fillable = [
        'user_id', 'email_subaccount_id', 'sendgrid_domain_id', 'domain',
        'subdomain', 'automatic_security', 'is_default', 'status',
        'dns_last_synced_at',
    ];

    protected $casts = [
        'automatic_security' => 'boolean',
        'is_default'         => 'boolean',
        'dns_last_synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(EmailSubaccount::class, 'email_subaccount_id');
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DomainDnsRecord::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
