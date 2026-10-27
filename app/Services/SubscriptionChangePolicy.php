<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubscriptionChangePolicy
{
    public function current(User $user): ?Subscription
    {
        return Subscription::with('bundle')->where('user_id', $user->id)->latest('id')->first();
    }

    public function canSelect(?Subscription $current, Bundle $target): bool
    {
        if (! $current || ! $current->is_active || $current->status !== 'active' ||
            ($current->end_date && $current->end_date->copy()->endOfDay()->lt(now())) ||
            (int) $current->bundle_id === (int) $target->id || ! $current->bundle || $current->bundle->is_free) {
            return true;
        }

        $currentOrder = (int) $current->bundle->sort_order;
        $targetOrder = (int) $target->sort_order;

        return $currentOrder !== $targetOrder
            ? $targetOrder > $currentOrder
            : CouponService::minor($target->price) > CouponService::minor($current->bundle->price);
    }

    public function assertCanSelect(User $user, Bundle $target): void
    {
        if (! $this->canSelect($this->current($user), $target)) {
            throw ValidationException::withMessages(['bundle_id' => __('subscriptions.downgrade_blocked')]);
        }
    }
}
