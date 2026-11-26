<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** LinkedIn Ads reporting (adAnalytics finder): the request LinkedIn documents, real numbers only, clear errors. */
class LinkedinInsightsTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private User $user;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'UTC')); // before any fixture row (the subscription starts "today")
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => '5551234', 'name' => 'Acme LinkedIn', 'account_type' => 'ad_account',
            'access_token' => 'li-token', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
        $this->account->syncAdDetails(['currency' => 'SAR']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Current period: group 777 (leads) and 888 (made in LinkedIn); previous period: 777 only; daily: two days. */
    private function fake(array &$urls = []): void
    {
        Http::fake(['api.linkedin.com/rest/adAnalytics*' => function (HttpRequest $r) use (&$urls) {
            $urls[] = $r->url();
            if (str_contains($r->url(), 'pivot=ACCOUNT')) {
                return Http::response(['elements' => [
                    ['dateRange' => ['start' => ['year' => 2026, 'month' => 11, 'day' => 13], 'end' => ['year' => 2026, 'month' => 11, 'day' => 13]], 'costInLocalCurrency' => '40.0', 'impressions' => 2000, 'clicks' => 30, 'oneClickLeads' => 4],
                    ['dateRange' => ['start' => ['year' => 2026, 'month' => 11, 'day' => 19], 'end' => ['year' => 2026, 'month' => 11, 'day' => 19]], 'costInLocalCurrency' => '60.5', 'impressions' => 3000, 'clicks' => 50, 'oneClickLeads' => 6],
                ]]);
            }
            $previous = str_contains($r->url(), 'start:(year:2026,month:11,day:6)');

            return Http::response(['elements' => $previous
                ? [['pivotValues' => ['urn:li:sponsoredCampaignGroup:777'], 'costInLocalCurrency' => '50', 'impressions' => 2500, 'clicks' => 40, 'oneClickLeads' => 5]]
                : [
                    ['pivotValues' => ['urn:li:sponsoredCampaignGroup:777'], 'costInLocalCurrency' => '100.50', 'impressions' => 5000, 'clicks' => 80, 'landingPageClicks' => 70, 'oneClickLeads' => 10, 'approximateMemberReach' => 3000],
                    ['pivotValues' => ['urn:li:sponsoredCampaignGroup:888'], 'costInLocalCurrency' => '20', 'impressions' => 1000, 'clicks' => 10, 'externalWebsiteConversions' => 2, 'conversionValueInLocalCurrency' => '300'],
                ]]);
        }]);
    }

    public function test_the_request_follows_linkedins_documented_format(): void
    {
        $urls = [];
        $this->fake($urls);

        $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'linkedin', 'days' => 7]))->assertOk();

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('LinkedIn-Version', '202606') && $r->hasHeader('X-Restli-Protocol-Version', '2.0.0')
            && $r->hasHeader('Authorization', 'Bearer li-token'));
        $group = collect($urls)->first(fn ($u) => str_contains($u, 'pivot=CAMPAIGN_GROUP'));
        // Rest.li 2.0: literal parentheses / colons / commas, only the URN encoded; UTC dates, inclusive; until = yesterday.
        $this->assertStringContainsString('q=analytics', $group);
        $this->assertStringContainsString('timeGranularity=ALL', $group);
        $this->assertStringContainsString('dateRange=(start:(year:2026,month:11,day:13),end:(year:2026,month:11,day:19))', $group);
        $this->assertStringContainsString('accounts=List(urn%3Ali%3AsponsoredAccount%3A5551234)', $group);
        $this->assertStringContainsString('fields=costInLocalCurrency,impressions,clicks,', $group);
        $this->assertLessThanOrEqual(20, count(explode(',', explode('fields=', $group)[1]))); // LinkedIn: up to 20
        $this->assertTrue(collect($urls)->contains(fn ($u) => str_contains($u, 'pivot=ACCOUNT') && str_contains($u, 'timeGranularity=DAILY')));
    }

    public function test_numbers_map_to_local_campaigns_with_results_by_objective(): void
    {
        $leads = AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'linkedin', 'name' => 'Leads', 'status' => 'ACTIVE', 'objective' => 'LEAD_GENERATION', 'ad_campaign_id' => '777']);
        $this->fake();

        $res = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'linkedin', 'days' => 7]))->assertOk()->json();

        $this->assertSame(['ok', 'SAR', 'account'], [$res['status'], $res['currency'], $res['scope']]);
        // Account totals include the group made directly in LinkedIn; costs arrive as strings.
        $this->assertEquals(['spend' => 120.5, 'clicks' => 90, 'impressions' => 6000, 'conversions' => 12, 'value' => 300], $res['totals']['current']);
        $this->assertEquals(50, $res['totals']['previous']['spend']);
        // Only this seller's own campaigns are listed per row.
        $this->assertSame([(string) $leads->id], array_map('strval', array_keys($res['campaigns'])));
        $this->assertEquals(100.5, $res['campaigns'][$leads->id]['spend']);
        // Daily: every day of the period, zero on days LinkedIn didn't report.
        $this->assertCount(7, $res['trend']['dates']);
        $this->assertEquals([40, 0, 0, 0, 0, 0, 60.5], $res['trend']['spend']);

        $insights = \App\Services\AdServices\LinkedinInsightsService::forUser($this->user->id)->insights(7);
        $this->assertSame(['lead', 10.0], [$insights['campaigns'][$leads->id]['result_type'], (float) $insights['campaigns'][$leads->id]['results']]);
        $this->assertEquals(10.05, $insights['campaigns'][$leads->id]['cost_per_result']); // 100.50 / 10
        $this->assertEquals(0.016, $insights['campaigns'][$leads->id]['ctr']);              // 80 / 5000
    }

    public function test_the_dashboard_shows_linkedin_spend(): void
    {
        $this->fake();

        $data = $this->getJson(route('admin.ads.dashboard.performance', ['days' => 7]))->assertOk()->json();

        $this->assertSame('ok', $data['platforms']['linkedin']['status']);
        $this->assertEquals(120.5, $data['platforms']['linkedin']['totals']['current']['spend']);
    }

    public function test_missing_reporting_permission_or_expired_access_asks_to_reconnect(): void
    {
        Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'Not enough permissions to access: adAnalytics', 'status' => 403], 403)]);
        $res = $this->getJson(route('admin.ads.campaigns.performance', ['platform' => 'linkedin', 'days' => 7]))->assertOk()->json();
        $this->assertSame('error', $res['status']);
        $this->assertStringContainsString('r_ads_reporting', $res['error']);

        // Expired, without a refresh token: nothing is sent, the person is told to reconnect.
        Http::fake();
        $this->account->forceFill(['expires_at' => now()->subDay(), 'refresh_token' => null])->save();
        \Illuminate\Support\Facades\Cache::flush();
        $res = \App\Services\AdServices\LinkedinInsightsService::forUser($this->user->id)->insights(7);
        $this->assertFalse($res['success']);
        $this->assertTrue($res['reconnect']);
        Http::assertNothingSent();
    }

    public function test_rate_limits_are_explained_and_nothing_is_invented(): void
    {
        Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'Too many requests'], 429)]);

        $res = $this->getJson(route('admin.ads.dashboard.performance', ['days' => 7]))->assertOk()->json();

        $this->assertSame('error', $res['platforms']['linkedin']['status']);
        $this->assertStringContainsString('try again', $res['platforms']['linkedin']['error']);
        $this->assertArrayNotHasKey('totals', $res['platforms']['linkedin']);
    }
}
