<?php

namespace App\Services\Notifications\Emitters;

use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\Log;

/**
 * Tells the Socialeaz team about every new self-service signup (support.new_signup).
 *
 * Recipients are plain addresses from config('notifications.staff_alerts.signup'),
 * by default support@socialeaz.com. The email carries the new customer's details so
 * the team can welcome, qualify or review the account without opening the admin.
 */
class SignupNotifications
{
    /** Called once the account, its role and its plan exist (inside or outside a transaction). */
    public static function registered(User $user): void
    {
        try {
            $to = self::recipients();
            if ($to === []) {
                return;
            }
            $subscription = rescue(fn () => $user->subscription()->first(), null, false);
            $business = rescue(fn () => \App\Models\BusinessProfile::where('user_id', $user->id)->value('business_name'), null, false);
            $locale = app()->getLocale();
            // Browser details only when a browser made the request (not for accounts created from the console).
            $request = request()->userAgent() ? request() : null;

            Notify::event('support.new_signup', [
                'subject' => $user,
                'emails' => $to,
                'params' => array_filter([
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'workspace' => $business && $business !== $user->name ? $business : null,
                    'plan' => $subscription?->bundle?->name_en,
                    'status' => $subscription?->status ? ucfirst((string) $subscription->status) : null,
                    'language' => ['en' => 'English', 'ar' => 'العربية (Arabic)'][$locale] ?? $locale,
                    'country' => $request?->header('CF-IPCountry'),
                    'device' => $request ? Support::device() : null,
                    'network' => $request ? Support::network() : null,
                    'account_id' => '#'.$user->id,
                ], fn ($v) => $v !== null && $v !== ''),
                'dates' => array_filter(['when' => now(), 'end' => $subscription?->end_date]),
                'url' => Support::url('admin.profiles.show', ['profile' => $user->id]),
                'dedup' => 'signup:'.$user->id,
            ]);
        } catch (\Throwable $e) {
            // Never block a signup because the team alert couldn't be prepared.
            Log::warning('Signup alert not prepared.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return string[] valid, unique addresses (comma separated in the env value) */
    public static function recipients(): array
    {
        $list = config('notifications.staff_alerts.signup', '');
        $list = is_array($list) ? $list : explode(',', (string) $list);

        return array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $list),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }
}
