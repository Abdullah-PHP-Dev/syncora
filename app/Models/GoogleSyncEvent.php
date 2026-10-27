<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the Google Suite activity / health log. Never holds API bodies. */
class GoogleSyncEvent extends Model
{
    public const UPDATED_AT = null;

    /** Rows kept per workspace; older ones are pruned on write. */
    public const KEEP = 100;

    protected $fillable = ['user_id', 'service', 'event', 'status', 'message', 'context'];

    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];

    public static function log(int $userId, string $service, string $event, string $status = 'ok', ?string $message = null, array $context = []): void
    {
        static::create(compact('service', 'event', 'status', 'message') + ['user_id' => $userId, 'context' => $context ?: null]);

        $cutoff = static::where('user_id', $userId)->orderByDesc('id')->skip(self::KEEP)->value('id');
        if ($cutoff) {
            static::where('user_id', $userId)->where('id', '<=', $cutoff)->delete();
        }
    }
}
