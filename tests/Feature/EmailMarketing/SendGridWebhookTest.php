<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailEvent;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\User;
use App\Services\EmailMarketingServices\SendGridWebhookService;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * SendGrid signs with ECDSA, not an HMAC shared secret - these generate a
 * real EC key pair with openssl (exactly as SendGrid's own signing
 * mechanism does) so the signature check is exercised for real, not
 * mocked away. Also covers the two things the spec explicitly calls out:
 * idempotency (a duplicate sg_event_id must never double-process) and
 * rejecting a tampered/invalid signature outright.
 */
class SendGridWebhookTest extends TestCase
{
    use CreatesEmailMarketingTables;

    private $privateKey;
    private string $publicKeyBase64;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();

        $keyPair = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKey = openssl_pkey_get_private($privateKeyPem);

        $details = openssl_pkey_get_details($keyPair);
        // Strip PEM headers/newlines - EmailSubaccount.webhook_public_key
        // stores the same bare-base64 form SendGrid's dashboard shows.
        $this->publicKeyBase64 = trim(str_replace(
            ["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n"],
            '',
            $details['key']
        ));
    }

    private function sign(string $timestamp, string $body): string
    {
        openssl_sign($timestamp . $body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public function test_a_correctly_signed_event_is_accepted_and_stored(): void
    {
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create([
            'user_id' => $user->id, 'status' => 'active',
            'api_key' => ['key' => 'SG.test'], 'webhook_public_key' => $this->publicKeyBase64,
        ]);
        $campaign = EmailCampaign::create([
            'user_id' => $user->id, 'name' => 'C1', 'subject' => 'S', 'from_name' => 'F',
            'from_email' => 'f@example.com', 'body' => 'b', 'status' => 'sent',
            'sendgrid_single_send_id' => 'ss_123',
        ]);

        $timestamp = (string) time();
        $body = json_encode([[
            'sg_event_id' => 'evt_1', 'sg_message_id' => 'msg_1', 'event' => 'delivered',
            'email' => 'contact@example.com', 'timestamp' => time(), 'marketing_campaign_id' => 'ss_123',
        ]]);

        $service = app(SendGridWebhookService::class);
        $found = $service->verifyAndIdentify($timestamp, $this->sign($timestamp, $body), $body);

        $this->assertNotNull($found);
        $this->assertEquals($subaccount->id, $found->id);

        $service->handleEvent($found, json_decode($body, true)[0]);

        $this->assertDatabaseHas('email_events', ['sg_event_id' => 'evt_1', 'event_type' => 'delivered']);
        $this->assertEquals(1, $campaign->fresh()->delivered_count);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        EmailSubaccount::create([
            'user_id' => $user->id, 'status' => 'active',
            'api_key' => ['key' => 'SG.test'], 'webhook_public_key' => $this->publicKeyBase64,
        ]);

        $timestamp = (string) time();
        $body = json_encode([['sg_event_id' => 'evt_2', 'event' => 'delivered', 'email' => 'x@example.com']]);
        $validSignature = $this->sign($timestamp, $body);

        // Body changed after signing - same class of forgery attempt the
        // signature exists to catch.
        $tamperedBody = json_encode([['sg_event_id' => 'evt_2', 'event' => 'delivered', 'email' => 'attacker@example.com']]);

        $found = app(SendGridWebhookService::class)->verifyAndIdentify($timestamp, $validSignature, $tamperedBody);

        $this->assertNull($found);
    }

    public function test_a_duplicate_event_id_is_not_processed_twice(): void
    {
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create([
            'user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test'],
        ]);
        $campaign = EmailCampaign::create([
            'user_id' => $user->id, 'name' => 'C1', 'subject' => 'S', 'from_name' => 'F',
            'from_email' => 'f@example.com', 'body' => 'b', 'status' => 'sent',
            'sendgrid_single_send_id' => 'ss_123',
        ]);

        $payload = [
            'sg_event_id' => 'evt_dup', 'event' => 'open', 'email' => 'contact@example.com',
            'marketing_campaign_id' => 'ss_123',
        ];

        $service = app(SendGridWebhookService::class);
        $service->handleEvent($subaccount, $payload);
        $service->handleEvent($subaccount, $payload); // SendGrid's documented at-least-once redelivery.

        $this->assertEquals(1, EmailEvent::where('sg_event_id', 'evt_dup')->count());
        $this->assertEquals(1, $campaign->fresh()->opened_count);
    }
}
