<?php

namespace App\Console\Commands\Notifications;

use App\Jobs\Notifications\SendNotificationEmail;
use App\Models\Notifications\NotificationDelivery;
use Illuminate\Console\Command;

/**
 * Every minute: sends notification emails whose time has come without a queue worker.
 * These were held back (quiet hours, grouping window) or failed and are waiting for a retry.
 * Emails due at the time of the event are sent immediately and never wait for this.
 */
class SendDueNotifications extends Command
{
    protected $signature = 'notifications:send-due {--limit=200}';

    protected $description = 'Send held-back and retryable notification emails that are now due';

    public function handle(): int
    {
        $ids = NotificationDelivery::query()
            ->where('channel', 'email')->where('status', NotificationDelivery::QUEUED)
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
            ->where('created_at', '<=', now()->subSeconds(30)) // leave brand-new rows to the immediate send
            ->orderBy('id')->limit((int) $this->option('limit'))->pluck('id');

        foreach ($ids as $id) {
            SendNotificationEmail::sendNow($id); // claims the row first, so a double run never sends twice
        }
        $this->info("Processed {$ids->count()} due email(s).");

        return self::SUCCESS;
    }
}
