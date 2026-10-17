<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionService
{
    private const DEFAULT_TRIAL_DAYS = 15;

    private const DEFAULT_DURATION_DAYS = 30;

    private const DEFAULT_SUBSCRIPTION = 'subscription';

    public function assignFreeTrial(User $user): Subscription
    {

        $this->ensureSeller($user);

        $bundle = Bundle::query()->where('is_free', true)->where('is_active', true)->first();

        if (! $bundle) {
            throw new RuntimeException('Free bundle not found.');
        }

        [$start, $end] = $this->resolveDates((int) data_get($bundle->meta, 'trial_days', self::DEFAULT_TRIAL_DAYS), true);

        return DB::transaction(function () use ($user, $bundle, $start, $end) {
            $subscription = Subscription::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bundle_id' => $bundle->id,
                    'bundle_name' => $bundle->name_en,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'trial',
                    'is_active' => true,
                ]
            );

            $this->createCycle($subscription, $bundle->id, 'trial', $start, $end);

            return $subscription;
        });
    }

    public function renew(Subscription $subscription, string $cycle): Subscription
    {
        $this->ensureSeller($subscription->user);

        $bundle = Bundle::findOrFail($subscription->bundle_id);

        $months = $this->resolveCycleToMonths($cycle);

        [$start, $end] = $this->resolveDates($months);

        return DB::transaction(function () use ($subscription, $bundle, $start, $end, $months) {

            $subscription->update([
                'start_date' => $start,
                'end_date' => $end,
                'billing_period' => $months,
                'status' => 'active',
                'is_active' => true,
            ]);

            $this->createCycle(
                $subscription,
                $bundle->id,
                'renewal',
                $start,
                $end
            );

            return $subscription->fresh();
        });
    }

    public function upgrade(
        Subscription $subscription,
        Bundle $newBundle,
        string $cycle
    ): Subscription {
        $this->ensureSeller($subscription->user);

        $months = $this->resolveCycleToMonths($cycle);

        [$start, $end] = $this->resolveDates($months);

        return DB::transaction(function () use ($subscription, $newBundle, $start, $end, $months) {

            $subscription->update([
                'bundle_id' => $newBundle->id,
                'bundle_name' => $newBundle->name_en,
                'start_date' => $start,
                'end_date' => $end,
                'billing_period' => $months,
                'status' => 'active',
                'is_active' => true,
            ]);

            $this->createCycle(
                $subscription,
                $newBundle->id,
                'upgrade',
                $start,
                $end
            );

            return $subscription->fresh();
        });
    }

    private function ensureSeller(User $user): void
    {
        abort_unless($user->hasRole('seller') && ! $user->isTeamMember(), 403);
    }

    private function createCycle(
        Subscription $subscription,
        int $bundleId,
        string $type,
        Carbon $start,
        Carbon $end
    ): void {
        SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'bundle_id' => $bundleId,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    private function resolveDates(int $duration, bool $days = false): array
    {
        $start = now();

        return [$start, $days ? $start->copy()->addDays($duration) : $start->copy()->addMonthsNoOverflow($duration)];
    }

    public function checkout(
        User $user, Bundle $bundle, string $action, string $paymentMethod,
        string $cycle, float $discount, string $subjectType, string $subjectId,
        string $callback, ?string $gateway = null, ?string $couponCode = null,
        ?string $checkoutKey = null
    ) {
        // Discounts are always recomputed from the catalog and coupon rules.
        return app(SubscriptionCheckoutService::class)->start($user, $bundle, $cycle, $paymentMethod, $gateway, $couponCode, $checkoutKey);
    }

    private function resolveCycleToMonths(string $cycle): int
    {
        return match ($cycle) {
            'monthly' => 1,
            'yearly' => 12,
            default => throw new \Exception("Invalid cycle: {$cycle}"),
        };
    }

    private function validatePaymentAttempts(User $user): void
    {

        $failedAttempts = WalletTransaction::where('seller_id', $user->id)
            ->whereIn('status', ['pending', 'rejected'])
            ->whereDate('created_at', today())
            /* ->whereIn('failure_reason', [
                 'insufficient_balance',
                 'payment_declined',
                 'abandoned'
             ])*/
            ->count();

        if ($failedAttempts >= 5) {
            throw ValidationException::withMessages([
                'payment_method' => 'Too many failed payment attempts. Please try again tomorrow.',
            ]);
        }
    }

    public function processSuccessfulPayment(WalletTransaction $transaction)
    {
        app(SubscriptionCheckoutService::class)->fulfill($transaction);

        return redirect()->route('dashboard');
    }

    public function success($transaction)
    {
        return $this->processSuccessfulPayment($transaction);
    }

    public function fail($transaction)
    {
        app(SubscriptionCheckoutService::class)->fail($transaction, __('Payment failed.'));
        return redirect()->route('admin.subscription.select')->withErrors(['payment' => __('Payment failed.')]);
    }
}
