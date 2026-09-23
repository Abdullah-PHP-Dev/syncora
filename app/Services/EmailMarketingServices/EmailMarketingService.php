<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailSubaccount;
use Carbon\Carbon;

/**
 * Orchestrates the SendGrid-backed Email Marketing module (replacing the
 * old Mailgun integration - a per-user SendGrid subaccount, not one
 * admin-global domain/key, per this app's explicit tenant-isolation
 * requirement). This class stays the thin facade EmailCampaignController/
 * EmailMarketingController already call into; the real per-concern logic
 * lives in the focused SendGrid*Service classes it delegates to
 * (SendGridSubaccountService, SendGridCampaignService, etc.) - kept
 * separate rather than one large class, matching the spec's own
 * "SendGridSubaccountService/SendGridCampaignService/..." service list.
 *
 * Two behavior changes from the old Mailgun flow, both structural rather
 * than incidental:
 *
 * 1. Personalization ({{first_name}}/{{last_name}}/{{email}}) is now
 *    substituted by SendGrid itself against each recipient's synced
 *    Contact fields, not rendered here per-recipient - a Single Send
 *    delivers the exact same html_content to SendGrid, which fans it out
 *    to the whole target list itself. There is no per-recipient loop on
 *    this app's side anymore to render into.
 * 2. Unsubscribe is handled by SendGrid's own suppression-group mechanism
 *    (email_config.suppression_group_id) once configured under Admin >
 *    APIs, not an app-appended HTML footer - SendGrid injects a
 *    compliant unsubscribe link automatically and keeps its own
 *    suppression list authoritative, which this app's own
 *    unsubscribed/bounced/complained status mirrors via the Event
 *    Webhook (see SendGridWebhookService).
 */
class EmailMarketingService
{
    public function __construct(
        protected SendGridSubaccountService $subaccounts,
        protected SendGridCampaignService $campaigns,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->subaccounts->isConfigured();
    }

    /**
     * Whether THIS seller specifically can send - distinct from
     * isConfigured() (the one admin-global parent key existing at all).
     * The dashboard/campaign UI reads this, not isConfigured(), to decide
     * whether to point the user at the setup wizard.
     */
    public function isReadyForUser(int $userId): bool
    {
        return (bool) EmailSubaccount::where('user_id', $userId)->where('status', 'active')->first()?->isActive();
    }

    /**
     * Saves the campaign as a SendGrid Single Send draft (creating it on
     * first save, updating in place after) without sending/scheduling it -
     * called on every "Save Draft" and as the first step of "Send Now"/
     * "Schedule" below.
     */
    public function saveDraft(EmailCampaign $campaign): array
    {
        return $this->campaigns->saveDraft($campaign);
    }

    public function preflight(EmailCampaign $campaign): array
    {
        return $this->campaigns->preflight($campaign);
    }

    /**
     * Sends immediately (no $sendAt) or schedules for later - both go
     * through SendGrid's schedule endpoint (send_at: 'now' vs an ISO
     * timestamp), matching how SendGrid itself only has one "schedule"
     * concept rather than a separate immediate-send call.
     */
    public function dispatchCampaign(EmailCampaign $campaign, ?Carbon $sendAt = null): array
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return ['success' => false, 'error' => 'This campaign has already been sent or is currently sending.'];
        }
    
        $result = $this->campaigns->sendOrSchedule($campaign, $sendAt);

        if (!$result['success']) {
            return $result;
        }

        $campaign->update([
            'status'       => $sendAt ? 'scheduled' : 'sent',
            'scheduled_at' => $sendAt,
            'sent_at'      => $sendAt ? null : now(),
        ]);

        return ['success' => true];
    }

    public function cancelCampaign(EmailCampaign $campaign): array
    {
        $result = $this->campaigns->cancel($campaign);

        if ($result['success']) {
            $campaign->update(['status' => 'draft', 'scheduled_at' => null]);
        }

        return $result;
    }
}
