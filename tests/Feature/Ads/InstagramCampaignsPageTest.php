<?php

namespace Tests\Feature\Ads;

use App\Mail\AdPerformanceReport;
use App\Models\Admin\AdCampaign;
use App\Models\AdReportSchedule;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Instagram campaigns page: the Meta ad account, with numbers narrowed to Instagram placements. */
class InstagramCampaignsPageTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private User $user;
    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.facebook.base_url', 'https://graph.facebook.com/v22.0/act_{accountId}');
        Settings::set('ads.facebook.client_secret', 'app-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 'owner@brand.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => '111', 'name' => 'Brand Ads', 'account_type' => 'ad_account',
            'access_token' => 'token', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
    }

    private function campaign(string $platform, string $remoteId, string $name): AdCampaign
    {
        return AdCampaign::create([
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => $platform, 'name' => $name,
            'status' => 'active', 'objective' => 'OUTCOME_SALES', 'ad_campaign_id' => $remoteId, 'daily_budget' => 100,
        ]);
    }

    /** Meta answers every insights call with an Instagram and a Facebook row, so the filter is what decides. */
    private function fakeMeta(): void
    {
        $row = fn (string $platform, array $extra) => ['publisher_platform' => $platform] + $extra;
        $purchase = fn ($n, $value) => ['actions' => [['action_type' => 'omni_purchase', 'value' => (string) $n]], 'action_values' => [['action_type' => 'omni_purchase', 'value' => (string) $value]]];
        Http::fake(['graph.facebook.com/v22.0/act_111/insights*' => function (HttpRequest $r) use ($row, $purchase) {
            return Http::response(['data' => match (true) {
                ($r['date_preset'] ?? null) === 'today' => [['spend' => '42.5']],
                ($r['level'] ?? null) === 'campaign' => [
                    $row('instagram', ['campaign_id' => '12001', 'objective' => 'OUTCOME_SALES', 'spend' => '300', 'impressions' => '40000', 'reach' => '20000', 'clicks' => '900', 'inline_link_clicks' => '800'] + $purchase(12, 1200)),
                    $row('facebook', ['campaign_id' => '12001', 'objective' => 'OUTCOME_SALES', 'spend' => '500', 'impressions' => '60000', 'reach' => '30000', 'clicks' => '1000', 'inline_link_clicks' => '900'] + $purchase(10, 800)),
                ],
                ($r['breakdowns'] ?? null) === 'publisher_platform,platform_position' => [
                    $row('instagram', ['platform_position' => 'reels', 'spend' => '200', 'impressions' => '25000'] + $purchase(9, 1000)),
                    $row('instagram', ['platform_position' => 'stream', 'spend' => '100', 'impressions' => '15000'] + $purchase(3, 200)),
                    $row('facebook', ['platform_position' => 'feed', 'spend' => '500', 'impressions' => '60000'] + $purchase(10, 800)),
                ],
                default => [
                    $row('instagram', ['date_start' => now()->subDay()->toDateString(), 'spend' => '300', 'impressions' => '40000', 'reach' => '18000', 'inline_link_clicks' => '800'] + $purchase(12, 1200)),
                    $row('facebook', ['date_start' => now()->subDay()->toDateString(), 'spend' => '500', 'impressions' => '60000', 'reach' => '28000', 'inline_link_clicks' => '900'] + $purchase(10, 800)),
                ],
            }]);
        }]);
    }

    public function test_the_page_shows_the_meta_account_with_campaigns_from_both_builders_and_the_instagram_identity(): void
    {
        $this->campaign('facebook', '12001', 'Made on Facebook');
        $this->campaign('instagram', '12002', 'Made on Instagram');
        $this->campaign('tiktok', '12003', 'TikTok');
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'instagram', 'platform_account_id' => 'ig1', 'name' => 'Social Eaz', 'username' => 'social.eaz', 'access_token' => 't', 'is_token_valid' => true]);

        $response = $this->get(route('admin.ads.campaigns.index', ['platform' => 'instagram']))->assertOk()->assertSee('meta-campaigns', false);
        $data = $response->viewData('data');

        $this->assertSame('instagram', $data['platform']);
        $this->assertSame($this->account->id, $data['account']['id']);
        $this->assertSame('social.eaz', $data['instagram']['username']);
        $this->assertEqualsCanonicalizing(['Made on Facebook', 'Made on Instagram'], array_column($data['campaigns'], 'name'));
        $this->assertStringContainsString('/instagram/', $data['urls']['insights']);
    }

    public function test_the_facebook_page_is_unchanged(): void
    {
        $this->campaign('facebook', '12001', 'Made on Facebook');
        $this->campaign('instagram', '12002', 'Made on Instagram');

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->assertOk()->assertSee('meta-campaigns', false)->viewData('data');

        $this->assertSame('facebook', $data['platform']);
        $this->assertNull($data['instagram']);
        $this->assertSame(['Made on Facebook'], array_column($data['campaigns'], 'name'));
    }

    public function test_insights_count_only_instagram_placements_and_keep_all_placement_spend_for_budgets(): void
    {
        $campaign = $this->campaign('facebook', '12001', 'Summer sale');
        $this->fakeMeta();

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'instagram', 'days' => 7]))->assertOk()->json();

        $this->assertSame('instagram', $res['platform']);
        $this->assertEquals(300, $res['totals']['current']['spend']);
        $this->assertEquals(800, $res['all_placements_spend']);
        $this->assertEquals(4, $res['totals']['current']['roas']);
        $this->assertEquals(12, $res['totals']['current']['purchases']);
        $this->assertEquals(300, $res['campaigns'][$campaign->id]['spend']);
        $this->assertSame(['instagram|reels', 'instagram|stream'], array_column($res['details']['placements'], 'key'));
        $this->assertEquals(1000, $res['details']['placements'][0]['purchase_value']);
        $this->assertEquals(300, end($res['details']['daily'])['spend']);
        $this->assertEquals(['spend' => 300, 'impressions' => 40000, 'reach' => 18000, 'link_clicks' => 800], array_intersect_key($res['details']['account'], array_flip(['spend', 'impressions', 'reach', 'link_clicks'])));
        $this->assertEquals(42.5, $res['details']['today_spend']);
        $this->assertNull($res['details']['regions']); // not available per platform
        Http::assertSent(fn (HttpRequest $r) => ($r['level'] ?? null) === 'campaign' && ($r['breakdowns'] ?? null) === 'publisher_platform');

        // The Facebook page keeps counting every placement.
        $fb = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'facebook', 'days' => 7]))->assertOk()->json();
        $this->assertNull($fb['platform']);
    }

    public function test_export_and_scheduled_reports_carry_the_instagram_numbers(): void
    {
        $this->campaign('facebook', '12001', 'Summer sale');
        $this->fakeMeta();
        Mail::fake();

        $response = $this->get(route('admin.ads.campaigns.export', ['platform' => 'instagram', 'days' => 7]))->assertOk();
        $this->assertStringContainsString('filename=instagram-ads-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('"Summer sale",300', $response->getContent());

        $schedule = AdReportSchedule::create([
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'instagram', 'name' => 'Weekly Instagram',
            'frequency' => 'weekly', 'weekday' => 0, 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7,
            'recipient_email' => 'owner@brand.com', 'locale' => 'en', 'status' => 'active', 'next_run_at' => now()->subMinute(),
        ]);
        $this->artisan('ads:send-reports')->assertSuccessful();

        Mail::assertSent(AdPerformanceReport::class, fn (AdPerformanceReport $m) => str_contains($m->report['csv'], '"Summer sale",300'));
        $this->assertNotNull($schedule->fresh()->last_sent_at);
        $this->assertSame([$schedule->id], array_column($this->get(route('admin.ads.campaigns.index', ['platform' => 'instagram']))->viewData('data')['reports'], 'id'));
        $this->assertSame([], $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->viewData('data')['reports']);
    }
}
