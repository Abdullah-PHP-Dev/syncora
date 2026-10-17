<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageUsageService
{
    public function __construct(private PackageAccessService $access) {}

    public function locked(User $user, Closure $operation): mixed
    {
        return DB::transaction(function () use ($user, $operation) {
            // One stable owner lock serializes capacities and reservations across renewals.
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->is_active, 403, __('package_access.expired'));
            $subscription = $this->access->subscription($user);
            abort_unless($this->access->active($subscription), 403, __('package_access.expired'));

            return $operation($subscription);
        }, 3);
    }

    public function periodStart(Subscription $subscription, string $period): string
    {
        $anchor = ($subscription->start_date ?? $subscription->created_at)->copy()->startOfDay();
        if ($period !== 'monthly' || $subscription->status === 'trial') {
            return $anchor->toDateString();
        }
        $months = max(0, (now()->year - $anchor->year) * 12 + now()->month - $anchor->month);
        $start = $anchor->copy()->addMonthsNoOverflow($months);
        if ($start->isFuture() && $months > 0) {
            $start = $anchor->copy()->addMonthsNoOverflow($months - 1);
        }

        return $start->toDateString();
    }

    public function limit(Subscription $subscription, string $key): int
    {
        return (int) ($subscription->bundle?->entitlement_settings[$key] ?? 0);
    }

    public function used(Subscription $subscription, string $key): int
    {
        $period = data_get($subscription->bundle?->meta, 'allowance_period', 'monthly');

        return (int) DB::table('package_usage')->where('user_id', $subscription->user_id)
            ->where('feature_key', $key)->where('period_start', $this->periodStart($subscription, $period))->sum('amount');
    }

    public function check(Subscription $subscription, string $key, int $current, int $amount = 1, int $multiplier = 1): void
    {
        $limit = $this->limit($subscription, $key);
        if ($limit !== -1 && $current + $amount > $limit * $multiplier) {
            throw ValidationException::withMessages(['package' => __('package_access.limit', ['feature' => config("package_reference.features.$key.name_".app()->getLocale(), $key)])]);
        }
    }

    public function reserve(User $user, string $key, int $amount = 1, ?string $event = null): string
    {
        if ($amount < 1 || ! in_array($key, ['ai_credits', 'hashtag_generation', 'scheduled_published_posts'], true)) {
            throw new \InvalidArgumentException('Unsupported package usage reservation.');
        }
        $event ??= (string) Str::uuid();

        return $this->locked($user, function ($subscription) use ($user, $key, $amount, $event) {
            $exists = DB::table('package_usage')->where(['user_id' => $user->id, 'feature_key' => $key, 'event_key' => $event])->exists();
            if (! $exists) {
                $this->check($subscription, $key, $this->used($subscription, $key), $amount);
                $this->record($subscription, $key, $event, $amount);
            }

            return $event;
        });
    }

    public function release(User $user, string $key, string $event): void
    {
        DB::table('package_usage')->where(['user_id' => $user->id, 'feature_key' => $key, 'event_key' => $event])->delete();
    }

    private function record(Subscription $subscription, string $key, string $event, int $amount): void
    {
        DB::table('package_usage')->insert([
            'user_id' => $subscription->user_id, 'feature_key' => $key, 'event_key' => $event,
            'period_start' => $this->periodStart($subscription, data_get($subscription->bundle?->meta, 'allowance_period', 'monthly')),
            'amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Persist capacity changes and post allowances in the same transaction as the resource. */
    public function persist(Model $model, Closure $save): mixed
    {
        $user = User::find($model->user_id);
        // Platform records/import fixtures without a seller owner are not subscriptions.
        if (! $user || ! $user->hasRole('seller') || $user->isTeamMember()) {
            return $save();
        }

        return $this->locked($user, function ($subscription) use ($model, $save, $user) {
            $previous = $model->exists ? $model->newQuery()->whereKey($model->id)->first() : null;
            $postUsage = false;
            if ($model instanceof SocialAccount) {
                foreach (['has_posting_permission' => ['publishing_accounts', 'publishing'], 'has_ads_permission' => ['advertising_accounts', 'advertising']] as $flag => [$key, $module]) {
                    if ($model->$flag && ! $previous?->$flag) {
                        $this->access->authorize($user, $module);
                        $this->check($subscription, $key, SocialAccount::where('user_id', $user->id)->where($flag, true)->count());
                    }
                }
                // Unified OAuth may grant messaging alongside publishing. Store provider
                // grants, while module middleware still prevents sending in preview mode.
                if ($model->has_messaging_permission && ! $previous?->has_messaging_permission
                    && ! $model->has_posting_permission && ! $model->has_ads_permission) {
                    $this->access->authorize($user, 'inbox');
                }
            } elseif ($model instanceof MediaAsset) {
                $this->access->authorize($user, 'media');
                $current = (int) MediaAsset::withTrashed()->where('user_id', $user->id)->selectRaw('COALESCE(SUM(size), 0) as total')->value('total');
                $delta = max(0, $model->size - ($previous?->size ?? 0));
                $this->check($subscription, 'media_storage_gb', $current, $delta, 1024 ** 3);
            } elseif ($model instanceof PostCategory) {
                $this->access->authorize($user, 'publishing');
                $this->check($subscription, 'content_categories', PostCategory::where('user_id', $user->id)->count());
            } elseif ($model instanceof Post) {
                $this->access->authorize($user, 'publishing');
                $postUsage = ! $model->exists || ! DB::table('package_usage')->where(['user_id' => $user->id, 'feature_key' => 'scheduled_published_posts', 'event_key' => 'post:'.$model->id])->exists();
                if ($postUsage) {
                    $this->check($subscription, 'scheduled_published_posts', $this->used($subscription, 'scheduled_published_posts'));
                }
            }
            $result = $save();
            if ($result !== false && $postUsage) {
                $this->record($subscription, 'scheduled_published_posts', 'post:'.$model->id, 1);
            }

            return $result;
        });
    }
}
