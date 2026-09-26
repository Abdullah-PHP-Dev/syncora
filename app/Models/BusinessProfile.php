<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProfile extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'business_hours', 'phone',
        'address', 'delivery_policy', 'return_policy', 'extra',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'extra'          => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
