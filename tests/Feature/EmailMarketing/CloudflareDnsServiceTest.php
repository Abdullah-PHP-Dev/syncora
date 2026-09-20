<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\DomainDnsRecord;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\VerifiedDomain;
use App\Models\User;
use App\Services\EmailMarketingServices\CloudflareDnsService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Covers the idempotency/safety guarantees CloudflareDnsService::
 * ensureRecord()/configureSendGridRecords() are supposed to provide -
 * every scenario asserts BOTH the returned outcome and (via
 * Http::assertSentCount()/Http::preventStrayRequests()) exactly which
 * real API calls were or weren't made, so a bug that silently skips a
 * needed write (or makes an unwanted one) fails the test even if the
 * returned array happens to look right.
 */
class CloudflareDnsServiceTest extends TestCase
{
    use CreatesEmailMarketingTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();

        config([
            'services.cloudflare.api_token'  => 'test-token',
            'services.cloudflare.account_id' => 'test-account',
            'services.cloudflare.zone_id'    => 'test-zone-id',
        ]);
    }

    private function fakeZone(string $name = 'socialeaz.com'): void
    {
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => $name, 'status' => 'active']], 200
            ),
        ]);
    }

    private function domainWithRecord(User $user, string $domain = 'socialeaz.com'): VerifiedDomain
    {
        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);

        $verifiedDomain = VerifiedDomain::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id,
            'sendgrid_domain_id' => 1, 'domain' => $domain, 'status' => 'dns_pending',
        ]);

        DomainDnsRecord::create([
            'verified_domain_id' => $verifiedDomain->id,
            'record_purpose'     => 'mail_cname',
            'type'                => 'cname',
            'host'                => 'em1234.' . $domain,
            'data'                => 'u1.wl1.sendgrid.net',
            'valid'               => false,
        ]);

        return $verifiedDomain->fresh('dnsRecords');
    }

    public function test_valid_credentials_return_success(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(
                ['success' => true, 'result' => ['id' => 'abc', 'status' => 'active']], 200
            ),
        ]);

        $result = app(CloudflareDnsService::class)->validateCredentials();

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
    }

    public function test_invalid_credentials_return_a_clear_error_without_exposing_the_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(
                ['success' => false, 'errors' => [['code' => 1000, 'message' => 'Invalid API Token']]], 400
            ),
        ]);

        $result = app(CloudflareDnsService::class)->validateCredentials();

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid API Token', $result['error']);
        $this->assertStringNotContainsString('test-token', json_encode($result));
    }

    public function test_configured_zone_is_correctly_identified_for_a_matching_domain(): void
    {
        Http::preventStrayRequests();
        $this->fakeZone('socialeaz.com');

        $result = app(CloudflareDnsService::class)->domainBelongsToZone('mail.socialeaz.com');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['belongs']);
    }

    public function test_a_domain_outside_the_configured_zone_is_rejected_before_any_write(): void
    {
        Http::preventStrayRequests();
        $this->fakeZone('socialeaz.com');

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user, 'someone-elses-domain.com');

        $result = app(CloudflareDnsService::class)->configureSendGridRecords($domain);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString("isn't on the platform's Cloudflare zone", $result['error']);
        // Only the zone lookup happened - never a records list/create/update call.
        Http::assertSentCount(1);
    }

    public function test_a_correct_existing_record_is_skipped_not_duplicated(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => 'socialeaz.com']], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records*' => Http::response(
                ['success' => true, 'result' => [['id' => 'rec1', 'type' => 'CNAME', 'name' => 'em1234.socialeaz.com', 'content' => 'u1.wl1.sendgrid.net', 'proxied' => false]]], 200
            ),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user);

        $result = app(CloudflareDnsService::class)->configureSendGridRecords($domain);

        $this->assertTrue($result['success']);
        $this->assertEquals('skipped', $result['records'][0]['outcome']);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
    }

    public function test_a_missing_record_is_created(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => 'socialeaz.com']], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records*' => Http::sequence()
                ->push(['success' => true, 'result' => []], 200) // list: nothing exists yet
                ->push(['success' => true, 'result' => ['id' => 'new-rec', 'type' => 'CNAME', 'name' => 'em1234.socialeaz.com']], 200), // create
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user);

        $result = app(CloudflareDnsService::class)->configureSendGridRecords($domain);

        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['records'][0]['outcome']);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), 'dns_records')
            && $request['content'] === 'u1.wl1.sendgrid.net' && $request['proxied'] === false);
    }

    public function test_a_conflicting_record_is_reported_and_never_overwritten(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => 'socialeaz.com']], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records*' => Http::response(
                ['success' => true, 'result' => [['id' => 'rec1', 'type' => 'CNAME', 'name' => 'em1234.socialeaz.com', 'content' => 'something-unrelated.example.net', 'proxied' => false]]], 200
            ),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user);

        $result = app(CloudflareDnsService::class)->configureSendGridRecords($domain);

        $this->assertTrue($result['success']);
        $this->assertEquals('conflict', $result['records'][0]['outcome']);
        $this->assertEquals('something-unrelated.example.net', $result['records'][0]['existing']);
        // A conflict is reported, never silently overwritten.
        Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_a_correct_but_proxied_record_is_safely_unproxied(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => 'socialeaz.com']], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records*' => Http::response(
                ['success' => true, 'result' => [['id' => 'rec1', 'type' => 'CNAME', 'name' => 'em1234.socialeaz.com', 'content' => 'u1.wl1.sendgrid.net', 'proxied' => true]]], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records/rec1' => Http::response(
                ['success' => true, 'result' => ['id' => 'rec1', 'proxied' => false]], 200
            ),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user);

        $result = app(CloudflareDnsService::class)->configureSendGridRecords($domain);

        $this->assertEquals('updated', $result['records'][0]['outcome']);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['proxied'] === false);
    }

    public function test_running_configuration_twice_is_idempotent(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudflare.com/client/v4/zones/test-zone-id' => Http::response(
                ['success' => true, 'result' => ['id' => 'test-zone-id', 'name' => 'socialeaz.com']], 200
            ),
            'api.cloudflare.com/client/v4/zones/test-zone-id/dns_records*' => Http::sequence()
                ->push(['success' => true, 'result' => []], 200) // 1st run: list -> nothing
                ->push(['success' => true, 'result' => ['id' => 'new-rec']], 200) // 1st run: create
                ->push(['success' => true, 'result' => [['id' => 'new-rec', 'type' => 'CNAME', 'name' => 'em1234.socialeaz.com', 'content' => 'u1.wl1.sendgrid.net', 'proxied' => false]]], 200), // 2nd run: list -> now exists
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithRecord($user);
        $service = app(CloudflareDnsService::class);

        $first = $service->configureSendGridRecords($domain);
        $this->assertEquals('created', $first['records'][0]['outcome']);

        $second = $service->configureSendGridRecords($domain->fresh('dnsRecords'));
        $this->assertEquals('skipped', $second['records'][0]['outcome']);

        // Exactly one POST (the create) across both runs - the second run
        // never re-creates it.
        Http::assertSentCount(5); // zone x2 + list x2 + create x1
    }
}
