<?php

namespace App\Console\Commands\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Services\EmailMarketingServices\SendGridCampaignService;
use Illuminate\Console\Command;

/**
 * Runs every minute (see bootstrap/app.php). Under the old Mailgun
 * integration this command was the thing that actually fired a scheduled
 * campaign once its time arrived - under SendGrid, EmailMarketingService::
 * dispatchCampaign() already calls SendGrid's own schedule endpoint the
 * moment "Schedule" is clicked, and SendGrid itself holds and fires the
 * send at that time. This command is now a reconciliation safety net: it
 * confirms a locally 'scheduled' campaign whose scheduled_at has passed
 * actually got marked 'triggered' on SendGrid's side, in case the Event
 * Webhook delivery for it was ever missed - the Event Webhook stays the
 * primary source of truth for this, per the spec's own "do not rely
 * exclusively on polling" guidance.
 */
class SendScheduledEmailCampaigns extends Command
{
    protected $signature = 'email-marketing:send-scheduled';

    protected $description = 'Reconcile SendGrid Single Send status for campaigns scheduled to have sent by now';

    public function handle(SendGridCampaignService $campaigns): int
    {
        $due = EmailCampaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $result = $campaigns->checkStatus($campaign);

            if (!($result['success'] ?? false)) {
                $this->error("Campaign #{$campaign->id}: " . ($result['error'] ?? 'status check failed'));
                continue;
            }

            if ($result['status'] === 'triggered') {
                $campaign->update(['status' => 'sent', 'sent_at' => $campaign->sent_at ?? now()]);
                $this->info("Campaign #{$campaign->id} confirmed sent (reconciled - no webhook had marked it yet).");
            }
        }

        return self::SUCCESS;
    }
}
