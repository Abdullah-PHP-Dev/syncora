<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SocialAccount;
use App\Models\User;

class Ad extends Model
{
    protected $table = "ads";
    protected $fillable = ['ad_id','ad_adgroup_id', 'platform', 'social_account_id', 'ad_campaign_id','ad_creative_id', 'name', 'type', 'status', 'text', 'ad_format', 'call_to_action',
        'final_urls', 'headlines', 'user_id'
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function adAdGroup(): BelongsTo
    {
        return $this->belongsTo(AdAdGroup::class);
    }

    public function adCreative(): BelongsTo
    {
        return $this->belongsTo(AdCreative::class);
    }
}
