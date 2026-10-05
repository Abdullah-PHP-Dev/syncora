<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletTransaction extends Model
{
    use HasFactory;


    protected static function boot()
    {
        parent::boot();

        static::created(function ($transaction) {
            $transaction->transaction_no = self::generateTransactionNo($transaction->id);
            $transaction->saveQuietly();
        });
    }



    protected $fillable = [
        'transaction_no',
        'seller_id',
        'vendor_id',
        'parent_transaction_id',
        'amount',
        'direction',
        'category',
        'status',
        'available_after',
        'pending_after',
        'reference_type',
        'reference_id',
        'payment_method',
        'payment_gateway',
        'gateway_reference',
        'card_brand',
        'card_scheme',
        'vat',
        'service_charges',
        'discount',
        'is_recurring',
        'is_cashback',
        'coupon_id',
        'approved_by',
        'remarks',
        'meta',
        'processed_at',
        'expires_at',
        'subject_type',
        'subject_id',
        'callback',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'available_after' => 'decimal:2',
        'pending_after' => 'decimal:2',
        'vat' => 'decimal:2',
        'service_charges' => 'decimal:2',
        'discount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_cashback' => 'boolean',
        'meta' => 'array',
        'processed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCredits($query)
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', 'debit');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function generateTransactionNo($id)
    {
        return 'TXN-' . str_pad($id, 10, '0', STR_PAD_LEFT);
    }

    public function isCredit()
    {
        return $this->direction === 'credit';
    }

    public function isDebit()
    {
        return $this->direction === 'debit';
    }

    public function paymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class, 'wallet_transaction_id');
    }

    public function shopInfo()
    {
        return $this->hasOne(Shop::class, 'user_id','seller_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
