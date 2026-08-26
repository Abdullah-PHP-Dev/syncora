<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SocialAccount;
use App\Models\User;

class PlatformPage extends Model
{
    protected $table = 'platform_pages';
    protected $fillable = ['platform', 'user_id', 'social_account_id', 'page_id', 'name', 'username', 'description', 'category', 'link', 'likes_count', 'followers_count', 'business_id', 'access_token', 'picture', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
