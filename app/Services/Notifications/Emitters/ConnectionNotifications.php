<?php

namespace App\Services\Notifications\Emitters;

use App\Models\SocialConnection;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/** CON-01/02/03/05 from status transitions (any writer: the hourly check, OAuth callbacks, the Hub). */
class ConnectionNotifications
{
    public static function statusChanged(SocialConnection $connection, ?string $from, string $dedupSuffix = ''): void
    {
        $platform = Support::platform($connection->platform);
        $account = $connection->provider_account_label ?: $platform;
        $base = ['workspace' => $connection->user_id, 'subject' => $connection, 'url' => Support::url('admin.connections.index').'#'.$connection->platform];
        $day = now()->toDateString();

        switch ($connection->status) {
            case SocialConnection::EXPIRING:
                $days = max(1, (int) ceil(now()->diffInHours($connection->expires_at ?? now()->addDay(), false) / 24));
                Notify::event('connections.expiring', $base + [
                    'params' => ['platform' => $platform, 'account' => $account, 'days' => $days, 'choice' => $days],
                    'dedup' => 'expiring:'.optional($connection->expires_at)->toDateString().$dedupSuffix,
                ]);
                break;
            case SocialConnection::NEEDS_REAUTH:
            case SocialConnection::EXPIRED:
            case SocialConnection::REVOKED:
                if ($connection->last_error === SocialConnection::DISCONNECTED_BY_USER) {
                    $actor = Support::actorId();
                    if ($actor) {
                        Notify::event('connections.disconnected', $base + [
                            'actor' => $actor, 'params' => ['platform' => $platform, 'actor' => \App\Models\User::whereKey($actor)->value('name') ?? '—'],
                            'dedup' => 'disconnected:'.$day,
                        ]);
                    }
                    break;
                }
                Notify::event('connections.reconnect_required', $base + [
                    'params' => ['platform' => $platform, 'account' => $account, 'scheduled' => self::scheduledPosts($connection)],
                    'dedup' => $connection->status.':'.$day.$dedupSuffix,
                ]);
                break;
            case SocialConnection::ERROR:
                Notify::event('connections.check_error', $base + ['params' => ['platform' => $platform], 'dedup' => 'error:'.$day]);
                break;
        }
    }

    /** Verified impact only: posts waiting to go out on this connection's accounts. */
    public static function scheduledPosts(SocialConnection $connection): int
    {
        return (int) rescue(fn () => DB::table('posts')->whereIn('status', ['pending', 'scheduled'])
            ->whereIn('social_account_id', DB::table('social_accounts')->where('social_connection_id', $connection->id)->select('id'))->count(), 0, false);
    }
}
