<?php

namespace App\Models\EmailMarketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainDnsRecord extends Model
{
    protected $fillable = [
        'verified_domain_id', 'record_purpose', 'type', 'host', 'data', 'valid',
    ];

    protected $casts = [
        'valid' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(VerifiedDomain::class, 'verified_domain_id');
    }

    public function label(): string
    {
        return match ($this->record_purpose) {
            'mail_cname' => 'Mail CNAME (SPF)',
            'dkim1'      => 'DKIM Key 1',
            'dkim2'      => 'DKIM Key 2',
            default      => $this->record_purpose,
        };
    }
}
