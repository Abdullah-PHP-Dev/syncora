<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionCheckoutService
{
    public function __construct(private CouponService $coupons) {}

    public function start(User $user, Bundle $bundle, string $cycle, string $method, ?string $gateway = null, ?string $code = null, ?string $key = null, int|float|string|null $expectedTotal = null): array
    {
        abort_unless($user->hasRole('seller') && ! $user->isTeamMember(), 403);
        if ($method === 'card') {
            $gateway ??= app(\App\Services\Payments\Gateways\CardGatewayManager::class)->selectedGateway();
        }
        $key ??= (string) Str::uuid();
        // A settings change must not invalidate a retry of the same card checkout.
        $fingerprint = hash('sha256', json_encode([$bundle->id, $cycle, $method, $method === 'card' ? null : $gateway, CouponService::normalize($code ?? '')]));
        [$transaction, $new] = DB::transaction(function () use ($user, $bundle, $cycle, $method, $gateway, $code, $key, $fingerprint, $expectedTotal) {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($owner->is_active === false, 403);
            $existing = WalletTransaction::where('seller_id', $user->id)->where('checkout_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->meta['checkout_fingerprint'] ?? '', $fingerprint), 409, __('coupons.checkout_changed'));

                return [$existing, false];
            }
            // Keep the existing abuse limit, under the same owner lock as creation.
            if (WalletTransaction::where('seller_id', $user->id)->whereIn('status', ['pending', 'rejected'])->whereDate('created_at', today())->count() >= 5) {
                throw ValidationException::withMessages(['payment_method' => __('Too many failed payment attempts. Please try again tomorrow.')]);
            }
            $currentBundle = Bundle::whereKey($bundle->id)->lockForUpdate()->firstOrFail();
            $bundle->setRawAttributes($currentBundle->getAttributes(), true);
            app(SubscriptionChangePolicy::class)->assertCanSelect($owner, $bundle);
            $quote = $this->coupons->quote($user, $bundle, $cycle, $code, $gateway ?? $method, true);
            if ($expectedTotal !== null && CouponService::minor($quote['total']) !== CouponService::minor($expectedTotal)) {
                throw ValidationException::withMessages(['coupon_code' => __('coupons.price_changed')]);
            }
            $transaction = WalletTransaction::create([
                'seller_id' => $user->id, 'amount' => $quote['total'], 'vat' => $quote['vat'], 'discount' => $quote['discount'], 'coupon_id' => $quote['coupon_id'],
                'direction' => 'debit', 'category' => 'subscription', 'status' => 'pending',
                'subject_type' => $bundle->getMorphClass(), 'subject_id' => $bundle->id,
                'callback' => SubscriptionService::class, 'checkout_key' => $key,
                'payment_method' => $method, 'payment_gateway' => $gateway,
                'meta' => ['plan' => $bundle->slug, 'bundle_id' => $bundle->id, 'bundle_name' => $bundle->name_en, 'cycle' => $cycle, 'currency' => $quote['currency'], 'subtotal' => $quote['subtotal'], 'coupon_code' => $quote['coupon_code'], 'checkout_fingerprint' => $fingerprint, 'vat_rate' => $quote['vat_rate'], 'net_amount' => $quote['net_amount'], 'prices_include_vat' => true],
            ]);
            $this->coupons->reserve($transaction, $quote);

            return [$transaction, true];
        }, 3);
        if (! $new) {
            return $this->result($transaction);
        }
        if (CouponService::minor($transaction->amount) === 0) {
            DB::transaction(function () use ($transaction) {
                User::whereKey($transaction->seller_id)->lockForUpdate()->firstOrFail();
                $transaction->update(['status' => 'completed', 'payment_method' => 'coupon', 'processed_at' => now()]);
                $this->fulfill($transaction);
            });

            return $this->result($transaction->fresh());
        }
        $pay = function () use ($transaction, $user, $bundle, $cycle, $method, $gateway) {
            $payment = $method === 'card' && $gateway === 'tap' && ! config('services.payment.tap.secret_key')
                ? ['status' => 'failed', 'message' => __('coupons.gateway_missing')]
                : app(PaymentManager::class)->driver($method)->pay([
                    'user' => $user->fresh(), 'amount' => (float) $transaction->amount, 'bundle' => $bundle,
                    'cycle' => $cycle, 'type' => 'subscription', 'transaction' => $transaction, 'gateway' => $gateway,
                ]);
            if (($payment['status'] ?? '') === 'paid') {
                $transaction->update(['status' => 'completed', 'processed_at' => now()]);
                $this->fulfill($transaction);
            } elseif (($payment['status'] ?? '') === 'failed') {
                $this->fail($transaction, $payment['message'] ?? __('Payment failed.'));
            } elseif (($payment['status'] ?? '') === 'pending' && ! empty($payment['redirect_url'])) {
                $meta = $transaction->fresh()->meta;
                $meta['redirect_url'] = $payment['redirect_url'];
                $transaction->update(['meta' => $meta] + (! empty($payment['session_id']) ? ['gateway_reference' => $payment['session_id']] : []));
            }

            return $payment;
        };
        try {
            // Internal balance changes and subscription fulfillment commit together.
            if (in_array($method, ['wallet', 'cashback'], true)) {
                DB::transaction(function () use ($user, $pay) {
                    User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $pay();
                }, 3);
            } else {
                $pay(); // No database locks are held across provider requests.
            }
        } catch (Throwable $exception) {
            report($exception);
            if (in_array($method, ['wallet', 'cashback'], true)) {
                $this->fail($transaction->fresh(), __('Payment failed.'));
            }
            // A provider timeout is ambiguous: retain the reservation until verified.
        }

        return $this->result($transaction->fresh());
    }

    public function result(WalletTransaction $transaction): array
    {
        if ($transaction->fulfilled_at) {
            return ['success' => true, 'payment_type' => 'instant', 'redirect_url' => route('dashboard'), 'transaction_id' => $transaction->id];
        }
        if ($transaction->status === 'rejected') {
            return ['success' => false, 'retryable' => true, 'message' => $transaction->remarks ?: __('Payment failed.')];
        }
        if ($url = ($transaction->meta['redirect_url'] ?? null)) {
            return ['success' => true, 'payment_type' => 'redirect', 'redirect_url' => $url, 'transaction_id' => $transaction->id];
        }

        return ['success' => false, 'retryable' => false, 'pending' => true, 'message' => __('coupons.payment_pending')];
    }

    /** Only a server-verified provider result may complete a payment. */
    public function complete(WalletTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            User::whereKey($transaction->seller_id)->lockForUpdate()->firstOrFail();
            $transaction = WalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->fulfilled_at || $transaction->status === 'rejected') {
                return;
            }
            $transaction->update(['status' => 'completed', 'processed_at' => now()]);
            $this->fulfill($transaction);
        }, 3);
    }

    /** Only trusted server-verified payments may enter this method. */
    public function fulfill(WalletTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            User::whereKey($transaction->seller_id)->lockForUpdate()->firstOrFail();
            $transaction = WalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->fulfilled_at) {
                return;
            }
            abort_unless($transaction->status === 'completed' && $transaction->category === 'subscription', 409);
            $user = $transaction->seller;
            abort_unless($user->hasRole('seller') && ! $user->isTeamMember(), 403);
            $bundle = Bundle::findOrFail($transaction->meta['bundle_id']);
            // A previously started checkout must not downgrade a newer active package.
            app(SubscriptionChangePolicy::class)->assertCanSelect($user, $bundle);
            $months = ($transaction->meta['cycle'] ?? 'monthly') === 'yearly' ? 12 : 1;
            $subscription = Subscription::where('user_id', $user->id)->latest('id')->first();
            // Only the same unexpired bundle extends its existing term; a bundle change starts today.
            $renewal = $subscription && (int) $subscription->bundle_id === (int) $bundle->id
                && ($subscription->end_date?->copy()->endOfDay()->gte(now()) ?? false);
            $start = $renewal ? $subscription->end_date->copy() : now();
            $end = $start->copy()->addMonthsNoOverflow($months);
            $values = ['bundle_id' => $bundle->id, 'bundle_name' => $transaction->meta['bundle_name'] ?? $bundle->name_en, 'billing_period' => $months, 'start_date' => $renewal ? $subscription->start_date : $start, 'end_date' => $end, 'status' => 'active', 'is_active' => true];
            if ($subscription) {
                $subscription->update($values);
            } else {
                $subscription = Subscription::create(['user_id' => $user->id] + $values);
            }
            SubscriptionCycle::create(['subscription_id' => $subscription->id, 'user_id' => $user->id, 'bundle_id' => $bundle->id, 'start_date' => $start, 'end_date' => $end, 'type' => $renewal ? 'renewal' : 'purchase', 'status' => 'active']);
            $this->coupons->complete($transaction);
            $transaction->update(['fulfilled_at' => now()]);
        }, 3);
    }

    public function fail(WalletTransaction $transaction, string $message): void
    {
        DB::transaction(function () use ($transaction, $message) {
            User::whereKey($transaction->seller_id)->lockForUpdate()->firstOrFail();
            $transaction = WalletTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->fulfilled_at || $transaction->status === 'completed') {
                return;
            }
            $transaction->update(['status' => 'rejected', 'remarks' => $message]);
            $this->coupons->release($transaction);
        });
    }
}
