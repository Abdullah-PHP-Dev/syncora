<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * TikTok campaigns page: the Instagram dashboard (MetaCampaigns.vue) on the
 * selected TikTok advertiser, with TikTok's report API faked
 * (report/integrated/get/, campaign/get/).
 */
class TiktokCampaignsPageTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private const BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    private User $user;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.tiktok.base_url', self::BASE);
        Cache::flush();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => '7001', 'name' => 'Socialeaz_adv', 'account_type' => 'ad_account',
            'access_token' => 'tt-token', 'expires_at' => now()->addYear(), 'is_token_valid' => true, 'has_ads_permission' => true,
            'metadata' => ['currency' => 'SAR'],
        ]);
    }

    private function campaign(string $remoteId, string $name, array $extra = []): AdCampaign
    {
        return AdCampaign::create($extra + [
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'tiktok', 'name' => $name,
            'status' => true, 'objective' => 'TRAFFIC', 'ad_campaign_id' => $remoteId, 'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 100,
        ]);
    }

    private static function row(array $dimensions, array $metrics): array
    {
        return ['dimensions' => $dimensions, 'metrics' => array_map('strval', $metrics)];
    }

    /** Current period: 300 spent; previous period: 200. Rejects the extra metrics when $rejectExtras. */
    private function fakeReports(bool $rejectExtras = false): void
    {
        $previousStart = now()->subDay()->startOfDay()->subDays(6)->subDays(7)->toDateString();

        Http::fake([self::BASE . 'report/integrated/get/*' => function (HttpRequest $r) use ($rejectExtras, $previousStart) {
            $metrics = json_decode($r['metrics'], true);
            if ($rejectExtras && in_array('complete_payment_roas', $metrics, true)) {
                return Http::response(['code' => 40002, 'message' => 'Invalid metric', 'data' => []]);
            }
            $dimensions = json_decode($r['dimensions'], true);
            $previous = $r['start_date'] === $previousStart;
            $roas = in_array('complete_payment_roas', $metrics, true) ? ['complete_payment_roas' => 2.5] : [];

            $list = match (true) {
                $dimensions === ['campaign_id'] => [self::row(['campaign_id' => '1801'], ['spend' => $previous ? 200 : 300, 'impressions' => 50000, 'clicks' => 600, 'reach' => 30000, 'conversion' => 12] + $roas)],
                $dimensions === ['stat_time_day'] => [self::row(['stat_time_day' => now()->subDay()->toDateString() . ' 00:00:00'], ['spend' => 300, 'impressions' => 50000, 'clicks' => 600, 'conversion' => 12] + $roas)],
                $dimensions === ['advertiser_id'] && $r['start_date'] === today()->toDateString() => [self::row(['advertiser_id' => '7001'], ['spend' => 41.5])],
                $dimensions === ['advertiser_id'] => [self::row(['advertiser_id' => '7001'], ['spend' => 300, 'impressions' => 50000, 'clicks' => 600, 'reach' => 28000, 'conversion' => 12] + $roas)],
                $dimensions === ['advertiser_id', 'age'] => [
                    self::row(['advertiser_id' => '7001', 'age' => 'AGE_18_24'], ['spend' => 200, 'impressions' => 30000, 'clicks' => 400, 'conversion' => 8]),
                    self::row(['advertiser_id' => '7001', 'age' => 'AGE_55_100'], ['spend' => 100, 'impressions' => 20000, 'clicks' => 200, 'conversion' => 4]),
                ],
                $dimensions === ['advertiser_id', 'country_code'] => [self::row(['advertiser_id' => '7001', 'country_code' => 'SA'], ['spend' => 300, 'impressions' => 50000, 'clicks' => 600, 'conversion' => 12])],
                $dimensions === ['advertiser_id', 'placement'] => [self::row(['advertiser_id' => '7001', 'placement' => 'PLACEMENT_TIKTOK'], ['spend' => 300, 'impressions' => 50000, 'clicks' => 600, 'conversion' => 12])],
                default => [],
            };

            return Http::response(['code' => 0, 'message' => 'OK', 'data' => ['list' => $list, 'page_info' => ['page' => 1, 'total_page' => 1]]]);
        }]);
    }

    public function test_the_page_uses_the_instagram_dashboard_for_the_tiktok_advertiser(): void
    {
        $this->campaign('1801', 'Ramadan traffic');
        $this->campaign('1802', 'Lifetime flight', ['status' => false, 'budget_mode' => 'BUDGET_MODE_TOTAL', 'budget' => 900]);
        AdCampaign::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'name' => 'Facebook one', 'status' => 'active']);
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        AdCampaign::create(['user_id' => $other->id, 'platform' => 'tiktok', 'name' => 'Not mine', 'status' => true, 'ad_campaign_id' => '9999']);

        $response = $this->get(route('admin.ads.campaigns.index', ['platform' => 'tiktok']))->assertOk()->assertSee('meta-campaigns', false);
        $data = $response->viewData('data');

        $this->assertSame('tiktok', $data['platform']);
        $this->assertSame($this->account->id, $data['account']['id']);
        $this->assertSame('SAR', $data['account']['currency']);
        $this->assertTrue($data['can_manage']);
        $this->assertEqualsCanonicalizing(['Ramadan traffic', 'Lifetime flight'], array_column($data['campaigns'], 'name'));
        $byName = collect($data['campaigns'])->keyBy('name');
        $this->assertSame(['active', 100.0, null], [$byName['Ramadan traffic']['status'], $byName['Ramadan traffic']['daily_budget'], $byName['Ramadan traffic']['lifetime_budget']]);
        $this->assertSame(['paused', null, 900.0], [$byName['Lifetime flight']['status'], $byName['Lifetime flight']['daily_budget'], $byName['Lifetime flight']['lifetime_budget']]);
        $this->assertSame(['total' => 2, 'active' => 1, 'paused' => 1], array_intersect_key($data['summary'], array_flip(['total', 'active', 'paused'])));
        $this->assertNull($data['urls']['duplicate']); // TikTok has no copy endpoint
        $this->assertStringContainsString('/tiktok/', $data['urls']['insights']);
        $this->assertSame('TikTok Ads', $response->viewData('messages')['meta']['scope']['tiktok']['title']);
        $this->assertStringNotContainsString('tt-token', $response->getContent());
    }

    public function test_insights_have_the_meta_shape_from_the_tiktok_report_api(): void
    {
        $campaign = $this->campaign('1801', 'Ramadan traffic');
        $this->fakeReports();

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'tiktok', 'days' => 7]))->assertOk()->json();

        $this->assertSame('tiktok', $res['platform']);
        $this->assertSame('SAR', $res['currency']);
        $c = $res['totals']['current'];
        $this->assertEquals([300, 50000, 600, 12], [$c['spend'], $c['impressions'], $c['clicks'], $c['conversions']]);
        $this->assertSame('link_click', $c['result_type']); // traffic campaigns count clicks
        $this->assertEquals(600, $c['results']);
        $this->assertEquals(750, $c['purchase_value']); // ROAS 2.5 × 300
        $this->assertEquals(2.5, $c['roas']);
        $this->assertEquals(200, $res['totals']['previous']['spend']);
        $this->assertEquals(300, $res['campaigns'][$campaign->id]['spend']);
        $this->assertCount(7, $res['details']['daily']);
        $this->assertEquals(300, end($res['details']['daily'])['spend']);
        $this->assertEquals(28000, $res['details']['account']['reach']);
        $this->assertSame(['18-24', '55+'], array_column($res['details']['ages'], 'key'));
        $this->assertSame(['SA'], array_column($res['details']['regions'], 'key'));
        $this->assertSame(['PLACEMENT_TIKTOK'], array_column($res['details']['placements'], 'key'));
        $this->assertEquals(41.5, $res['details']['today_spend']);
        $this->assertNotNull($this->account->fresh()->metadata['insights_synced_at'] ?? null);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Access-Token', 'tt-token') && $r['advertiser_id'] === '7001'
            && $r['report_type'] === 'BASIC' && $r['data_level'] === 'AUCTION_CAMPAIGN' && $r['service_type'] === 'AUCTION');
        Http::assertSent(fn (HttpRequest $r) => $r['report_type'] === 'AUDIENCE' && $r['dimensions'] === '["advertiser_id","age"]');
    }

    public function test_core_numbers_still_load_when_tiktok_rejects_the_extra_metrics(): void
    {
        $this->campaign('1801', 'Ramadan traffic');
        $this->fakeReports(rejectExtras: true);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'tiktok', 'days' => 7]))->assertOk()->json();

        $this->assertEquals(300, $res['totals']['current']['spend']);
        $this->assertEquals(0, $res['totals']['current']['purchase_value']);
        $this->assertNull($res['totals']['current']['roas']); // nothing invented
    }

    public function test_an_expired_token_is_reported_not_hidden(): void
    {
        Http::fake([self::BASE . '*' => Http::response(['code' => 40105, 'message' => 'Access token is invalid', 'data' => []])]);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'tiktok', 'days' => 7]))->assertStatus(422)->json();

        $this->assertFalse($res['success']);
        $this->assertTrue($res['reconnect']);
    }

    public function test_sync_imports_the_advertisers_campaigns_for_this_seller_only(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'tiktok', 'name' => 'Theirs', 'status' => false, 'ad_campaign_id' => '1801']);
        Http::fake([self::BASE . 'campaign/get/*' => Http::response(['code' => 0, 'data' => [
            'list' => [
                ['campaign_id' => '1801', 'campaign_name' => 'Ramadan traffic', 'objective_type' => 'TRAFFIC', 'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 150, 'operation_status' => 'ENABLE'],
                ['campaign_id' => '1802', 'campaign_name' => 'Awareness', 'objective_type' => 'REACH', 'budget_mode' => 'BUDGET_MODE_INFINITE', 'budget' => 0, 'operation_status' => 'DISABLE'],
            ],
            'page_info' => ['page' => 1, 'total_page' => 1],
        ]])]);

        $this->postJson(route('admin.ads.campaigns.sync', ['platform' => 'tiktok']))->assertOk()->assertJsonPath('data.synced', 2);

        $mine = AdCampaign::where('user_id', $this->user->id)->where('platform', 'tiktok')->orderBy('ad_campaign_id')->get();
        $this->assertSame(['Ramadan traffic', 'Awareness'], $mine->pluck('name')->all());
        $this->assertEquals([150, null], $mine->pluck('budget')->map(fn ($b) => $b === null ? null : (float) $b)->all());
        $this->assertSame($this->account->id, (int) $mine[0]->social_account_id);
        $this->assertSame('Theirs', $foreign->fresh()->name);
        $this->assertNotNull($this->account->fresh()->adDetails->last_synced_at);
    }

    public function test_status_changes_only_reach_the_sellers_own_campaigns_and_tiktok_refusals_are_errors(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'tiktok', 'name' => 'Theirs', 'status' => true, 'ad_campaign_id' => '9999']);
        $mine = $this->campaign('1801', 'Ramadan traffic');
        Http::fake([self::BASE . '*' => Http::response(['code' => 40001, 'message' => 'No permission', 'data' => []])]);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'tiktok', 'id' => $foreign->id]), ['status' => 'PAUSED'])->assertNotFound();
        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'tiktok', 'id' => $mine->id]), ['status' => 'PAUSED'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue((bool) $mine->fresh()->status);
        $this->delete(route('admin.ads.campaigns.destroy', ['platform' => 'tiktok', 'campaign' => $foreign->id]))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    public function test_export_and_the_performance_endpoint_carry_tiktok_spend(): void
    {
        $this->campaign('1801', 'Ramadan traffic');
        $this->fakeReports();

        $perf = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'tiktok', 'days' => 7]))->assertOk()->json();
        $this->assertSame('ok', $perf['status']);
        $this->assertEquals(300, $perf['totals']['current']['spend']);

        $csv = $this->get(route('admin.ads.campaigns.export', ['platform' => 'tiktok', 'days' => 7]))->assertOk()->getContent();
        $this->assertStringContainsString('"Amount spent",300', $csv);
        $this->assertStringContainsString('"Ramadan traffic",TRAFFIC', $csv);
        $this->assertStringNotContainsString('isn’t available for TikTok', $csv);
    }

    public function test_without_a_tiktok_account_the_page_asks_to_connect_and_nothing_is_called(): void
    {
        $this->account->delete();
        Http::fake();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'tiktok']))->assertOk()->viewData('data');
        $this->assertNull($data['account']);
        $this->assertFalse($data['can_manage']);
        $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'tiktok']))->assertStatus(422);
        Http::assertNothingSent();
    }
}
