<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdCampaign extends Model
{
    protected $table = 'ad_campaigns';
    protected $fillable = ['ad_account_id', 'name', 'status', 'campaign_id', 'objective', 'app_promotion_type', 'bidding_amount', 'budget_mode', 'budget', 'daily_budget', 'budget_resource_id', 'start_time', 'end_time', 'bidding_strategy', 'bidding_resource_id', 'advertising_channel_type', 'advertising_channel_sub_type', 'hotel_center_id', 'app_store', 'app_id', 'merchant_id', 'cpc_bid_ceiling_micros', 'funding_instrument_id', 'account_to_use', 'platform'];
}
