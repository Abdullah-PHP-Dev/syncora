<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\Workspace;

/** The reader's language and timezone - never the actor's (audit A4). */
class RecipientContext
{
    public const LOCALES = ['en', 'ar'];

    public static function locale(?User $user, ?int $workspaceId = null): string
    {
        $candidates = [$user?->locale, $user?->ui_locale, self::workspace($workspaceId)?->content_language, 'en'];
        foreach ($candidates as $locale) {
            $locale = strtolower(substr((string) $locale, 0, 2));
            if (in_array($locale, self::LOCALES, true)) {
                return $locale;
            }
        }

        return 'en';
    }

    public static function timezone(?User $user, ?int $workspaceId = null): string
    {
        foreach ([$user?->timezone, self::workspace($workspaceId)?->timezone, config('notifications.defaults.timezone')] as $tz) {
            if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        }

        return 'UTC';
    }

    private static function workspace(?int $accountId): ?Workspace
    {
        static $memo = [];
        if (! $accountId) {
            return null;
        }

        return $memo[$accountId] ??= rescue(fn () => Workspace::where('account_id', $accountId)->first(), null, false);
    }

    /** In quiet hours right now? Returns when they end, or null. */
    public static function quietUntil(?User $user, ?int $workspaceId = null): ?\Carbon\CarbonImmutable
    {
        $quiet = $user?->notification_settings['quiet_hours'] ?? null;
        if (! $quiet || empty($quiet['enabled']) || empty($quiet['start']) || empty($quiet['end'])) {
            return null;
        }
        $now = \Carbon\CarbonImmutable::now(self::timezone($user, $workspaceId));
        [$sh, $sm] = array_map('intval', explode(':', $quiet['start']));
        [$eh, $em] = array_map('intval', explode(':', $quiet['end']));
        $start = $now->setTime($sh, $sm);
        $end = $now->setTime($eh, $em);
        if ($start->lte($end)) {
            return $now->gte($start) && $now->lt($end) ? $end : null;
        }
        // Overnight window (e.g. 22:00 - 08:00).
        if ($now->gte($start)) {
            return $end->addDay();
        }

        return $now->lt($end) ? $end : null;
    }
}
