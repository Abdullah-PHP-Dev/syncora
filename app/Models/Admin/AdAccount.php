<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdAccount extends Model
{
    protected $table = 'ad_accounts';
    protected $fillable = ['platform', 'name', 'currency', 'client_id', 'client_id', 'token_secret', 'status', 'access_token', 'refresh_token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
