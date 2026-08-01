<?php

namespace App\Console\Commands\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Console\Command;

/**
 * Runs every minute (see bootstrap/app.php) and dispatches any campaign
 * whose scheduled_at has arrived - the same "poll every minute" approach
 * PollXDirectMessages uses for X, except here it's polling this app's own
 * database rather than an external API.
 */
class SendScheduledEmailCampaigns extends Command
{
    protected $signature = 'email-marketing:send-scheduled';

    protected $description = 'Dispatch email campaigns whose scheduled send time has arrived';

    public function handle(EmailMarketingService $service): int
    {
        $due = EmailCampaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $result = $service->dispatchCampaign($campaign);

            if (!($result['success'] ?? false)) {
                $campaign->update(['status' => 'failed', 'error_message' => $result['error'] ?? 'Failed to dispatch campaign.']);
                $this->error("Campaign #{$campaign->id}: " . ($result['error'] ?? 'failed to dispatch'));
                continue;
            }

            $this->info("Campaign #{$campaign->id} dispatched to {$campaign->fresh()->total_recipients} recipients.");
        }

        return self::SUCCESS;
    }
}
