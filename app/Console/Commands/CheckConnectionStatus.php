<?php

namespace App\Console\Commands;

use App\Models\SocialConnection;
use App\Services\Connections\ConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps social_connections.status honest (docs/connection-hub-design.md §7)
 * - the Hub and the notification bell only ever read status, they never
 * call providers. Runs hourly (bootstrap/app.php):
 *
 *  1. Expiry pass, every connection, no provider calls: time-based status
 *     (active / expiring within 7 days / needs reauth - see
 *     SocialConnection::statusFor() for refresh-token rules). Problems
 *     a provider reported (needs_reauth, error) are never cleared by time.
 *  2. Validation pass: asks the provider (driver->validate()) about
 *     connections not checked for 24h, oldest first, capped per run - so a
 *     day's checks spread over the hourly runs. Learns revocations and
 *     scope changes.
 *
 * A transition into needs_reauth / revoked marks the linked assets
 * is_token_valid = false, which existing module pages already surface as
 * "reconnect". Never throws; one failing connection doesn't stop the run.
 */
class CheckConnectionStatus extends Command
{
    protected $signature = 'connections:check-status
                            {--dry-run : Report status changes without writing or calling providers}
                            {--limit=100 : Max provider validations per run}';

    protected $description = 'Update connection statuses from token expiry and provider validation';

    private array $changes = [];

    public function handle(ConnectionService $connections): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // 1. Expiry pass
        SocialConnection::where('status', '!=', SocialConnection::REVOKED)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($dryRun) {
                foreach ($chunk as $connection) {
                    $next = $this->timeStatus($connection);
                    if ($next !== $connection->status) {
                        $this->transition($connection, $next, 'expiry', $dryRun);
                    }
                }
            });

        // 2. Validation pass
        $validated = 0;

        if (! $dryRun) {
            SocialConnection::where('status', '!=', SocialConnection::REVOKED)
                ->whereNotNull('access_token')
                ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subDay()))
                ->orderByRaw('last_checked_at IS NOT NULL, last_checked_at')
                ->limit(max(0, (int) $this->option('limit')))
                ->get()
                ->each(function (SocialConnection $connection) use ($connections, &$validated) {
                    $before = $connection->status;

                    try {
                        $connections->validate($connection);
                    } catch (\Throwable $e) {
                        Log::warning('Connection validation failed.', ['connection_id' => $connection->id, 'error' => $e->getMessage()]);
                        $connection->forceFill(['status' => SocialConnection::ERROR, 'last_error' => $e->getMessage(), 'last_checked_at' => now()])->save();
                    }

                    $validated++;

                    if ($connection->status !== $before) {
                        $this->recordChange($connection, $before, $connection->status, 'provider');
                        $this->invalidateAssetsIfBroken($connection);
                    }
                });
        }

        $this->line($dryRun ? '<comment>DRY RUN - nothing was written, no provider was called.</comment>' : "<info>Done.</info> Validated {$validated} connection(s) with their provider.");

        if ($this->changes) {
            $this->table(['connection', 'user', 'step', 'from', 'to', 'by'], $this->changes);
        } else {
            $this->line('No status changes.');
        }

        return self::SUCCESS;
    }

    /** Status from the token's expiry alone; provider-reported problems stick. */
    private function timeStatus(SocialConnection $connection): string
    {
        $byTime = $connection->timeStatus();

        if (in_array($connection->status, [SocialConnection::NEEDS_REAUTH, SocialConnection::ERROR], true)) {
            return $byTime === SocialConnection::NEEDS_REAUTH ? SocialConnection::NEEDS_REAUTH : $connection->status;
        }

        return $byTime;
    }

    private function transition(SocialConnection $connection, string $next, string $by, bool $dryRun): void
    {
        $this->recordChange($connection, $connection->status, $next, $by);

        if ($dryRun) {
            return;
        }

        $connection->forceFill(['status' => $next])->save();
        $this->invalidateAssetsIfBroken($connection);
    }

    private function recordChange(SocialConnection $connection, string $from, string $to, string $by): void
    {
        $this->changes[] = ["#{$connection->id}", $connection->user_id, $connection->step, $from, $to, $by];
    }

    private function invalidateAssetsIfBroken(SocialConnection $connection): void
    {
        if (in_array($connection->status, [SocialConnection::NEEDS_REAUTH, SocialConnection::REVOKED], true)) {
            DB::table('social_accounts')->where('social_connection_id', $connection->id)->update(['is_token_valid' => false]);
        }
    }
}
