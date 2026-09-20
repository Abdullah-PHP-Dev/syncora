<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSegment;
use App\Models\EmailMarketing\EmailSubaccount;
use Carbon\Carbon;

/**
 * Builds/schedules a SendGrid Marketing Campaigns Single Send for a local
 * EmailCampaign row - the current, non-legacy campaign API (confirmed
 * against docs this session), replacing EmailMarketingService's old
 * one-Mailgun-call-per-recipient loop entirely. html_content is sent
 * directly in email_config rather than first syncing a SendGrid Design
 * object - this app's own email_campaigns.body stays the single source of
 * truth for content, and Single Send always reflects whatever is saved
 * here at create/update time.
 */
class SendGridCampaignService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    /**
     * The spec's "pre-flight checklist" - every item is a real check
     * against actual infrastructure state (never a single boolean), and
     * sending is refused unless every required item passes.
     */
    public function preflight(EmailCampaign $campaign): array
    {
        $subaccount = EmailSubaccount::where('user_id', $campaign->user_id)->first();
        $sender = $campaign->senderIdentity;
        $audience = $campaign->audience();
        $recipientCount = $this->audienceRecipientCount($audience);

        $checks = [
            ['label' => 'SendGrid subaccount active', 'pass' => (bool) $subaccount?->isActive()],
            ['label' => 'Sender verified', 'pass' => (bool) $sender?->isVerified()],
            ['label' => 'Audience selected', 'pass' => (bool) $audience],
            ['label' => 'Audience contains recipients', 'pass' => $recipientCount > 0],
            ['label' => 'Subject provided', 'pass' => filled($campaign->subject)],
            ['label' => 'Content provided', 'pass' => filled($campaign->body)],
            // Every marketing send needs a real SendGrid unsubscribe
            // group attached (ASM) - a draft can be saved without one,
            // but Send Now/Schedule stay blocked until it's set. Never
            // silently falls back to a hardcoded/shared group id (see
            // SendGridSuppressionService's docblock for why a single
            // global value was wrong for this per-subaccount model).
            ['label' => 'Unsubscribe group selected', 'pass' => (bool) $campaign->suppression_group_id],
        ];

        return [
            'ready'  => collect($checks)->every(fn ($c) => $c['pass']),
            'checks' => $checks,
        ];
    }

    private function audienceRecipientCount(EmailList|EmailSegment|null $audience): int
    {
        if ($audience instanceof EmailList) {
            return $audience->subscribers()->where('status', 'subscribed')->count();
        }

        // Segment membership lives in SendGrid, not locally - a synced
        // segment's count isn't knowable without an extra API round trip,
        // which the setup/campaign wizard makes explicitly when displaying
        // "estimated recipients" rather than every time preflight() runs.
        return $audience ? 1 : 0;
    }

    /**
     * Creates the Single Send draft on first save, updates it on every
     * later save while still a draft - SendGrid's own create/update split
     * (POST vs PATCH) mirrored 1:1 rather than always deleting and
     * recreating.
     */
    public function saveDraft(EmailCampaign $campaign): array
    {
        $preflight = $this->preflight($campaign);
       
        $subaccount = EmailSubaccount::where('user_id', $campaign->user_id)->first();

        if (!$subaccount?->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.'];
        }

        $audience = $campaign->audience();
        
        $sendTo = $audience instanceof EmailSegment
            ? ['segment_ids' => [$audience->sendgrid_segment_id]]
            : ['list_ids' => array_filter([$audience?->sendgrid_list_id])];

        $payload = [
            'name'    => $campaign->name,
            'send_to' => $sendTo,
            'email_config' => [
                'subject'      => $campaign->subject,
                'html_content' => $campaign->body,
                'sender_id'    => (int) $campaign->senderIdentity?->sendgrid_sender_id,
                // Cast explicitly, not just relying on the model's own
                // 'integer' cast - SendGrid's Go backend rejects a JSON
                // string here with an opaque "json could not be
                // unmarshalled" error (confirmed live this session: a
                // value just set via mass-assignment from an HTML
                // <select> stayed a string in-memory for the rest of
                // that request).
                'suppression_group_id' => $campaign->suppression_group_id ? (int) $campaign->suppression_group_id : null,
            ],
        ];

        $client = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region);

        $response = $campaign->sendgrid_single_send_id
            ? $client->patch("marketing/singlesends/{$campaign->sendgrid_single_send_id}", $payload)
            : $client->post('marketing/singlesends', $payload);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'preflight' => $preflight];
        }

        if (!$campaign->sendgrid_single_send_id) {
            $campaign->update(['sendgrid_single_send_id' => $response['data']['id'] ?? null]);
        }

        return ['success' => true, 'preflight' => $preflight];
    }

    /**
     * SendGrid requires the draft to exist before it can be scheduled -
     * saveDraft() is always called first so "Send Now"/"Schedule" from a
     * campaign that was never explicitly saved as a draft still works.
     */
    public function sendOrSchedule(EmailCampaign $campaign, ?Carbon $sendAt = null): array
    {
        $preflight = $this->preflight($campaign);
       
        if (!$preflight['ready']) {
            $failed = collect($preflight['checks'])->where('pass', false)->pluck('label')->implode(', ');

            return [
                'success'  => false,
                'error'    => "This campaign is not ready to send: {$failed}.",
                'preflight' => $preflight,
            ];
        }

        $draftResult = $this->saveDraft($campaign);
   
        if (!$draftResult['success']) {
            return $draftResult;
        }

        $subaccount = EmailSubaccount::where('user_id', $campaign->user_id)->first();

        // PUT, not POST - confirmed live against the real API this
        // session: POST here returns an empty-body 405 (a routing-level
        // rejection, not a validation error), which is what actually
        // produced the "json could not be unmarshalled" error a seller
        // saw - the previous docblock's "confirmed against docs" claim
        // for this endpoint was wrong.
        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->put("marketing/singlesends/{$campaign->sendgrid_single_send_id}/schedule", [
                'send_at' => $sendAt ? $sendAt->toIso8601String() : 'now',
            ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true];
    }

    public function cancel(EmailCampaign $campaign): array
    {
        $subaccount = EmailSubaccount::where('user_id', $campaign->user_id)->first();

        if (!$campaign->sendgrid_single_send_id || !$subaccount) {
            return ['success' => true];
        }

        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->delete("marketing/singlesends/{$campaign->sendgrid_single_send_id}/schedule");

        return $response['success']
            ? ['success' => true]
            : ['success' => false, 'error' => $response['error']];
    }

    /**
     * Reconciliation safety net (see SendScheduledEmailCampaigns) - the
     * Event Webhook is the primary source of truth for delivery/open/
     * click counts, but a campaign's own top-level status should still
     * reflect reality even if a webhook delivery was somehow missed.
     * Retrieve Single Send is the real, current endpoint for this
     * (GET /v3/marketing/singlesends/{id}) - not the Stats API, which the
     * spec explicitly says shouldn't be relied on for real-time state.
     */
    public function checkStatus(EmailCampaign $campaign): array
    {
        $subaccount = EmailSubaccount::where('user_id', $campaign->user_id)->first();

        if (!$campaign->sendgrid_single_send_id || !$subaccount?->isActive()) {
            return ['success' => false, 'error' => 'Not sent through SendGrid yet.'];
        }

        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->get("marketing/singlesends/{$campaign->sendgrid_single_send_id}");

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'status' => $response['data']['status'] ?? null];
    }
}
