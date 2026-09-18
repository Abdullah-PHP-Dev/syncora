<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marketing/ads-side account details for a social_accounts row - currency,
 * business id, account status, spend. Never populated for a row that only
 * has has_posting_permission.
 */
class SocialAccountAdDetails extends Model
{
    protected $table = 'social_account_ad_details';

    protected $fillable = [
        'social_account_id',
        'currency',
        'account_status',
        'business_id',
        'timezone',
        'spend_cap',
        'amount_spent',
        'balance',
        'funding_source',
        'last_synced_at',
    ];

    protected $casts = [
        'spend_cap' => 'decimal:2',
        'amount_spent' => 'decimal:2',
        'balance' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
