<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class AdMedia extends Model
{
    protected $table = 'ad_media';
    protected $fillable = ['user_id','ad_media_id','platform','ad_account_id','ad_campaign_id','name','file_name','download_link','type','status','image_category','url','upload_by_type', 'ad_format','signature', 'file_id', 'title', 'description'];


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

    public function creatives(): BelongsToMany
    {
        return $this->belongsToMany(
            AdCreative::class,
            'ad_creative_media',
            'ad_media_id',
            'ad_creative_id'
        );
    }

    public function adAds(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function adCreativeMedia(): HasOne
    {
        return $this->hasOne(AdCreativeMedia::class);
    }
}
