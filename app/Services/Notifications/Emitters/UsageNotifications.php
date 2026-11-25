<?php

namespace App\Services\Notifications\Emitters;

use App\Models\Subscription;
use App\Services\Notifications\Notify;

/**
 * USG-01..04. Thresholds fire once per allowance period (dedup on the period), capacity
 * limits at most once a week per feature. Recorded on the paying owner's account.
 */
class UsageNotifications
{
    public const QUOTAS = ['ai_credits', 'hashtag_generation', 'scheduled_published_posts'];

    /** Called after an allowance was used: crossing 80% or reaching 100% tells the owner. */
    public static function consumed(Subscription $subscription, string $key, int $before, int $after, int $limit): void
    {
        if ($limit <= 0 || ! in_array($key, self::QUOTAS, true)) {
            return;
        }
        $period = app(\App\Services\PackageUsageService::class)->periodStart($subscription, data_get($subscription->bundle?->meta, 'allowance_period', 'monthly'));
        $options = ['workspace' => $subscription->user_id, 'url' => Support::url('admin.subscription.usage', [], 'admin.subscription.plans'),
            'params' => ['feature_key' => $key, 'used' => $after, 'limit' => $limit, 'percent' => 80]];
        if ($after >= $limit && $before < $limit) {
            Notify::event('usage.limit_reached', $options + ['dedup' => "$key:$period"]);
        } elseif ($after >= 0.8 * $limit && $before < 0.8 * $limit) {
            Notify::event('usage.threshold_reached', $options + ['dedup' => "$key:$period"]);
        }
    }

    /** A check refused an action. Raised after the surrounding transaction (it is rolled back). */
    public static function blocked(Subscription $subscription, string $key, int $limit): void
    {
        $quota = in_array($key, self::QUOTAS, true);
        $period = $quota ? app(\App\Services\PackageUsageService::class)->periodStart($subscription, data_get($subscription->bundle?->meta, 'allowance_period', 'monthly')) : now()->format('o-W');
        Notify::later($quota ? 'usage.limit_reached' : 'usage.plan_limit_hit', [
            'workspace' => $subscription->user_id, 'url' => Support::url('admin.subscription.plans'),
            'params' => ['feature_key' => $key, 'limit' => $limit], 'dedup' => "$key:$period",
        ]);
    }
}
