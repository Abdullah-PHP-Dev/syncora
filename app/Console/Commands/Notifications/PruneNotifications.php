<?php

namespace App\Console\Commands\Notifications;

use App\Models\Notifications\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily retention: removes old notification history and settles deliveries that can no
 * longer be sent. Unread items are kept (they still need the person); outbox rows are
 * removed with their deliveries once older than the retention window.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days= : Retention in days (default config notifications.defaults.retention_days)}';

    protected $description = 'Prune old platform notifications and settle stale deliveries';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('notifications.defaults.retention_days', 180));
        $cutoff = now()->subDays($days);

        // A claim that never finished (worker died mid-send): unknown outcome, never re-sent (R15).
        $stuck = NotificationDelivery::where('status', 'sending')->where('updated_at', '<', now()->subDay())
            ->update(['status' => NotificationDelivery::FAILED, 'failed_at' => now(), 'error' => 'Send outcome unknown (worker stopped).']);
        $stale = NotificationDelivery::where('status', NotificationDelivery::QUEUED)->where('scheduled_for', '<', now()->subDays(2))
            ->update(['status' => NotificationDelivery::FAILED, 'failed_at' => now(), 'error' => 'Not sent within two days.']);

        $items = DB::table('user_notifications')->where('updated_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('read_at')->orWhereNotNull('archived_at'))->delete();
        $digests = DB::table('notification_digest_items')->whereNotNull('sent_at')->where('sent_at', '<', $cutoff)->delete();
        $events = 0;
        DB::table('notification_events')->where('occurred_at', '<', $cutoff)
            ->whereNotExists(fn ($q) => $q->from('user_notifications')->whereColumn('user_notifications.notification_event_id', 'notification_events.id'))
            ->orderBy('id')->select('id')->chunkById(1000, function ($rows) use (&$events) {
                $events += DB::table('notification_events')->whereIn('id', $rows->pluck('id'))->delete();
            });

        $this->info("Settled {$stuck} stuck and {$stale} stale deliveries; removed {$items} notifications, {$digests} digest items, {$events} events.");

        return self::SUCCESS;
    }
}
