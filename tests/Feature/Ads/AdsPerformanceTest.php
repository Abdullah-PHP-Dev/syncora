<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Google\ReportRange;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Ads Dashboard reporting: real spend/results from reporting platforms only, nothing invented for the rest. */
class AdsPerformanceTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function account(string $platform, string $externalId): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => $platform, 'platform_account_id' => $externalId, 'name' => ucfirst($platform) . ' Ads',
            'access_token' => 'token-' . $platform, 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
    }

    public function test_google_ads_results_are_mapped_to_local_campaigns_and_other_platforms_report_nothing(): void
    {
        $this->account('google', '123-456-7890');
        $this->account('instagram', '555'); // no Meta ad account to report it through
        $local = AdCampaign::create(['user_id' => $this->user->id, 'platform' => 'google', 'name' => 'Search KSA', 'status' => 'ENABLED', 'ad_campaign_id' => 'customers/1234567890/campaigns/9', 'daily_budget' => 50]);
        $range = ReportRange::make('7d');
        [$start] = $range->current();
        Http::fake(function (HttpRequest $request) use ($start) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'FROM customer')) {
                return Http::response(['results' => [['customer' => ['currencyCode' => 'SAR'], 'segments' => ['date' => $start], 'metrics' => ['costMicros' => '120000000', 'clicks' => '40', 'impressions' => '2000', 'conversions' => 4.0, 'conversionsValue' => 480.0]]]]);
            }
            if (str_contains($query, 'FROM campaign')) {
                return Http::response(['results' => [['campaign' => ['id' => '9', 'name' => 'Search KSA', 'status' => 'ENABLED'], 'metrics' => ['costMicros' => '120000000', 'clicks' => '40', 'impressions' => '2000', 'conversions' => 4.0, 'conversionsValue' => 480.0]]]]);
            }

            return Http::response(['results' => [['conversionAction' => ['id' => '1']]]]);
        });

        $data = $this->getJson(route('admin.ads.dashboard.performance', ['days' => 7]))->assertOk()->json();

        $this->assertSame('ok', $data['platforms']['google']['status']);
        $this->assertSame('SAR', $data['platforms']['google']['currency']);
        $this->assertEquals(120, $data['platforms']['google']['totals']['current']['spend']);
        $this->assertEquals(480, $data['platforms']['google']['totals']['current']['value']);
        $this->assertCount(7, $data['platforms']['google']['trend']['dates']);
        $this->assertEquals(['spend' => 120, 'clicks' => 40, 'impressions' => 2000, 'conversions' => 4, 'value' => 480], $data['campaign_metrics'][$local->id]);
        // Connected but without reporting: said plainly, no numbers.
        $this->assertSame(['status' => 'no_reporting'], $data['platforms']['instagram']);
        $this->assertSame(['status' => 'not_connected'], $data['platforms']['tiktok']);
    }

    public function test_meta_spend_is_on_the_dashboard_and_instagram_is_counted_inside_it_once(): void
    {
        \App\Support\Settings::set('ads.facebook.base_url', 'https://graph.facebook.com/v25.0/act_{accountId}');
        \App\Support\Settings::set('ads.facebook.client_secret', 'app-secret');
        $meta = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => '111', 'name' => 'Meta Ads', 'account_type' => 'ad_account',
            'access_token' => 'token-111', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
        $meta->syncAdDetails(['currency' => 'SAR']);
        $fb = AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $meta->id, 'platform' => 'facebook', 'name' => 'Leads', 'status' => 'ACTIVE', 'objective' => 'OUTCOME_LEADS', 'ad_campaign_id' => '12001']);
        $ig = AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $meta->id, 'platform' => 'instagram', 'name' => 'Reels', 'status' => 'ACTIVE', 'objective' => 'OUTCOME_SALES', 'ad_campaign_id' => '12002']);
        Http::fake(['graph.facebook.com/*' => function (HttpRequest $r) {
            if (($r['level'] ?? '') !== 'campaign') {
                return Http::response(['data' => []]); // daily series and breakdowns
            }

            return Http::response(['data' => [
                ['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '300', 'impressions' => '30000', 'reach' => '9000', 'clicks' => '600', 'actions' => [['action_type' => 'lead', 'value' => '30']]],
                ['campaign_id' => '12002', 'objective' => 'OUTCOME_SALES', 'spend' => '200', 'impressions' => '20000', 'reach' => '8000', 'clicks' => '400',
                    'actions' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '10']], 'action_values' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '1500']]],
                ['campaign_id' => '99999', 'objective' => 'OUTCOME_TRAFFIC', 'spend' => '50', 'impressions' => '5000', 'clicks' => '100'], // made in Meta, not here
            ]]);
        }]);

        $data = $this->getJson(route('admin.ads.dashboard.performance', ['days' => 7]))->assertOk()->json();

        $p = $data['platforms']['facebook'];
        $this->assertSame(['ok', 'SAR'], [$p['status'], $p['currency']]);
        // The whole ad account, including the campaign made directly in Meta.
        $this->assertEquals([550, 1100, 55000], [$p['totals']['current']['spend'], $p['totals']['current']['clicks'], $p['totals']['current']['impressions']]);
        $this->assertEquals(1500, $p['totals']['current']['value']);
        $this->assertNotNull($p['totals']['previous']);
        // Instagram is inside Meta's totals: never a second, double-counted row.
        $this->assertSame(['status' => 'in_meta'], $data['platforms']['instagram']);
        // Per campaign, for both builders; Meta's own campaign isn't mixed into local rows.
        $this->assertEquals(300, $data['campaign_metrics'][$fb->id]['spend']);
        $this->assertEquals(200, $data['campaign_metrics'][$ig->id]['spend']);
        $this->assertCount(2, $data['campaign_metrics']);
    }

    public function test_a_platform_error_is_reported_without_breaking_the_others(): void
    {
        $this->account('google', '123-456-7890');
        Http::fake(['googleads.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $data = $this->getJson(route('admin.ads.dashboard.performance'))->assertOk()->json();

        $this->assertSame('error', $data['platforms']['google']['status']);
        $this->assertNotEmpty($data['platforms']['google']['error']);
        $this->assertSame([], $data['campaign_metrics']);
    }

    public function test_only_the_offered_ranges_are_accepted(): void
    {
        $this->getJson(route('admin.ads.dashboard.performance', ['days' => 365]))->assertStatus(422);
    }
}
