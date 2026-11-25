<?php

namespace App\Console\Commands\Notifications;

use App\Jobs\Notifications\SendNotificationDigest;
use App\Models\User;
use App\Services\Notifications\RecipientContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hourly: sends the daily / weekly digest for everyone whose local time is their chosen slot
 * (default 08:00 daily, Sunday 09:00 weekly). Items older than a missed slot go out anyway.
 */
class SendDigests extends Command
{
    protected $signature = 'notifications:digests {--now : Send every pending digest regardless of the time slot}';

    protected $description = 'Send daily and weekly notification digests for people whose local slot is now';

    public function handle(): int
    {
        $queued = 0;
        $pending = DB::table('notification_digest_items')->whereNull('sent_at')
            ->groupBy('user_id', 'cadence')->selectRaw('user_id, cadence, MIN(created_at) as oldest')->get();
        foreach ($pending as $row) {
            $user = User::find($row->user_id);
            if (! $user) {
                continue;
            }
            $settings = $user->notification_settings ?? [];
            $local = now(RecipientContext::timezone($user));
            $due = $this->option('now') || match ($row->cadence) {
                'daily' => (int) $local->format('G') === (int) ($settings['daily_hour'] ?? config('notifications.defaults.daily_digest_hour'))
                    || now()->diffInHours($row->oldest, true) > 26,
                'weekly' => ((int) $local->dayOfWeek === (int) ($settings['weekly_day'] ?? config('notifications.defaults.weekly_digest_day'))
                    && (int) $local->format('G') === (int) config('notifications.defaults.weekly_digest_hour'))
                    || now()->diffInDays($row->oldest, true) > 8,
                default => false,
            };
            if ($due) {
                try {
                    SendNotificationDigest::dispatchSync($user->id, $row->cadence);
                    $queued++;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Digest not sent.', ['user' => $user->id, 'cadence' => $row->cadence, 'error' => $e->getMessage()]);
                }
            }
        }
        $this->info("Sent {$queued} digest(s).");

        return self::SUCCESS;
    }
}
