<?php

namespace App\Services\EmailMarketingServices;

use App\Jobs\EmailMarketing\SendCampaignEmailJob;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailCampaignSend;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\ApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Sends campaign email through Mailgun's HTTP API directly (messages
 * endpoint) rather than through Laravel's Mail facade - the same "call the
 * platform's real REST API directly" approach used by every other
 * integration in this app (Slack chat.postMessage, Discord's REST API,
 * etc), which here also means the Mailgun-assigned message id and
 * per-recipient tracking/custom variables are available directly from the
 * send response instead of needing extra event-listener plumbing.
 *
 * Credentials (domain, private API key, webhook signing key) are platform-
 * wide settings configured once by the app owner through Admin > APIs
 * (AdminSetting/adminSetting()), the same mechanism already used for
 * Slack's client_id/client_secret/signing_secret - there is one Mailgun
 * sending domain for the whole app, shared by every tenant's campaigns,
 * not a per-user credential.
 *
 * Endpoint shapes verified this session against documentation.mailgun.com:
 * POST /v3/{domain}/messages (form-encoded, HTTP Basic auth with
 * "api" as the username), the o:tracking-opens/o:tracking-clicks/v:*
 * custom-variable/webhook signature (HMAC-SHA256 of timestamp+token)
 * request/payload shapes, and the delivered/opened/clicked/permanent_fail/
 * temporary_fail/complained/unsubscribed webhook event names.
 */
class EmailMarketingService
{
    public function __construct(protected ApiService $apiService)
    {
    }

    /**
     * Null (rather than throwing) when incomplete, so callers can surface
     * one clear "not configured yet" error instead of a confusing HTTP
     * failure the first time an admin tries this feature before setting
     * their Mailgun credentials.
     */
    public function config(): ?array
    {
        $domain = adminSetting('email_marketing.mailgun.domain');
        $secret = adminSetting('email_marketing.mailgun.secret');

        if (!$domain || !$secret) {
            return null;
        }

        return [
            'domain'               => $domain,
            'secret'               => $secret,
            'endpoint'             => adminSetting('email_marketing.mailgun.endpoint') ?: 'api.mailgun.net',
            'webhook_signing_key'  => adminSetting('email_marketing.mailgun.webhook_signing_key') ?: $secret,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->config() !== null;
    }

    /**
     * Verifies the Mailgun domain/API key actually work by hitting the
     * domain's own info endpoint - the same "prove it against the real API
     * before trusting it" check this module's other platform integrations
     * do at connect time.
     */
    public function verifyCredentials(string $domain, string $secret, string $endpoint): array
    {
        $response = $this->apiService->get(
            "https://{$endpoint}/v3/domains/{$domain}",
            ['Authorization' => 'Basic ' . base64_encode('api:' . $secret)],
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $this->extractError($response, 'Could not verify this Mailgun domain/API key.')];
        }

        return ['success' => true];
    }

    /**
     * Creates one email_campaign_sends row per currently-subscribed member
     * of the campaign's list and queues a job for each - only subscribers
     * with status "subscribed" get a row at all, so bounced/complained/
     * already-unsubscribed contacts are never queued in the first place.
     * Safe to call again on a campaign that partially dispatched before
     * (eg. after a crash) since the unique (campaign, subscriber) index
     * plus upsert() means already-created rows are left untouched.
     */
    public function dispatchCampaign(EmailCampaign $campaign): array
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return ['success' => false, 'error' => 'This campaign has already been sent or is currently sending.'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Mailgun is not configured yet. Add your domain and API key under Admin > APIs.'];
        }

        $subscriberIds = $campaign->list->subscribers()
            ->where('status', 'subscribed')
            ->pluck('email_subscribers.id');

        if ($subscriberIds->isEmpty()) {
            return ['success' => false, 'error' => 'This list has no subscribed recipients to send to.'];
        }

        $now = now();
        $rows = $subscriberIds->map(fn ($subscriberId) => [
            'email_campaign_id'   => $campaign->id,
            'email_subscriber_id' => $subscriberId,
            'status'              => 'pending',
            'created_at'          => $now,
            'updated_at'          => $now,
        ])->all();

        EmailCampaignSend::upsert($rows, ['email_campaign_id', 'email_subscriber_id'], ['updated_at']);

        $campaign->update([
            'status'              => 'sending',
            'total_recipients'    => $subscriberIds->count(),
            'sent_count'          => 0,
            'delivered_count'     => 0,
            'opened_count'        => 0,
            'clicked_count'       => 0,
            'bounced_count'       => 0,
            'complained_count'    => 0,
            'unsubscribed_count'  => 0,
            'failed_count'        => 0,
            'error_message'       => null,
        ]);

        $pendingSendIds = EmailCampaignSend::where('email_campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->pluck('id');

        foreach ($pendingSendIds as $sendId) {
            SendCampaignEmailJob::dispatch($sendId);
        }

        return ['success' => true];
    }

    /**
     * The actual Mailgun API call for a single recipient - called from
     * SendCampaignEmailJob, kept here (rather than in the job) so both the
     * job and any future retry/resend tooling can reuse it.
     */
    public function sendCampaignEmail(EmailCampaignSend $send): array
    {
        $config = $this->config();

        if (!$config) {
            return ['success' => false, 'error' => 'Mailgun is not configured.'];
        }

        $campaign = $send->campaign;
        $subscriber = $send->subscriber;
        $unsubscribeUrl = $this->unsubscribeUrl($subscriber);

        $subject = $this->renderMergeTags($campaign->subject, $subscriber, $unsubscribeUrl);
        $html = $this->appendUnsubscribeFooter(
            $this->renderMergeTags($campaign->body, $subscriber, $unsubscribeUrl),
            $unsubscribeUrl
        );

        $response = $this->apiService->post(
            "https://{$config['endpoint']}/v3/{$config['domain']}/messages",
            ['Authorization' => 'Basic ' . base64_encode('api:' . $config['secret'])],
            [
                'from'                  => "{$campaign->from_name} <{$campaign->from_email}>",
                'to'                    => $subscriber->email,
                'subject'               => $subject,
                'html'                  => $html,
                'o:tracking'            => 'yes',
                'o:tracking-opens'      => 'yes',
                'o:tracking-clicks'     => 'yes',
                'o:tag'                 => 'campaign-' . $campaign->id,
                'v:send_id'             => (string) $send->id,
                'v:campaign_id'         => (string) $campaign->id,
                'h:List-Unsubscribe'    => "<{$unsubscribeUrl}>",
                'h:List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
            'form'
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $this->extractError($response, 'Mailgun API request failed.')];
        }

        return ['success' => true, 'mailgun_message_id' => $response['data']['id'] ?? null];
    }

    /**
     * Mailgun doesn't always answer with a JSON body - eg. a plain-text
     * "Forbidden" for a bad API key - so ApiService's parsed `data` is
     * null in that case and the real reason would otherwise be silently
     * dropped in favour of a generic message.
     */
    private function extractError(array $response, string $fallback): string
    {
        $message = $response['data']['message'] ?? trim((string) ($response['body'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    /**
     * After every send attempt (success or failure), checks whether every
     * queued recipient for the campaign has now been attempted - if so the
     * campaign moves out of "sending" into a terminal status. Delivery/
     * open/click counters keep updating via webhook events long after
     * this, the same way Mailchimp-style ESPs show a campaign as "Sent"
     * while engagement stats keep trickling in.
     */
    public function finalizeCampaignIfComplete(EmailCampaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->status !== 'sending') {
            return;
        }

        $stillPending = $campaign->sends()->whereIn('status', ['pending', 'sending'])->exists();

        if ($stillPending) {
            return;
        }

        $campaign->update([
            'status'  => $campaign->failed_count >= $campaign->total_recipients && $campaign->total_recipients > 0 ? 'failed' : 'sent',
            'sent_at' => now(),
        ]);
    }

    public function unsubscribeUrl(EmailSubscriber $subscriber): string
    {
        return Route::has('email.unsubscribe')
            ? route('email.unsubscribe', $subscriber->unsubscribe_token)
            : url('/email/unsubscribe/' . $subscriber->unsubscribe_token);
    }

    private function renderMergeTags(string $content, EmailSubscriber $subscriber, string $unsubscribeUrl): string
    {
        $name = $subscriber->name ?: 'there';
        $firstName = trim(explode(' ', $name)[0]) ?: $name;

        return strtr($content, [
            '{{name}}'             => e($name),
            '{{first_name}}'       => e($firstName),
            '{{email}}'            => e($subscriber->email),
            '{{unsubscribe_url}}'  => $unsubscribeUrl,
        ]);
    }

    private function appendUnsubscribeFooter(string $html, string $unsubscribeUrl): string
    {
        $address = adminSetting('email_marketing.company_address');

        return $html . '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:sans-serif;font-size:12px;color:#9ca3af;">'
            . ($address ? e($address) . '<br>' : '')
            . 'You are receiving this email because you subscribed to our mailing list. '
            . '<a href="' . $unsubscribeUrl . '" style="color:#6d28d9;">Unsubscribe</a>'
            . '</div>';
    }

    /**
     * HMAC-SHA256 of timestamp+token using the webhook signing key, plus a
     * 15-minute replay window - the same shape as Slack's v0 signature
     * check in SlackMessagingService::verifySignature(), just with
     * Mailgun's own field names (Mailgun puts the raw pieces inside a
     * `signature` object in the JSON body rather than in headers).
     */
    public function verifyWebhookSignature(array $signature): bool
    {
        $config = $this->config();
        $timestamp = $signature['timestamp'] ?? null;
        $token = $signature['token'] ?? null;
        $sig = $signature['signature'] ?? null;

        if (!$config || !$timestamp || !$token || !$sig) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 900) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $config['webhook_signing_key']);

        return hash_equals($expected, (string) $sig);
    }

    /**
     * Applies a single Mailgun event to the send row + campaign counters
     * it belongs to, resolved via the v:send_id custom variable set at
     * send time - not by matching Mailgun's message id, which avoids any
     * edge case around how that id gets formatted/wrapped by the time it
     * reaches a webhook payload. Idempotent per event type (checks the
     * relevant *_at column is still null) since Mailgun explicitly
     * documents that the same event can be delivered more than once.
     */
    public function handleWebhookEvent(array $payload): void
    {
        $eventData = $payload['event-data'] ?? [];
        $event = $eventData['event'] ?? null;
        $sendId = $eventData['user-variables']['send_id'] ?? null;

        if (!$event || !$sendId) {
            return;
        }

        $send = EmailCampaignSend::with('campaign', 'subscriber')->find($sendId);

        if (!$send || !$send->campaign || !$send->subscriber) {
            return;
        }

        $campaign = $send->campaign;
        $subscriber = $send->subscriber;

        switch ($event) {
            case 'delivered':
                if (!$send->delivered_at) {
                    $send->update(['delivered_at' => now(), 'status' => 'delivered']);
                    $campaign->increment('delivered_count');
                }
                break;

            case 'opened':
                if (!$send->opened_at) {
                    $send->update(['opened_at' => now(), 'status' => 'opened']);
                    $campaign->increment('opened_count');
                }
                break;

            case 'clicked':
                if (!$send->clicked_at) {
                    $send->update(['clicked_at' => now(), 'status' => 'clicked']);
                    $campaign->increment('clicked_count');
                }
                break;

            case 'unsubscribed':
                if (!$send->unsubscribed_at) {
                    $send->update(['unsubscribed_at' => now(), 'status' => 'unsubscribed']);
                    $campaign->increment('unsubscribed_count');
                }
                if ($subscriber->status === 'subscribed') {
                    $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
                }
                break;

            case 'complained':
                if (!$send->complained_at) {
                    $send->update(['complained_at' => now(), 'status' => 'complained']);
                    $campaign->increment('complained_count');
                }
                $subscriber->update(['status' => 'complained']);
                break;

            case 'failed':
                // Mailgun fires "failed" for both permanent (bad address,
                // never retried) and temporary (still retrying) delivery
                // problems - only a permanent failure should stop future
                // sends to this address; a temporary one is just logged.
                if (($eventData['severity'] ?? null) === 'permanent') {
                    if (!$send->bounced_at) {
                        $send->update(['bounced_at' => now(), 'status' => 'bounced']);
                        $campaign->increment('bounced_count');
                    }
                    $subscriber->update(['status' => 'bounced']);
                } else {
                    Log::info('Mailgun temporary delivery failure', ['send_id' => $send->id, 'reason' => $eventData['delivery-status']['message'] ?? null]);
                }
                break;
        }
    }
}
