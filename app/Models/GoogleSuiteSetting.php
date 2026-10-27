<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
