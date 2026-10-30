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

/** Campaigns pages of Google, YouTube, TikTok, Snapchat, LinkedIn, X and ChatGPT Ads, and their live performance. */
class PlatformCampaignsPageTest extends TestCase
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

    private function account(string $platform): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => $platform, 'platform_account_id' => '123-456-7890', 'name' => ucfirst($platform) . ' Ads',
            'access_token' => 'token', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
    }

    /** The Google Ads customer reports one Search and one YouTube campaign. */
    private function fakeGoogleAds(): array
    {
        $search = AdCampaign::create(['user_id' => $this->user->id, 'platform' => 'google', 'name' => 'Search KSA', 'status' => 'ENABLED', 'ad_campaign_id' => 'customers/1234567890/campaigns/9', 'daily_budget' => 50]);
        $video = AdCampaign::create(['user_id' => $this->user->id, 'platform' => 'youtube', 'name' => 'Brand video', 'status' => 'ENABLED', 'ad_campaign_id' => 'customers/1234567890/campaigns/10', 'daily_budget' => 30]);
        [$start] = ReportRange::make('7d')->current();
        $metrics = fn ($cost, $clicks, $conv) => ['costMicros' => (string) ($cost * 1000000), 'clicks' => (string) $clicks, 'impressions' => '2000', 'conversions' => $conv, 'conversionsValue' => $conv * 100];
        Http::fake(function (HttpRequest $request) use ($start, $metrics) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'FROM customer')) {
                return Http::response(['results' => [['customer' => ['currencyCode' => 'SAR'], 'segments' => ['date' => $start], 'metrics' => $metrics(150, 60, 5.0)]]]);
            }
            if (str_contains($query, 'FROM campaign')) {
                return Http::response(['results' => [
                    ['campaign' => ['id' => '9', 'name' => 'Search KSA', 'status' => 'ENABLED'], 'metrics' => $metrics(120, 40, 4.0)],
                    ['campaign' => ['id' => '10', 'name' => 'Brand video', 'status' => 'ENABLED'], 'metrics' => $metrics(30, 20, 1.0)],
                ]]);
            }

            return Http::response(['results' => [['conversionAction' => ['id' => '1']]]]);
        });

        return [$search, $video];
    }

    public function test_every_platform_page_uses_the_shared_component_and_only_reporting_platforms_load_performance(): void
    {
        foreach (['google', 'tiktok', 'snapchat', 'linkedin', 'x', 'openai'] as $platform) {
            $this->account($platform);
        }

        foreach (['google' => true, 'youtube' => true, 'openai' => true, 'tiktok' => false, 'snapchat' => false, 'linkedin' => false, 'x' => false] as $platform => $reports) {
            $response = $this->get(route('admin.ads.campaigns.index', ['platform' => $platform]))->assertOk()->assertSee('<platform-campaigns', false);
            $data = $response->viewData('data');
            $this->assertSame($reports, $data['urls']['performance'] !== null, $platform);
            $this->assertNotEmpty($response->viewData('messages')['platform'], $platform);
        }
    }

    public function test_google_performance_covers_the_customer_and_maps_only_google_campaigns(): void
    {
        $this->account('google');
        [$search, $video] = $this->fakeGoogleAds();

        $data = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'google', 'days' => 7]))->assertOk()->json();

        $this->assertSame('ok', $data['status']);
        $this->assertSame('account', $data['scope']);
        $this->assertEquals(150, $data['totals']['current']['spend']);
        $this->assertCount(7, $data['trend']['dates']);
        $this->assertSame([(string) $search->id], array_map('strval', array_keys($data['campaigns'])));
        $this->assertEquals(120, $data['campaigns'][$search->id]['spend']);
    }

    public function test_youtube_performance_is_the_sum_of_its_own_campaigns(): void
    {
        $this->account('google');
        [, $video] = $this->fakeGoogleAds();

        $data = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'youtube', 'days' => 7]))->assertOk()->json();

        $this->assertSame('campaigns', $data['scope']);
        $this->assertEquals(['spend' => 30, 'clicks' => 20, 'impressions' => 2000, 'conversions' => 1, 'value' => 100], $data['totals']['current']);
        $this->assertNull($data['totals']['previous']);
        $this->assertNull($data['trend']);
        $this->assertSame([(string) $video->id], array_map('strval', array_keys($data['campaigns'])));
    }

    public function test_platforms_without_reporting_say_so_and_ranges_are_checked(): void
    {
        $this->account('tiktok');
        Http::fake();

        $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'tiktok']))->assertOk()->assertExactJson(['status' => 'no_reporting']);
        $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'snapchat']))->assertOk()->assertExactJson(['status' => 'not_connected']);
        $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'tiktok', 'days' => 365]))->assertStatus(422);
        Http::assertNothingSent();
    }
}
