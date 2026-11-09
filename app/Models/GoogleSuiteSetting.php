<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Workspace-level reporting preferences for the Google Suite. */
class GoogleSuiteSetting extends Model
{
    public const RANGES = ['today', '7d', '28d', '30d', '90d'];
    public const WIDGETS = ['services', 'insights', 'performance', 'attention', 'activity', 'acquisition', 'queries', 'landing_pages', 'audience'];
    public const SYNC_FREQUENCIES = ['hourly', 'every_6_hours', 'daily', 'manual'];

    public const DEFAULTS = [
        'default_range' => '28d',
        'compare_previous' => true,
        'widgets' => self::WIDGETS,
        'sync_frequency' => 'hourly',
    ];

    /**
     * Preference keys that hold secrets: stored encrypted and never sent to
     * the browser (see visible()). The workspace's own PageSpeed Insights
     * API key lives here - it is the seller's credential, not SocialEaz's.
     */
    public const PAGESPEED_KEY = 'pagespeed_api_key';
    public const SECRETS = [self::PAGESPEED_KEY];

    /** Google API keys: "AIza" followed by 35 URL-safe characters. */
    public const API_KEY_PATTERN = '/^AIza[0-9A-Za-z_\-]{35}$/';

    protected $fillable = ['user_id', 'preferences'];

    protected $casts = ['preferences' => 'array'];

    public static function for(int $userId): array
    {
        $stored = static::where('user_id', $userId)->value('preferences');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;

        return array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public static function store(int $userId, array $preferences): array
    {
        $merged = array_merge(self::for($userId), $preferences);
        static::updateOrCreate(['user_id' => $userId], ['preferences' => $merged]);

        return $merged;
    }

    /** Preferences safe to send to the browser: no linked-account identity, no secrets. */
    public static function visible(array $preferences): array
    {
        return collect($preferences)->except(['account', ...self::SECRETS])->all();
    }

    /** The workspace's decrypted PageSpeed key, or null (none saved, or unreadable after an APP_KEY change). */
    public static function pageSpeedKey(int $userId): ?string
    {
        $encrypted = self::for($userId)[self::PAGESPEED_KEY] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException $e) {
            return null;
        }
    }

    public static function setPageSpeedKey(int $userId, ?string $key): void
    {
        $preferences = self::for($userId);
        if ($key === null) {
            unset($preferences[self::PAGESPEED_KEY]);
        } else {
            $preferences[self::PAGESPEED_KEY] = Crypt::encryptString($key);
        }
        static::updateOrCreate(['user_id' => $userId], ['preferences' => $preferences]);
    }

    /** What the page may show about the saved key: whether there is one, and its last 4 characters. */
    public static function pageSpeedKeyStatus(int $userId): array
    {
        $key = self::pageSpeedKey($userId);

        return ['saved' => $key !== null, 'last4' => $key !== null ? substr($key, -4) : null];
    }
}
