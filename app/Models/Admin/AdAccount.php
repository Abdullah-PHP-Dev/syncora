<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;


class AdAccount extends Model
{
    protected $table = 'ad_accounts';
    protected $fillable = ['platform', 'user_id', 'name', 'currency', 'client_id', 'client_id', 'token_secret', 'status', 'access_token', 'refresh_token', 'expires_at', 'profile_id'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function adGroups(): HasMany
    {
        return $this->hasMany(AdAdGroup::class);
    }

    public function adCreatives(): HasMany
    {
        return $this->hasMany(AdCreative::class);
    }

    public function adMedias(): HasMany
    {
        return $this->hasMany(AdMedia::class);
    }

    public function adAds(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
