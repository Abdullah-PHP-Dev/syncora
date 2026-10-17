<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'claimed_count', 'claimed_discount'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'minimum_amount' => 'decimal:2', 'maximum_discount' => 'decimal:2', 'discount_budget' => 'decimal:2', 'bundle_ids' => 'array', 'seller_ids' => 'array', 'cycles' => 'array', 'payment_methods' => 'array', 'is_active' => 'boolean', 'first_purchase_only' => 'boolean', 'allow_yearly_discount' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'version' => 'integer'];
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function changes()
    {
        return $this->hasMany(CouponChange::class);
    }
}
