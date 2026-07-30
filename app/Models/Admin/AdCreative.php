<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class AdCreative extends Model
{
    protected $table = "ad_creatives";
    protected $fillable = ['ad_adgroup_id', 'user_id', 'platform', 'ad_account_id', 'ad_campaign_id', 'name', 'platform', 'top_snap_media_id', 'brand_name', 'ad_creative_id', 'profile_id',
        'headline', 'ad_format', 'page_id', 'message', 'ad_media_files', 'url', 'type', 'top_snap_crop_position', 'chat_properties', 'web_view_properties', 'app_install_properties',
        'deep_link_properties', 'ad_to_lens_properties', 'ad_to_call_properties','call_to_action', 'ad_to_message_properties', 'lead_generation_form_id', 'composite_properties', 'preview_properties',
        'headlines', 'descriptions', 'long_headlines', 'business_name', 'logo_asset_resource', 'video_asset_resource', 'final_urls',
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

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(AdAdGroup::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            AdMedia::class,
            'ad_creative_media',
            'ad_creative_id',
            'ad_media_id'
        );
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
