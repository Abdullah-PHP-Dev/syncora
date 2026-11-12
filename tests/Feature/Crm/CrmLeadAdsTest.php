<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\CapabilityCatalog;
use App\Services\Connections\Drivers\MetaDriver;
use App\Services\Connections\Drivers\TikTokDriver;
use App\Services\Crm\MetaLeads;
use App\Services\Crm\TikTokLeads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\Concerns\CrmFixtures;
use Tests\TestCase;

/** Meta Lead Ads and TikTok Instant Form leads: permission → subscription → CRM leads. */
class CrmLeadAdsTest extends TestCase
{
    use CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        if (! Schema::hasColumn('social_accounts', 'asset_token')) {
            (require database_path('migrations/2026_10_07_100000_add_asset_and_user_tokens_to_social_accounts_table.php'))->up();
        }
        if (! Schema::hasTable('social_connections')) {
            (require database_path('migrations/2026_10_07_110000_create_social_connections_table.php'))->up();
        }
        DB::table('admin_settings')->insert([
            ['key' => 'ads.tiktok.client_id', 'value' => 'tt-app', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ads.tiktok.client_secret', 'value' => 'tt-secret', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'posts.facebook.client_id', 'value' => 'fb-app', 'created_at' => now(), 'updated_at' => now()],
        ]);
        \App\Support\Settings::forgetRequestCache();
    }

    private function metaPage(array $scopes, string $pageId = 'page-1'): SocialAccount
    {
        $connection = SocialConnection::create(['user_id' => $this->rawda->id, 'platform' => 'meta', 'step' => MetaDriver::LOGIN, 'provider_account_id' => 'fb-user-'.$pageId,
            'access_token' => 'user-token', 'granted_scopes' => $scopes, 'capabilities' => SocialConnection::capabilitiesFrom($scopes, SocialConnection::capabilityMap('meta', MetaDriver::LOGIN)), 'status' => 'active']);

        return SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'facebook', 'platform_account_id' => $pageId, 'name' => 'Rawda Page',
            'access_token' => 'page-token', 'social_connection_id' => $connection->id]);
    }

    public function test_meta_lead_permission_becomes_the_lead_ads_capability_and_subscribes_the_page_to_leadgen(): void
    {
        $this->assertStringContainsString('leads_retrieval', app()->call(fn () => (new \ReflectionMethod(\App\Services\SocialAuth\SocialAuthService::class, 'facebookPermissionParams'))->invoke(app(\App\Services\SocialAuth\SocialAuthService::class)))['scope'] ?? '');

        $page = $this->metaPage(['pages_manage_posts', 'pages_manage_metadata', 'leads_retrieval']);
        $this->assertTrue(MetaLeads::granted($page));
        $this->assertContains('leads', $page->connection->capabilities);
        $this->assertStringEndsWith(',leadgen', MetaLeads::pageFields($page));

        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $this->assertSame(['ok' => true, 'leadgen' => true, 'error' => null], MetaLeads::subscribe($page));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'page-1/subscribed_apps') && str_contains($r['subscribed_fields'], 'feed') && str_contains($r['subscribed_fields'], 'leadgen'));
        $this->assertTrue($page->fresh()->metadata['leadgen_subscribed']);

        // Hub catalog offers "Lead ads" for Meta (connected) and TikTok.
        $this->actingAs($this->rawda);
        $meta = collect(app(CapabilityCatalog::class)->forUser($this->rawda->id, 'meta'))->firstWhere('key', 'leads');
        $this->assertSame('connected', $meta['status'] ?? null);
        $this->assertNotNull(collect(app(CapabilityCatalog::class)->forUser($this->rawda->id, 'tiktok'))->firstWhere('key', 'leads'));
    }

    public function test_a_refused_leadgen_field_never_costs_the_page_its_feed_and_messenger_events(): void
    {
        $page = $this->metaPage(['pages_manage_posts', 'leads_retrieval']);
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::sequence()
            ->push(['error' => ['message' => '(#200) leadgen requires leads_retrieval']], 403)
            ->push(['success' => true])]);

        $this->assertSame(['ok' => true, 'leadgen' => false, 'error' => null], MetaLeads::subscribe($page));
        $sent = Http::recorded()->map(fn ($pair) => $pair[0]['subscribed_fields'])->all();
        $this->assertStringContainsString('leadgen', $sent[0]);
        $this->assertSame(\App\Services\PostServices\MetaPostService::PAGE_WEBHOOK_FIELDS, $sent[1]);

        // Without the permission, leadgen is never requested at all.
        $plain = $this->metaPage(['pages_manage_posts'], 'page-2');
        $this->assertStringNotContainsString('leadgen', MetaLeads::pageFields($plain));
    }

    public function test_meta_backfill_imports_form_leads_once(): void
    {
        $page = $this->metaPage(['leads_retrieval']);
        Http::fake([
            'graph.facebook.com/*/leadgen_forms*' => Http::response(['data' => [['id' => 'form-1', 'name' => 'Majlis']]]),
            'graph.facebook.com/*/form-1/leads*' => Http::response(['data' => [
                ['id' => 'L1', 'created_time' => '2026-11-10T10:00:00+0000', 'campaign_name' => 'Ramadan', 'platform' => 'fb',
                    'field_data' => [['name' => 'full_name', 'values' => ['Noura Q']], ['name' => 'phone_number', 'values' => ['+966552148830']], ['name' => 'city', 'values' => ['Riyadh']]]],
            ]]),
        ]);

        $this->assertSame(1, MetaLeads::sync($page)['created']);
        $this->assertSame(0, MetaLeads::sync($page)['created'], 'already in the CRM');
        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['meta:L1', 'facebook', 'Ramadan', $this->rawda->id], [$lead->external_lead_id, $lead->source, $lead->campaign, $lead->workspace_id]);
        $this->assertStringContainsString('city: Riyadh', $lead->interest);
    }

    public function test_tiktok_connect_subscribes_advertisers_and_the_webhook_creates_leads(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->rawda->id, 'platform' => 'tiktok', 'step' => TikTokDriver::BUSINESS, 'provider_account_id' => null,
            'access_token' => 'tt-token', 'capabilities' => ['ads'], 'status' => 'active']);
        $advertiser = SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'tiktok', 'platform_account_id' => 'adv-1', 'name' => 'Rawda Ads',
            'access_token' => 'tt-token', 'has_ads_permission' => true, 'social_connection_id' => $connection->id]);

        Http::fake(['business-api.tiktok.com/open_api/v1.3/subscription/subscribe/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['subscription_id' => 'sub-9']])]);
        $this->assertTrue(TikTokLeads::subscribe($advertiser)['ok']);
        Http::assertSent(fn ($r) => $r['subscribe_entity'] === 'LEAD' && $r['app_id'] === 'tt-app' && $r['subscription_detail']['advertiser_id'] === 'adv-1'
            && $r['callback_url'] === route('api.crm.tiktok.leads', TikTokLeads::callbackToken()));
        $this->assertSame('sub-9', $advertiser->fresh()->metadata['crm_lead_subscription_id']);
        $this->assertContains('leads', $connection->fresh()->capabilities);

        $payload = ['request_id' => 'r1', 'object' => 1, 'time' => time(), 'entry' => [[
            'id' => 'TL1', 'lead_source' => 'INSTANT_FORM', 'page_id' => 'form-7', 'advertiser_id' => 'adv-1', 'campaign_name' => 'Outdoor', 'ad_name' => 'Lounge',
            'changes' => [['field' => 'name', 'value' => 'Yousef Al-Shammari'], ['field' => 'phone_number', 'value' => '+966502149907'], ['field' => 'budget', 'value' => '10k']],
        ]]];
        $url = TikTokLeads::callbackUrl();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk(); // at-least-once delivery
        $this->postJson(route('api.crm.tiktok.leads', str_repeat('0', 40)), $payload)->assertNotFound();

        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
        $this->assertSame(['tiktok:TL1', 'tiktok', 'Outdoor', null], [$lead->external_lead_id, $lead->source, $lead->campaign, $lead->owner_id]);
        $this->assertSame('Yousef', Contact::withoutGlobalScopes()->find($lead->contact_id)->first_name);
        $this->assertStringContainsString('budget: 10k', $lead->interest);
    }

    public function test_tiktok_backfill_downloads_instant_form_leads(): void
    {
        $advertiser = SocialAccount::create(['user_id' => $this->rawda->id, 'platform' => 'tiktok', 'platform_account_id' => 'adv-1', 'name' => 'Rawda Ads',
            'access_token' => 'tt-token', 'has_ads_permission' => true, 'metadata' => ['crm_lead_subscription_id' => 'sub-9']]);
        Http::fake([
            '*/page/get/*' => Http::response(['code' => 0, 'data' => ['list' => [['page_id' => 'form-7', 'title' => 'Lounge form']]]]),
            '*/page/lead/task/download/*' => Http::response("lead_id,created_time,campaign_name,ad_name,name,phone_number,email\nTL5,2026-11-10,Outdoor,Lounge,Dana S,0559031187,dana@x.test\n", 200, ['Content-Type' => 'text/csv']),
            '*/page/lead/task/' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-1', 'status' => 'SUCCEED']]),
        ]);

        $this->assertSame(1, TikTokLeads::sync($advertiser, 0)['created']);
        $this->assertSame(0, TikTokLeads::sync($advertiser, 0)['created']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['tiktok:TL5', 'Outdoor'], [$lead->external_lead_id, $lead->campaign]);
        $this->assertSame('dana@x.test', $lead->contact->email);

        // From CRM settings, scoped to the workspace's own accounts.
        $this->actingAs($this->rawda)->getJson($this->url('settings.show'))->assertOk()->assertJsonPath('capture.tiktok_advertisers.0.subscribed', true);
        $this->actingAs($this->hala)->postJson($this->url('settings.leads'), ['action' => 'sync', 'account_id' => $advertiser->id])->assertNotFound();
    }
}
