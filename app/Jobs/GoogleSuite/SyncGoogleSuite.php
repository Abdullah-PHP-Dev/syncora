<?php

namespace App\Jobs\GoogleSuite;

use App\Services\GoogleSuite\GoogleSuiteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Warms one workspace's Google Suite reports in the background so the
 * dashboards open from cache, and records each property's health.
 * Failures are recorded by GoogleSuiteService, never thrown.
 */
class SyncGoogleSuite implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct(public int $userId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(GoogleSuiteService $suite): void
    {
        $suite->sync($this->userId);
    }
}
