<?php

namespace App\Console\Commands\Notifications;

use App\Services\Notifications\NotificationScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Time-based platform notifications (billing reminders, invitations, reconnect reminders, unanswered inbox, Google sync). */
class ScanNotifications extends Command
{
    protected $signature = 'notifications:scan {--only= : One scan: billing, payments, invitations, connections, inbox, google}';

    protected $description = 'Emit time-based platform notifications (deduplicated, safe to run repeatedly)';

    private const SCANS = ['billing' => 'billing', 'payments' => 'pendingPayments', 'invitations' => 'invitations',
        'connections' => 'connections', 'inbox' => 'unansweredConversations', 'google' => 'googleSync'];

    public function handle(NotificationScanner $scanner): int
    {
        $only = $this->option('only');
        foreach (self::SCANS as $name => $method) {
            if ($only && $only !== $name) {
                continue;
            }
            try {
                $this->line(sprintf('%-12s %d event(s)', $name, $scanner->{$method}()));
            } catch (\Throwable $e) {
                // One failing scan never stops the others.
                Log::warning('Notification scan failed.', ['scan' => $name, 'error' => $e->getMessage()]);
                $this->error("$name: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
