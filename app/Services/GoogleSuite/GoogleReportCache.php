<?php

namespace App\Services\GoogleSuite;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for Google report responses, namespaced per workspace
 * with a version number so one flush drops all of a workspace's entries
 * (works on every cache store, no tags needed). Reports are summaries
 * Google already holds; nothing is kept beyond TTL.
 */
class GoogleReportCache
{
    public const TTL_MINUTES = 30;

    public static function remember(int $userId, string $key, bool $fresh, callable $callback): array
    {
        $full = self::key($userId, $key);

        if ($fresh) {
            Cache::forget($full);
        }

        return Cache::remember($full, now()->addMinutes(self::TTL_MINUTES), $callback);
    }

    public static function flush(int $userId): void
    {
        Cache::forever(self::versionKey($userId), self::version($userId) + 1);
    }

    private static function key(int $userId, string $key): string
    {
        return 'google_suite:' . $userId . ':' . self::version($userId) . ':' . sha1($key);
    }

    private static function version(int $userId): int
    {
        return (int) Cache::get(self::versionKey($userId), 1);
    }

    private static function versionKey(int $userId): string
    {
        return 'google_suite:' . $userId . ':version';
    }
}
