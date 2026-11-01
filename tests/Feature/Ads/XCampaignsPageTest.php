<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * X campaigns page: the Instagram dashboard (MetaCampaigns.vue) on the
 * selected X ad account, with the synchronous analytics endpoint faked
 * (GET stats/accounts/:id, v12 - at most 7 days and 20 entities per call)
 * and GET accounts/:id/campaigns + line_items.
 */
class XCampaignsPageTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private const BASE = 'https://ads-api.x.com/12/';

    private User $user;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.x.base_url', self::BASE);
        Settings::set('ads.x.consumer_key', 'ck');
        Settings::set('ads.x.consumer_secret', 'cs');
        Cache::flush();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'acc1', 'name' => 'Acme Ads', 'account_type' => 'ad_account',
            'access_token' => 'x-token', 'is_token_valid' => true, 'has_ads_permission' => true,
            'metadata' => ['legacy_token_secret' => 'secret', 'profile_id' => '555'],
        ]);
        $this->account->syncAdDetails(['currency' => 'SAR', 'timezone' => 'Asia/Riyadh']);
    }

    private function campaign(string $remoteId, string $name, array $extra = []): AdCampaign
    {
        return AdCampaign::create($extra + [
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'x', 'name' => $name,
            'status' => true, 'objective' => 'WEBSITE_CLICKS', 'ad_campaign_id' => $remoteId, 'budget_mode' => 'daily', 'daily_budget' => 50,
        ]);
    }

    /**
     * Every day: SAR 10 spent, 1,000 impressions, 20 link clicks, 1 purchase per entity.
     * Rejects WEB_CONVERSION when $rejectExtras. Fails if a call breaks X's limits.
     */
    private function fakeStats(bool $rejectExtras = false): void
    {
        Http::fake([self::BASE . 'stats/accounts/acc1*' => function (HttpRequest $r) use ($rejectExtras) {
            $start = Carbon::parse($r['start_time']);
            $end = Carbon::parse($r['end_time']);
            $ids = explode(',', $r['entity_ids']);
            if ($start->diffInHours($end) > 7 * 24 || count($ids) > 20 || $start->minute || $end->minute) {
                return Http::response(['errors' => [['code' => 'INVALID_PARAMETER', 'message' => 'Range or entities out of bounds']]], 400);
            }
            if ($rejectExtras && str_contains($r['metric_groups'], 'WEB_CONVERSION')) {
                return Http::response(['errors' => [['code' => 'INVALID_PARAMETER', 'message' => 'metric_groups']]], 400);
            }
            $length = $r['granularity'] === 'TOTAL' ? 1 : (int) round($start->diffInHours($end) / 24);
            $series = fn ($v) => array_fill(0, $length, $v);
            $metrics = ['billed_charge_local_micro' => $series(10_000_000), 'impressions' => $series(1000), 'url_clicks' => $series(20), 'engagements' => $series(40), 'video_total_views' => $series(5)];
            if (str_contains($r['metric_groups'], 'WEB_CONVERSION')) {
                $metrics['conversion_purchases'] = ['metric' => $series(1)];
            }
            if ($r['granularity'] === 'TOTAL') {
                $metrics = ['billed_charge_local_micro' => [4_500_000]];
            }

            return Http::response(['data_type' => 'stats', 'time_series_length' => $length, 'data' => array_map(fn ($id) => ['id' => $id, 'id_data' => [['segment' => null, 'metrics' => $metrics]]], $ids)]);
        }]);
    }

    public function test_the_page_uses_the_instagram_dashboard_for_the_x_ad_account(): void
    {
        $this->campaign('c1', 'Ramadan traffic');
        $this->campaign('c2', 'Launch flight', ['status' => false, 'budget_mode' => 'total', 'daily_budget' => null, 'budget' => 900]);
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        AdCampaign::create(['user_id' => $other->id, 'platform' => 'x', 'name' => 'Not mine', 'status' => true, 'ad_campaign_id' => 'z9']);

        $response = $this->get(route('admin.ads.campaigns.index', ['platform' => 'x']))->assertOk()->assertSee('meta-campaigns', false);
        $data = $response->viewData('data');

        $this->assertSame('x', $data['platform']);
        $this->assertSame('SAR', $data['account']['currency']);
        $this->assertTrue($data['can_manage']);
        $byName = collect($data['campaigns'])->keyBy('name');
        $this->assertEqualsCanonicalizing(['Ramadan traffic', 'Launch flight'], $byName->keys()->all());
        $this->assertSame(['active', 50.0, null], [$byName['Ramadan traffic']['status'], $byName['Ramadan traffic']['daily_budget'], $byName['Ramadan traffic']['lifetime_budget']]);
        $this->assertSame(['paused', null, 900.0], [$byName['Launch flight']['status'], $byName['Launch flight']['daily_budget'], $byName['Launch flight']['lifetime_budget']]);
        $this->assertNull($data['urls']['duplicate']);
        $this->assertStringContainsString('/x/', $data['urls']['insights']);
        $this->assertSame('X Ads', $response->viewData('messages')['meta']['scope']['x']['title']);
        $this->assertStringNotContainsString('x-token', $response->getContent());
    }

    public function test_insights_cover_long_ranges_in_7_day_requests_and_leave_unreported_parts_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:30', 'Asia/Riyadh')); // today has completed hours
        $campaign = $this->campaign('c1', 'Ramadan traffic');
        $this->fakeStats();

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'x', 'days' => 30]))->assertOk()->json();

        $this->assertSame('x', $res['platform']);
        $c = $res['totals']['current'];
        $this->assertEquals([300, 30000, 600, 30], [$c['spend'], $c['impressions'], $c['clicks'], $c['conversions']]); // 30 days × daily numbers, micro → SAR
        $this->assertSame('link_click', $c['result_type']); // website clicks campaigns count link clicks
        $this->assertEquals(600, $c['results']);
        $this->assertNull($c['roas']); // purchase value isn't read
        $this->assertEquals(300, $res['totals']['previous']['spend']);
        $this->assertEquals(300, $res['campaigns'][$campaign->id]['spend']);
        $this->assertCount(30, $res['details']['daily']);
        $this->assertEquals([10, 20], [$res['details']['daily'][0]['spend'], $res['details']['daily'][0]['link_clicks']]);
        $this->assertNull($res['details']['account']['reach']); // not in X's synchronous stats
        $this->assertNull($res['details']['ages']);
        $this->assertNull($res['details']['regions']);
        $this->assertNull($res['details']['placements']);
        $this->assertEquals(4.5, $res['details']['today_spend']);

        // DAY ranges start on the account's midnight (Riyadh = 21:00 UTC) and stay within 7 days.
        Http::assertSent(fn (HttpRequest $r) => $r['granularity'] === 'DAY' && $r['entity'] === 'CAMPAIGN' && str_ends_with($r['start_time'], 'T21:00:00Z')
            && $r['placement'] === 'ALL_ON_TWITTER' && str_contains($r['metric_groups'], 'BILLING'));
        Http::assertNotSent(fn (HttpRequest $r) => Carbon::parse($r['start_time'])->diffInHours(Carbon::parse($r['end_time'])) > 168);
        Carbon::setTestNow();
    }

    public function test_campaigns_are_requested_twenty_at_a_time(): void
    {
        foreach (range(1, 25) as $n) {
            $this->campaign('c' . $n, 'Campaign ' . $n);
        }
        $this->fakeStats();

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'x', 'days' => 7]))->assertOk()->json();

        $this->assertCount(25, $res['campaigns']);
        $this->assertEquals(25 * 70, $res['totals']['current']['spend']);
    }

    public function test_core_numbers_still_load_when_x_rejects_web_conversions(): void
    {
        $this->campaign('c1', 'Ramadan traffic');
        $this->fakeStats(rejectExtras: true);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'x', 'days' => 7]))->assertOk()->json();

        $this->assertEquals(70, $res['totals']['current']['spend']);
        $this->assertEquals(0, $res['totals']['current']['conversions']);
    }

    public function test_sync_imports_campaigns_with_their_objective_for_this_seller_only(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'x', 'name' => 'Theirs', 'status' => false, 'ad_campaign_id' => 'c1']);
        Http::fake([
            self::BASE . 'accounts/acc1/line_items*' => Http::response(['data' => [['id' => 'l1', 'campaign_id' => 'c1', 'objective' => 'WEBSITE_CLICKS']]]),
            self::BASE . 'accounts/acc1/campaigns*' => Http::response(['data' => [
                ['id' => 'c1', 'name' => 'Ramadan traffic', 'entity_status' => 'ACTIVE', 'daily_budget_amount_local_micro' => 80_000_000, 'funding_instrument_id' => 'f1'],
                ['id' => 'c2', 'name' => 'Old', 'entity_status' => 'PAUSED', 'deleted' => true],
            ]]),
        ]);

        $this->postJson(route('admin.ads.campaigns.sync', ['platform' => 'x']))->assertOk()->assertJsonPath('data.synced', 1);

        $mine = AdCampaign::where('user_id', $this->user->id)->where('platform', 'x')->sole();
        $this->assertSame(['Ramadan traffic', 'WEBSITE_CLICKS', 'f1', true], [$mine->name, $mine->objective, $mine->funding_instrument_id, (bool) $mine->status]);
        $this->assertEquals(80, $mine->daily_budget);
        $this->assertSame('Theirs', $foreign->fresh()->name);
    }

    public function test_status_and_delete_only_reach_the_sellers_own_campaigns_and_x_refusals_are_errors(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'x', 'name' => 'Theirs', 'status' => true, 'ad_campaign_id' => 'z9']);
        $mine = $this->campaign('c1', 'Ramadan traffic');
        Http::fake([self::BASE . '*' => Http::response(['errors' => [['code' => 'UNAUTHORIZED_ACCESS', 'message' => 'No access']]], 403)]);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'x', 'id' => $foreign->id]), ['status' => 'PAUSED'])->assertNotFound();
        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'x', 'id' => $mine->id]), ['status' => 'PAUSED'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue((bool) $mine->fresh()->status);
        $this->delete(route('admin.ads.campaigns.destroy', ['platform' => 'x', 'campaign' => $foreign->id]))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    public function test_export_and_the_performance_endpoint_carry_x_spend(): void
    {
        $this->campaign('c1', 'Ramadan traffic');
        $this->fakeStats();

        $perf = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'x', 'days' => 7]))->assertOk()->json();
        $this->assertSame('ok', $perf['status']);
        $this->assertEquals(70, $perf['totals']['current']['spend']);

        $csv = $this->get(route('admin.ads.campaigns.export', ['platform' => 'x', 'days' => 7]))->assertOk()->getContent();
        $this->assertStringContainsString('"Amount spent",70', $csv);
        $this->assertStringContainsString('"Ramadan traffic",WEBSITE_CLICKS', $csv);
        $this->assertStringNotContainsString('isn’t available for X', $csv);
    }

    public function test_without_an_x_account_the_page_asks_to_connect_and_nothing_is_called(): void
    {
        $this->account->delete();
        Http::fake();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'x']))->assertOk()->viewData('data');
        $this->assertNull($data['account']);
        $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'x']))->assertStatus(422);
        Http::assertNothingSent();
    }
}
