<?php

namespace App\Console\Commands;

use App\Jobs\GoogleSuite\SyncGoogleSuite;
use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Models\SocialConnection;
use App\Services\Connections\Drivers\GoogleDriver;
use Illuminate\Console\Command;

/**
 * Hourly (bootstrap/app.php): queues a background sync for each workspace
 * with a selected GA4 property or Search Console site, honouring its
 * sync_frequency preference. Workspaces whose consent is broken are
 * skipped - the health screen already asks them to reconnect.
 */
class SyncGoogleSuites extends Command
{
    protected $signature = 'google-suite:sync {--user= : Only this workspace owner}';

    protected $description = 'Queue Google Suite (GA4 + Search Console) background syncs';

    private const HOURS = ['hourly' => 1, 'every_6_hours' => 6, 'daily' => 24];

    public function handle(): int
    {
        $queued = 0;

        GoogleProperty::where('is_selected', true)
            ->when($this->option('user'), fn ($q, $user) => $q->where('user_id', $user))
            ->get(['user_id', 'last_synced_at'])
            ->groupBy('user_id')
            ->each(function ($properties, $userId) use (&$queued) {
                $hours = self::HOURS[GoogleSuiteSetting::for((int) $userId)['sync_frequency']] ?? null;
                $last = $properties->pluck('last_synced_at')->filter()->min();
                $usable = SocialConnection::where(['user_id' => $userId, 'platform' => 'google', 'step' => GoogleDriver::ANALYTICS])
                    ->whereIn('status', SocialConnection::USABLE)->exists();

                if (! $hours || ! $usable || ($last && $last->gt(now()->subHours($hours)->addMinutes(5)))) {
                    return;
                }

                SyncGoogleSuite::dispatch((int) $userId);
                $queued++;
            });

        $this->info("Queued {$queued} Google Suite sync(s).");

        return self::SUCCESS;
    }
}
