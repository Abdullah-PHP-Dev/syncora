<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

class PackageAccessService
{
    public static function initialSettings(Bundle $bundle): array
    {
        $settings = [];
        foreach (config('package_access.modules') as $module => $definition) {
            $value = $bundle->entitlement_settings[$definition['source']] ?? null;
            if ($definition['numeric'] ?? false) {
                $settings[$module] = ['mode' => is_numeric($value) && ((int) $value === -1 || (int) $value > 0) ? 'included' : 'disabled', 'tier' => 'standard'];
            } elseif (is_bool($value)) {
                $settings[$module] = ['mode' => $value ? 'included' : 'disabled', 'tier' => 'standard'];
            } else {
                $settings[$module] = config('package_access.legacy_tiers')[$value ?? ''] ?? ['mode' => 'disabled', 'tier' => 'none'];
            }
        }

        return $settings;
    }

    public function subscription(User $user): ?Subscription
    {
        return $user->subscription()->latest('subscriptions.id')->first();
    }

    public function active(?Subscription $subscription): bool
    {
        return $subscription && $subscription->is_active
            && in_array($subscription->status, ['active', 'trial'], true)
            && (! $subscription->start_date || $subscription->start_date->copy()->startOfDay()->lte(now()))
            && (! $subscription->end_date || $subscription->end_date->copy()->endOfDay()->gte(now()));
    }

    public function allows(User $user, string $module, bool $write = false): bool
    {
        if ($user->is_active === false || ! $user->hasRole('seller') || $user->isTeamMember() || ! array_key_exists($module, config('package_access.modules'))) {
            return false;
        }
        $subscription = $this->subscription($user);
        if (! $this->active($subscription) || ! $subscription->bundle) {
            return false;
        }
        $definition = config("package_access.modules.$module");
        if ($definition['numeric'] ?? false) {
            $capacity = $subscription->bundle->entitlement_settings[$definition['source']] ?? 0;
            if (! is_numeric($capacity) || ((int) $capacity !== -1 && (int) $capacity <= 0)) {
                return false;
            }
        }
        // A retired package still serves existing subscribers. Missing settings fail closed.
        $mode = $subscription->bundle->access_settings[$module]['mode'] ?? 'disabled';

        return $mode === 'included' || ($mode === 'preview' && ! $write);
    }

    public function authorize(User $user, string $module, bool $write = true): void
    {
        abort_unless($this->allows($user, $module, $write), 403, __('package_access.denied', ['module' => __(config("package_access.modules.$module.label", $module))]));
    }

    public function modulesForRoute(string $name): array
    {
        $modules = [];
        foreach (config('package_access.routes') as $module => $patterns) {
            if (Str::is($patterns, $name)) {
                $modules[] = $module;
            }
        }

        return $modules;
    }
}
