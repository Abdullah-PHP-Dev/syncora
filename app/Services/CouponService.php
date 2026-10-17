<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public static function minor(int|float|string $value): int
    {
        return (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function normalize(string $code): string
    {
        return Str::upper(trim($code));
    }

    /** Preview only: a preview never reserves inventory or creates a payment. */
    public function quote(User $user, Bundle $bundle, string $cycle, ?string $code = null, ?string $method = null, bool $lock = false): array
    {
        abort_unless($user->is_active !== false && $user->hasRole('seller') && ! $user->isTeamMember(), 403);
        abort_unless($bundle->is_active && ! $bundle->is_free, 404);
        if (! in_array($cycle, ['monthly', 'yearly'], true)) {
            $this->deny('invalid');
        }
        $subtotal = self::minor($cycle === 'yearly' ? $bundle->yearly_price : $bundle->price);
        $discount = 0;
        $coupon = null;
        if ($code !== null && trim($code) !== '') {
            $query = Coupon::where('code', self::normalize($code));
            $coupon = ($lock ? $query->lockForUpdate() : $query)->first();
            if (! $coupon || ! $coupon->is_active) {
                $this->deny('invalid');
            }
            if ($coupon->starts_at?->isFuture()) {
                $this->deny('not_started');
            }
            if ($coupon->ends_at && $coupon->ends_at->lte(now())) {
                $this->deny('expired');
            }
            if ($coupon->currency !== $bundle->currency || ! in_array($cycle, $coupon->cycles ?? [], true)) {
                $this->deny('ineligible');
            }
            if ($coupon->bundle_ids && ! in_array($bundle->id, $coupon->bundle_ids)) {
                $this->deny('ineligible');
            }
            if ($coupon->seller_ids && ! in_array($user->id, $coupon->seller_ids)) {
                $this->deny('ineligible');
            }
            if ($coupon->payment_methods && (! $method || ! in_array($method, $coupon->payment_methods, true))) {
                $this->deny('payment_method');
            }
            if ($subtotal <= 0 || $subtotal < self::minor($coupon->minimum_amount)) {
                $this->deny('minimum');
            }
            if ($cycle === 'yearly' && ! $coupon->allow_yearly_discount && $subtotal < self::minor($bundle->price) * 12) {
                $this->deny('yearly_discount');
            }
            if ($coupon->first_purchase_only && WalletTransaction::where('seller_id', $user->id)->where('category', 'subscription')->whereIn('status', ['pending', 'completed'])->exists()) {
                $this->deny('first_purchase');
            }
            $claims = CouponRedemption::where('coupon_id', $coupon->id)->claimed();
            if ($coupon->usage_limit !== null && $coupon->claimed_count >= $coupon->usage_limit) {
                $this->deny('exhausted');
            }
            if ($coupon->per_user_limit !== null && (clone $claims)->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
                $this->deny('user_limit');
            }
            $discount = $coupon->type === 'percentage'
                ? intdiv($subtotal * self::minor($coupon->value) + 5000, 10000)
                : self::minor($coupon->value);
            if ($coupon->maximum_discount !== null) {
                $discount = min($discount, self::minor($coupon->maximum_discount));
            }
            $discount = min($subtotal, max(0, $discount));
            if ($discount === 0) {
                $this->deny('minimum');
            }
            if ($coupon->discount_budget !== null && self::minor($coupon->claimed_discount) + $discount > self::minor($coupon->discount_budget)) {
                $this->deny('exhausted');
            }
        }

        return ['coupon_id' => $coupon?->id, 'coupon_code' => $coupon?->code, 'currency' => $bundle->currency, 'cycle' => $cycle]
            + app(SubscriptionPriceService::class)->breakdown($subtotal / 100, $discount / 100);
    }

    /** Called inside the owner/coupon lock used to build the payment transaction. */
    public function reserve(WalletTransaction $transaction, array $quote): void
    {
        if (! $quote['coupon_id']) {
            return;
        }
        CouponRedemption::create([
            'coupon_id' => $quote['coupon_id'], 'user_id' => $transaction->seller_id,
            'wallet_transaction_id' => $transaction->id, 'bundle_id' => $transaction->subject_id,
            'code' => $quote['coupon_code'], 'cycle' => $quote['cycle'], 'currency' => $quote['currency'],
            'subtotal' => $quote['subtotal'], 'discount' => $quote['discount'], 'total' => $quote['total'],
            'status' => 'reserved',
        ]);
        Coupon::whereKey($quote['coupon_id'])->increment('claimed_count');
        Coupon::whereKey($quote['coupon_id'])->increment('claimed_discount', $quote['discount']);
    }

    public function complete(WalletTransaction $transaction): void
    {
        CouponRedemption::where('wallet_transaction_id', $transaction->id)->where('status', 'reserved')->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function release(WalletTransaction $transaction): void
    {
        // The caller holds the seller lock; the coupon lock serializes inventory.
        if (! $transaction->coupon_id) {
            return;
        }
        $coupon = Coupon::withTrashed()->whereKey($transaction->coupon_id)->lockForUpdate()->firstOrFail();
        $redemption = CouponRedemption::where('wallet_transaction_id', $transaction->id)->where('status', 'reserved')->lockForUpdate()->first();
        if (! $redemption) {
            return;
        }
        $redemption->update(['status' => 'released', 'released_at' => now()]);
        $coupon->decrement('claimed_count');
        $coupon->decrement('claimed_discount', (float) $redemption->discount);
    }

    private function deny(string $reason): never
    {
        throw ValidationException::withMessages(['coupon_code' => __('coupons.errors.'.$reason)]);
    }
}
