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
        $this->account('facebook', '555');
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
        $this->assertSame(['status' => 'no_reporting'], $data['platforms']['facebook']);
        $this->assertSame(['status' => 'not_connected'], $data['platforms']['tiktok']);
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
