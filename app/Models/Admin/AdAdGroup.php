<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class AdAdGroup extends Model
{
    protected $table = "ad_adgroups";
    protected $fillable = ['ad_adgroup_id', 'user_id', 'platform', 'ad_account_id', 'ad_campaign_id', 'name', 'promotion_type', 'promotion_target_type', 'placement_type', 'placements', 'location_ids',
        'gender', 'operating_systems', 'operating_systems', 'audience_type', 'budget_mode', 'budget', 'schedule_type', 'schedule_start_time', 'schedule_end_time', 'optimization_goal', 'bid_type',
        'bid_price', 'conversion_bid_price', 'deep_bid_type', 'roas_bid', 'bid_display_mode', 'billing_event', 'pacing', 'status', 'age_groups', 'primary_web_event_tag',
        'ios', 'android', 'objective', 'publisher_platforms', 'languages', 'destination_type', 'bid_strategy', 'keywords'
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(AdCreative::class, 'ad_adgroup_id', 'id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
