<?php

namespace App\Console\Commands;

use App\Models\AdReportSchedule;
use App\Services\AdServices\FacebookReportService;
use Illuminate\Console\Command;

/** Emails every active ad performance report that is due (scheduled every 15 minutes). */
class SendAdReports extends Command
{
    protected $signature = 'ads:send-reports';

    protected $description = 'Send scheduled ad performance reports that are due';

    public function handle(FacebookReportService $reports): int
    {
        AdReportSchedule::where('status', 'active')->whereNotNull('next_run_at')->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')->limit(200)->get()
            ->each(function (AdReportSchedule $schedule) use ($reports) {
                $result = $reports->deliver($schedule);
                // Move on to the next slot either way; a failure is kept in last_error for the page.
                $schedule->forceFill(['next_run_at' => $schedule->nextRunAfter(now())])->save();
                $this->line(sprintf('#%d %s', $schedule->id, $result['success'] ? 'sent' : 'failed: ' . ($result['error'] ?? '')));
            });

        return self::SUCCESS;
    }
}
