<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $table = "ads";
    protected $fillable = ['ad_id','ad_adgroup_id', 'platform', 'social_account_id', 'ad_campaign_id','ad_creative_id', 'name', 'type', 'status', 'text', 'ad_format', 'call_to_action',
        'final_urls', 'headlines', 'user_id'
    ];
}
