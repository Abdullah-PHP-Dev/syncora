<?php

namespace App\Support;

use App\Models\Admin\AdminSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the adminSetting()/set_adminSetting()/deleteAdminSetting() global
 * helpers (app/Helpers/Helper.php). Previously each adminSetting() call ran
 * its own `AdminSetting::where('key', $key)->first()` query - on any request
 * that reads more than one setting (or the same one more than once) that's
 * a repeated, avoidable query. Settings change rarely and are read
 * constantly, so the whole table is cached as one blob under a single
 * 'admin_settings' cache key and only refetched when a write invalidates it.
 */
class Settings
{
    private const CACHE_KEY = 'admin_settings';

    /**
     * Per-request memo on top of the cache store itself - so N calls to
     * get() in one request hit the cache backend (here: the `database`
     * driver's own table) at most once, not N times.
     */
    private static ?array $requestCache = null;

    /**
     * All settings as [key => raw stored value]. Values are exactly as
     * stored (json_decode is NOT applied here - see get()).
     */
    public static function all(): array
    {
        if (self::$requestCache !== null) {
            return self::$requestCache;
        }

        // The admin_settings table can genuinely not exist yet: Artisan
        // eagerly constructs every discovered console command (not just
        // the one being run), and some commands read settings from their
        // constructor - so `php artisan migrate:fresh` on a brand-new DB
        // hits this before its own migration creates the table. That
        // "missing" state must never be written into the persistent
        // cache: caching [] forever here would mean every future request
        // keeps reading a stale empty cache even after migrations run,
        // until something happens to call set()/delete() to flush it. So
        // this checks the table *before* touching Cache::rememberForever()
        // and only memoizes the empty result for the current request.
        if (! Schema::hasTable('admin_settings')) {
            return self::$requestCache = [];
        }

        return self::$requestCache = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => AdminSetting::pluck('value', 'key')->all()
        );
    }

    /**
     * A single setting's value, decoded if it looks like the JSON
     * array/object set() produces.
     *
     * set() below only ever runs json_encode() on arrays/objects - every
     * scalar (string, "true"/"false", a bare number) is stored as the raw
     * value, unquoted. Unconditionally json_decode()ing on the way back
     * out therefore silently mis-parses any scalar that happens to also be
     * valid JSON on its own: a Slack OAuth client_id like
     * "11910619340784.11910624460400" is syntactically a JSON number, so
     * it decoded to a float and lost everything past ~17 significant
     * digits ("11910619340784.12"), which is exactly why Slack's authorize
     * screen said "Invalid client_id parameter" even though the stored
     * value was correct. Only attempting the decode when the value
     * actually looks like the JSON array/object set() produces keeps that
     * case working while leaving every plain scalar - numeric-looking or
     * not - untouched.
     */
    public static function get(string $key, $default = null)
    {
        $all = self::all();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        $value = $all[$key];
        $trimmed = ltrim((string) $value);

        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Create or update a setting, then invalidate the cache so the next
     * read (including later in this same request) sees the new value.
     */
    public static function set(string $key, $value): bool
    {
        AdminSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) || is_object($value)
                    ? json_encode($value)
                    : $value,
            ]
        );

        self::flush();

        return true;
    }

    /**
     * Delete a setting, then invalidate the cache.
     */
    public static function delete(string $key): bool
    {
        $deleted = (bool) AdminSetting::where('key', $key)->delete();

        self::flush();

        return $deleted;
    }

    private static function flush(): void
    {
        self::$requestCache = null;
        Cache::forget(self::CACHE_KEY);
    }
}
