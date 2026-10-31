<?php

namespace Tests\Feature\Ads;

use App\Mail\AdPerformanceReport;
use App\Models\Admin\AdCampaign;
use App\Models\AdReportSchedule;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Google\ReportRange;
use Illuminate\Http\Client\Request as HttpRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
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

    public function test_google_export_has_the_period_totals_daily_rows_and_campaigns(): void
    {
        $this->account('google');
        $this->fakeGoogleAds();

        $response = $this->get(route('admin.ads.campaigns.export', ['platform' => 'google', 'days' => 7]))->assertOk();
        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('filename=google-ads-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('"Amount spent",150', $csv);
        $this->assertStringContainsString('"Conversion value",500', $csv);
        $this->assertStringContainsString('Date,"Amount spent",Clicks,Conversions', $csv);
        $this->assertStringContainsString('"Search KSA",,enabled,50,,,,120,2000,40,2%,3,4,400', $csv);
        $this->assertStringNotContainsString('Brand video', $csv); // the YouTube campaign is on the YouTube page
    }

    public function test_a_platform_without_reporting_exports_its_campaigns_and_says_spend_is_not_reported(): void
    {
        $account = $this->account('tiktok');
        AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $account->id, 'platform' => 'tiktok', 'name' => '=HYPERLINK("http://evil")', 'status' => 'enable', 'objective' => 'TRAFFIC', 'daily_budget' => 80]);
        Http::fake();

        $csv = $this->get(route('admin.ads.campaigns.export', ['platform' => 'tiktok', 'days' => 30]))->assertOk()->getContent();

        $this->assertStringContainsString('isn’t available for TikTok yet', $csv);
        $this->assertStringContainsString('"Daily budget",80', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\",TRAFFIC,enable,80", $csv);
        $this->assertStringNotContainsString('Amount spent', $csv);
        Http::assertNothingSent();
    }

    public function test_reports_are_scheduled_per_platform_and_the_scheduler_emails_them(): void
    {
        Carbon::setTestNow(now('UTC')->next(Carbon::THURSDAY)->setTime(10, 0)); // after the fixture subscription starts
        $tiktok = $this->account('tiktok');
        AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $tiktok->id, 'platform' => 'tiktok', 'name' => 'Ramadan traffic', 'status' => 'enable', 'daily_budget' => 80]);
        $this->account('google');
        $this->fakeGoogleAds();
        Mail::fake();

        $report = $this->postJson(route('admin.ads.reports.store', ['platform' => 'tiktok']), [
            'name' => 'Weekly TikTok', 'frequency' => 'weekly', 'weekday' => 0, 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7,
        ])->assertOk()->json('report');
        $this->assertDatabaseHas('ad_report_schedules', ['id' => $report['id'], 'platform' => 'tiktok', 'social_account_id' => $tiktok->id, 'recipient_email' => 's@example.com']);
        $this->assertSame([$report['id']], array_column($this->get(route('admin.ads.campaigns.index', ['platform' => 'tiktok']))->viewData('data')['reports'], 'id'));
        $this->assertSame([], $this->get(route('admin.ads.campaigns.index', ['platform' => 'google']))->viewData('data')['reports']);

        $this->postJson(route('admin.ads.reports.store', ['platform' => 'google']), [
            'name' => 'Weekly Google', 'frequency' => 'daily', 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7,
        ])->assertOk();
        AdReportSchedule::query()->update(['next_run_at' => now()->subMinute()]);

        $this->artisan('ads:send-reports')->assertSuccessful();

        Mail::assertSent(AdPerformanceReport::class, 2);
        Mail::assertSent(AdPerformanceReport::class, fn (AdPerformanceReport $m) => $m->schedule->platform === 'tiktok'
            && str_contains($m->report['csv'], 'Ramadan traffic') && str_contains($m->render(), 'TikTok Ads report'));
        Mail::assertSent(AdPerformanceReport::class, fn (AdPerformanceReport $m) => $m->schedule->platform === 'google'
            && str_contains($m->render(), 'Search KSA') && str_contains($m->attachments()[0]->as, 'google-ads-'));
        $this->assertSame(2, AdReportSchedule::whereNotNull('last_sent_at')->whereNull('last_error')->count());
        Carbon::setTestNow();
    }
}
