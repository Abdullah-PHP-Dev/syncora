<?php

namespace App\Support\Google;

/**
 * The Google Suite consent (step google.analytics): read-only reporting
 * scopes only, plus openid/email so the page can show which Google account
 * is linked. Separate from the YouTube/Ads consent so neither widens the other.
 */
class GoogleSuiteScopes
{
    public const ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';
    public const SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /** Scope each service needs, keyed by GoogleProperty service. */
    public const BY_SERVICE = [
        'ga4' => self::ANALYTICS,
        'search_console' => self::SEARCH_CONSOLE,
    ];

    public static function requested(): array
    {
        return ['openid', 'email', self::ANALYTICS, self::SEARCH_CONSOLE];
    }

    /** Reporting scopes the user left unticked on the consent screen. */
    public static function missing(?array $granted): array
    {
        return array_values(array_diff(array_values(self::BY_SERVICE), $granted ?? []));
    }

    public static function grants(?array $granted, string $service): bool
    {
        return in_array(self::BY_SERVICE[$service] ?? '', $granted ?? [], true);
    }
}
