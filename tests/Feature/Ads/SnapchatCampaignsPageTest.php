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
 * Snapchat campaigns page: the Instagram dashboard (MetaCampaigns.vue) on the
 * selected Snap ad account, with the Measurement API faked
 * (GET adaccounts/{id}/stats, response shapes from Snap's docs) and
 * GET adaccounts/{id}/campaigns.
 */
class SnapchatCampaignsPageTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private const BASE = 'https://adsapi.snapchat.com/v1/';

    private User $user;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.snapchat.base_url', self::BASE);
        Cache::flush();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'snapchat', 'platform_account_id' => 'acc-1', 'name' => 'Socialeaz Snap', 'account_type' => 'ad_account',
            'access_token' => 'snap-token', 'expires_at' => now()->addMinutes(20), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
        $this->account->syncAdDetails(['currency' => 'SAR', 'timezone' => 'Asia/Riyadh']);
    }

    private function campaign(string $remoteId, string $name, array $extra = []): AdCampaign
    {
        return AdCampaign::create($extra + [
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'snapchat', 'name' => $name,
            'status' => true, 'objective' => 'SALES', 'ad_campaign_id' => $remoteId, 'budget_mode' => 'daily', 'budget' => 50,
        ]);
    }

    private static function micro(float $amount): int
    {
        return (int) round($amount * 1_000_000);
    }

    /** Current period: SAR 300 spent; previous: SAR 200. Rejects the extra fields when $rejectExtras. */
    private function fakeStats(bool $rejectExtras = false): void
    {
        Http::fake([self::BASE . 'adaccounts/acc-1/stats*' => function (HttpRequest $r) use ($rejectExtras) {
            if ($rejectExtras && str_contains($r['fields'], 'conversion_purchases')) {
                return Http::response(['request_status' => 'ERROR', 'display_message' => 'Invalid field'], 400);
            }
            $previous = str_starts_with($r['start_time'], now('Asia/Riyadh')->subDay()->startOfDay()->subDays(13)->toDateString());
            $full = ['impressions' => 50000, 'swipes' => 600, 'spend' => self::micro(300), 'uniques' => 28000, 'conversion_purchases' => 12, 'conversion_purchases_value' => self::micro(900)];
            $stats = $rejectExtras ? array_intersect_key($full, array_flip(['impressions', 'swipes', 'spend'])) : $full;
            $stat = fn (array $body) => Http::response(['request_status' => 'SUCCESS', 'total_stats' => [['sub_request_status' => 'SUCCESS', 'total_stat' => $body]]]);

            return match (true) {
                ($r['granularity'] ?? null) === 'DAY' => Http::response(['request_status' => 'SUCCESS', 'timeseries_stats' => [['timeseries_stat' => ['granularity' => 'DAY', 'timeseries' => [
                    ['start_time' => now('Asia/Riyadh')->subDay()->startOfDay()->format('Y-m-d\TH:i:s.vP'), 'stats' => array_diff_key($stats, ['uniques' => 1])],
                ]]]]]),
                ($r['breakdown'] ?? null) === 'campaign' => $stat(['id' => 'acc-1', 'type' => 'AD_ACCOUNT', 'breakdown_stats' => ['campaign' => [
                    ['id' => 'snap-c1', 'type' => 'CAMPAIGN', 'stats' => $previous ? ['impressions' => 30000, 'swipes' => 300, 'spend' => self::micro(200)] : $stats],
                ]]]),
                ($r['report_dimension'] ?? null) === 'age' => $stat(['dimension_stats' => [
                    ['impressions' => 30000, 'swipes' => 400, 'spend' => self::micro(200), 'conversion_purchases' => 8, 'age_bucket' => '18-20'],
                    ['impressions' => 20000, 'swipes' => 200, 'spend' => self::micro(100), 'conversion_purchases' => 4, 'age_bucket' => '35+'],
                ]]),
                ($r['report_dimension'] ?? null) === 'country' => $stat(['dimension_stats' => [
                    ['impressions' => 50000, 'swipes' => 600, 'spend' => self::micro(300), 'conversion_purchases' => 12, 'country' => 'sa'],
                ]]),
                $r['fields'] === 'spend' => $stat(['stats' => ['spend' => self::micro(41.5)]]),
                default => $stat(['stats' => $stats]),
            };
        }]);
    }

    public function test_the_page_uses_the_instagram_dashboard_for_the_snap_ad_account(): void
    {
        $this->campaign('snap-c1', 'Ramadan sales');
        $this->campaign('snap-c2', 'Lifetime flight', ['status' => false, 'budget_mode' => 'total', 'budget' => 900]);
        AdCampaign::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'name' => 'TikTok one', 'status' => true]);
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        AdCampaign::create(['user_id' => $other->id, 'platform' => 'snapchat', 'name' => 'Not mine', 'status' => true, 'ad_campaign_id' => 'x9']);

        $response = $this->get(route('admin.ads.campaigns.index', ['platform' => 'snapchat']))->assertOk()->assertSee('meta-campaigns', false);
        $data = $response->viewData('data');

        $this->assertSame('snapchat', $data['platform']);
        $this->assertSame('SAR', $data['account']['currency']);
        $this->assertTrue($data['account']['healthy']);
        $this->assertEqualsCanonicalizing(['Ramadan sales', 'Lifetime flight'], array_column($data['campaigns'], 'name'));
        $byName = collect($data['campaigns'])->keyBy('name');
        $this->assertSame(['active', 50.0, null], [$byName['Ramadan sales']['status'], $byName['Ramadan sales']['daily_budget'], $byName['Ramadan sales']['lifetime_budget']]);
        $this->assertSame(['paused', null, 900.0], [$byName['Lifetime flight']['status'], $byName['Lifetime flight']['daily_budget'], $byName['Lifetime flight']['lifetime_budget']]);
        $this->assertNull($data['urls']['duplicate']);
        $this->assertSame('https://ads.snapchat.com/', $data['urls']['meta']);
        $this->assertStringContainsString('/snapchat/', $data['urls']['insights']);
        $this->assertStringNotContainsString('snap-token', $response->getContent());
    }

    public function test_a_refreshable_account_stays_usable_after_the_short_access_token_expires(): void
    {
        $this->account->forceFill(['expires_at' => now()->subMinutes(5), 'refresh_token' => 'refresh'])->save();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'snapchat']))->assertOk()->viewData('data');

        $this->assertTrue($data['account']['healthy']);
        $this->assertTrue($data['can_manage']);
    }

    public function test_insights_have_the_meta_shape_from_the_measurement_api(): void
    {
        $campaign = $this->campaign('snap-c1', 'Ramadan sales');
        $this->fakeStats();

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'snapchat', 'days' => 7]))->assertOk()->json();

        $this->assertSame('snapchat', $res['platform']);
        $c = $res['totals']['current'];
        $this->assertEquals([300, 50000, 600, 12, 28000], [$c['spend'], $c['impressions'], $c['clicks'], $c['conversions'], $c['reach']]); // micro → SAR
        $this->assertSame('purchase', $c['result_type']); // sales campaigns count purchases
        $this->assertEquals(12, $c['results']);
        $this->assertEquals(900, $c['purchase_value']);
        $this->assertEquals(3, $c['roas']);
        $this->assertEquals(200, $res['totals']['previous']['spend']);
        $this->assertEquals(300, $res['campaigns'][$campaign->id]['spend']);
        $this->assertCount(7, $res['details']['daily']);
        $this->assertEquals(300, end($res['details']['daily'])['spend']);
        $this->assertEquals(28000, $res['details']['account']['reach']);
        $this->assertSame(['18-20', '35+'], array_column($res['details']['ages'], 'key'));
        $this->assertSame(['SA'], array_column($res['details']['regions'], 'key'));
        $this->assertNull($res['details']['placements']); // Snap has no placement breakdown
        $this->assertNotNull($this->account->fresh()->metadata['insights_synced_at'] ?? null);

        // Daily stats start on the ad account's own day boundary (Riyadh, +03:00).
        Http::assertSent(fn (HttpRequest $r) => ($r['granularity'] ?? null) === 'DAY' && str_ends_with($r['start_time'], 'T00:00:00.000+03:00')
            && $r->hasHeader('Authorization', 'Bearer snap-token') && ! str_contains($r['fields'], 'uniques'));
        Http::assertSent(fn (HttpRequest $r) => ($r['breakdown'] ?? null) === 'campaign' && $r['granularity'] === 'TOTAL');
    }

    public function test_core_numbers_still_load_when_snap_rejects_the_extra_fields(): void
    {
        $campaign = $this->campaign('snap-c1', 'Ramadan sales');
        $this->fakeStats(rejectExtras: true);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'snapchat', 'days' => 7]))->assertOk()->json();

        $this->assertEquals(300, $res['totals']['current']['spend']);
        // Purchases unknown, so not counted as zero.
        $this->assertNull($res['campaigns'][$campaign->id]['results']);
        $this->assertNull($res['totals']['current']['results']);
        $this->assertNull($res['totals']['current']['roas']);
    }

    public function test_an_auth_failure_asks_to_reconnect(): void
    {
        Http::fake([self::BASE . '*' => Http::response(['request_status' => 'ERROR', 'display_message' => 'Unauthorized'], 401)]);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'snapchat', 'days' => 7]))->assertStatus(422)->json();

        $this->assertTrue($res['reconnect']);
    }

    public function test_sync_imports_the_ad_accounts_campaigns_for_this_seller_only(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'snapchat', 'name' => 'Theirs', 'status' => false, 'ad_campaign_id' => 'snap-c1']);
        Http::fake([self::BASE . 'adaccounts/acc-1/campaigns*' => Http::response(['request_status' => 'SUCCESS', 'campaigns' => [
            ['sub_request_status' => 'SUCCESS', 'campaign' => ['id' => 'snap-c1', 'name' => 'Ramadan sales', 'status' => 'ACTIVE', 'daily_budget_micro' => self::micro(80), 'objective_v2_properties' => ['objective_v2_type' => 'SALES']]],
            ['sub_request_status' => 'SUCCESS', 'campaign' => ['id' => 'snap-c2', 'name' => 'Brand reach', 'status' => 'PAUSED', 'lifetime_spend_cap_micro' => self::micro(1000), 'objective_v2_properties' => ['objective_v2_type' => 'AWARENESS_AND_ENGAGEMENT']]],
            ['sub_request_status' => 'SUCCESS', 'campaign' => ['id' => 'snap-c3', 'name' => 'Gone', 'status' => 'PAUSED', 'deleted' => true]],
        ]])]);

        $this->postJson(route('admin.ads.campaigns.sync', ['platform' => 'snapchat']))->assertOk()->assertJsonPath('data.synced', 2);

        $mine = AdCampaign::where('user_id', $this->user->id)->where('platform', 'snapchat')->orderBy('ad_campaign_id')->get();
        $this->assertSame(['Ramadan sales', 'Brand reach'], $mine->pluck('name')->all());
        $this->assertSame([true, false], $mine->pluck('status')->map(fn ($s) => (bool) $s)->all());
        $this->assertEquals(80, $mine[0]->daily_budget);
        $this->assertEquals(1000, $mine[1]->budget);
        $this->assertSame('Theirs', $foreign->fresh()->name);

        $rows = collect($this->get(route('admin.ads.campaigns.index', ['platform' => 'snapchat']))->viewData('data')['campaigns'])->keyBy('name');
        $this->assertSame([80.0, null], [$rows['Ramadan sales']['daily_budget'], $rows['Ramadan sales']['lifetime_budget']]);
        $this->assertSame([null, 1000.0], [$rows['Brand reach']['daily_budget'], $rows['Brand reach']['lifetime_budget']]);
    }

    public function test_status_and_delete_only_reach_the_sellers_own_campaigns_and_snap_refusals_are_errors(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = AdCampaign::create(['user_id' => $other->id, 'platform' => 'snapchat', 'name' => 'Theirs', 'status' => true, 'ad_campaign_id' => 'x9']);
        $mine = $this->campaign('snap-c1', 'Ramadan sales');
        Http::fake([self::BASE . '*' => Http::response(['request_status' => 'ERROR', 'debug_message' => 'No permission'], 403)]);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'snapchat', 'id' => $foreign->id]), ['status' => 'PAUSED'])->assertNotFound();
        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'snapchat', 'id' => $mine->id]), ['status' => 'PAUSED'])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue((bool) $mine->fresh()->status);
        $this->delete(route('admin.ads.campaigns.destroy', ['platform' => 'snapchat', 'campaign' => $foreign->id]))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    public function test_export_and_the_performance_endpoint_carry_snapchat_spend(): void
    {
        $this->campaign('snap-c1', 'Ramadan sales');
        $this->fakeStats();

        $perf = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'snapchat', 'days' => 7]))->assertOk()->json();
        $this->assertSame('ok', $perf['status']);
        $this->assertEquals(300, $perf['totals']['current']['spend']);
        $this->assertEquals(900, $perf['totals']['current']['value']);

        $csv = $this->get(route('admin.ads.campaigns.export', ['platform' => 'snapchat', 'days' => 7]))->assertOk()->getContent();
        $this->assertStringContainsString('"Amount spent",300', $csv);
        $this->assertStringContainsString('"Ramadan sales",SALES', $csv);
        $this->assertStringNotContainsString('isn’t available for Snapchat', $csv);
    }

    public function test_without_a_snap_account_the_page_asks_to_connect_and_nothing_is_called(): void
    {
        $this->account->delete();
        Http::fake();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'snapchat']))->assertOk()->viewData('data');
        $this->assertNull($data['account']);
        $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'snapchat']))->assertStatus(422);
        Http::assertNothingSent();
    }
}
