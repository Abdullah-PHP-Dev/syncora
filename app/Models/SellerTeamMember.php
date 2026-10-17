<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerTeamMember extends Model
{
    protected $fillable = ['owner_id', 'user_id', 'email', 'token_hash', 'expires_at', 'accepted_at', 'permissions'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOccupyingSeat($query)
    {
        return $query->where(fn ($query) => $query->whereNotNull('accepted_at')->orWhere('expires_at', '>', now()));
    }
}
