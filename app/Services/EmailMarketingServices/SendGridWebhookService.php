<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailEvent;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;

/**
 * SendGrid signs Event Webhook deliveries with ECDSA (not an HMAC shared
 * secret like Mailgun's) - headers X-Twilio-Email-Event-Webhook-Signature
 * / X-Twilio-Email-Event-Webhook-Timestamp, verified against a PUBLIC key
 * SendGrid generates per-subuser when signing is enabled
 * (PATCH /v3/user/webhooks/event/settings/signed, done with that subuser's
 * own key - stored as EmailSubaccount.webhook_public_key). One shared app
 * URL (POST /api/webhooks/sendgrid/events) receives every seller's events;
 * the subaccount to verify against is resolved from the payload's own
 * event data (email/message id) rather than a URL segment, since
 * SendGrid's webhook URL is configured once per subuser and there is no
 * subaccount identifier in the URL itself.
 */
class SendGridWebhookService
{
    /**
     * Tries every active subaccount's stored public key until one
     * verifies - there is no subaccount id in the request itself to look
     * up the right key directly. Cheap in practice: this app's per-
     * installation seller count is small, and a real production
     * deployment would instead register a distinct webhook URL per
     * subuser (eg. /api/webhooks/sendgrid/events/{subaccount}) to avoid
     * this loop - noted as a follow-up rather than done here, since it
     * changes the URL every seller's SendGrid webhook settings must be
     * configured with.
     */
    public function verifyAndIdentify(string $timestamp, string $signature, string $rawBody): ?EmailSubaccount
    {
        $signedPayload = $timestamp . $rawBody;
        $decodedSignature = base64_decode($signature, true);

        if ($decodedSignature === false) {
            return null;
        }

        foreach (EmailSubaccount::whereNotNull('webhook_public_key')->get() as $subaccount) {
            if ($this->verify($signedPayload, $decodedSignature, $subaccount->webhook_public_key)) {
                return $subaccount;
            }
        }

        return null;
    }

    private function verify(string $signedPayload, string $decodedSignature, string $publicKeyBase64): bool
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKeyBase64, 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            return false;
        }

        return openssl_verify($signedPayload, $decodedSignature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * One event payload -> one EmailEvent row (skipped if sg_event_id
     * already exists - SendGrid explicitly documents at-least-once
     * delivery, so idempotency is a real requirement, not defensive
     * over-engineering) plus the matching EmailCampaign counter increment.
     * marketing_campaign_id/marketing_campaign_name are the fields
     * SendGrid's Event Webhook includes specifically for events generated
     * by a Marketing Campaigns Single Send - matched back to
     * sendgrid_single_send_id, this app's own campaign id.
     */
    public function handleEvent(EmailSubaccount $subaccount, array $payload): void
    {
        $sgEventId = $payload['sg_event_id'] ?? null;

        if (!$sgEventId || EmailEvent::where('sg_event_id', $sgEventId)->exists()) {
            return;
        }

        $singleSendId = $payload['marketing_campaign_id'] ?? null;
        $campaign = $singleSendId
            ? EmailCampaign::where('sendgrid_single_send_id', $singleSendId)->first()
            : null;

        $eventType = $payload['event'] ?? 'unknown';

        EmailEvent::create([
            'user_id'             => $subaccount->user_id,
            'email_campaign_id'   => $campaign?->id,
            'sendgrid_message_id' => $payload['sg_message_id'] ?? null,
            'event_type'          => $eventType,
            'recipient_email'     => $payload['email'] ?? '',
            'event_at'            => isset($payload['timestamp']) ? now()->createFromTimestamp($payload['timestamp']) : now(),
            'ip'                  => $payload['ip'] ?? null,
            'user_agent'          => $payload['useragent'] ?? null,
            'url'                 => $payload['url'] ?? null,
            'reason'              => $payload['reason'] ?? null,
            'sg_event_id'         => $sgEventId,
            'raw_payload'         => $payload,
        ]);

        if ($campaign) {
            $this->incrementCampaignCounter($campaign, $eventType);
        }

        if (in_array($eventType, ['unsubscribe', 'group_unsubscribe', 'bounce', 'spamreport'], true)) {
            $this->updateSubscriberStatus($subaccount, $payload['email'] ?? '', $eventType);
        }
    }

    private function incrementCampaignCounter(EmailCampaign $campaign, string $eventType): void
    {
        $column = match ($eventType) {
            'delivered'                          => 'delivered_count',
            'open'                                => 'opened_count',
            'click'                               => 'clicked_count',
            'bounce'                              => 'bounced_count',
            'spamreport'                          => 'complained_count',
            'unsubscribe', 'group_unsubscribe'    => 'unsubscribed_count',
            default                               => null,
        };

        if ($column) {
            $campaign->increment($column);
        }
    }

    private function updateSubscriberStatus(EmailSubaccount $subaccount, string $email, string $eventType): void
    {
        $subscriber = EmailSubscriber::where('user_id', $subaccount->user_id)->where('email', $email)->first();

        if (!$subscriber) {
            return;
        }

        $status = match ($eventType) {
            'unsubscribe', 'group_unsubscribe' => 'unsubscribed',
            'bounce'                            => 'bounced',
            'spamreport'                        => 'complained',
            default                             => null,
        };

        if ($status && $subscriber->status === 'subscribed') {
            $subscriber->update([
                'status'           => $status,
                'unsubscribed_at'  => $status === 'unsubscribed' ? now() : $subscriber->unsubscribed_at,
            ]);
        }
    }
}
