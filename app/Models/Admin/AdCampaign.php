<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class AdCampaign extends Model
{
    protected $table = 'ad_campaigns';
    protected $fillable = ['ad_account_id', 'user_id', 'name', 'status', 'ad_campaign_id', 'objective', 'special_ad_category', 'app_promotion_type', 'bidding_amount', 'budget_mode', 'budget', 'daily_budget', 'budget_resource_id', 'start_time', 'end_time', 'bidding_strategy', 'bidding_resource_id', 'advertising_channel_type', 'advertising_channel_sub_type', 'hotel_center_id', 'app_store', 'app_id', 'merchant_id', 'cpc_bid_ceiling_micros', 'funding_instrument_id', 'platform'];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adGroups(): HasMany
    {
        return $this->hasMany(AdAdGroup::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
