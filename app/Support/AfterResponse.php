<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Runs work after the HTTP response has been sent (PHP-FPM finishes the request first), so
 * slow work such as SMTP never makes a page wait or time out. No queue worker needed.
 * In console commands and tests it runs immediately. Errors are logged, never thrown.
 */
class AfterResponse
{
    public static function run(callable $work, string $what = 'after-response task'): void
    {
        $safe = function () use ($work, $what) {
            try {
                $work();
            } catch (\Throwable $e) {
                Log::error(ucfirst($what).' failed.', ['error' => $e->getMessage()]);
            }
        };
        if (app()->runningInConsole()) {
            $safe();

            return;
        }
        app()->terminating($safe);
    }
}
