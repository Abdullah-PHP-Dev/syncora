<?php

namespace Tests\Feature\GoogleSuite;

use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\GoogleSuite\GoogleSuiteAuth;
use App\Services\GoogleSuite\GoogleSuiteService;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Google\GoogleApiException;
use App\Support\Google\GoogleSuiteScopes;
use App\Support\Google\ReportRange;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Google Suite (Analytics & Insights): consent, discovery, reporting, isolation, disconnect. */
class GoogleSuiteTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        (require database_path('migrations/2026_10_27_100000_create_google_suite_tables.php'))->up();
        Settings::set('posts.google.client_id', 'client-1');
        Settings::set('posts.google.client_secret', 'secret-1');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        // GETs would be redirected to their /en URL; the routes under test keep every other middleware.
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function connection(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::ANALYTICS, 'provider_app' => 'posts.google',
            'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour(), 'status' => SocialConnection::ACTIVE,
            'granted_scopes' => [GoogleSuiteScopes::ANALYTICS, GoogleSuiteScopes::SEARCH_CONSOLE], 'capabilities' => ['insights'],
        ], $attributes));
    }

    private function property(string $service, array $attributes = []): GoogleProperty
    {
        return GoogleProperty::create(array_merge([
            'user_id' => $this->user->id, 'service' => $service, 'is_selected' => true,
            'external_id' => $service === GoogleProperty::GA4 ? 'properties/123' : 'sc-domain:example.com',
            'display_name' => $service === GoogleProperty::GA4 ? 'Example GA4' : 'example.com',
        ], $attributes));
    }

    private function idToken(array $claims): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256']) . '.' . $part($claims) . '.signature';
    }

    private function startConsent(): string
    {
        app(GoogleSuiteAuth::class)->redirect();

        return session('social_oauth_state_google');
    }

    public function test_connect_asks_only_for_read_only_reporting_scopes(): void
    {
        $location = app(ConnectionService::class)->connect('google', GoogleDriver::ANALYTICS)->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $location);
        $this->assertSame(['openid', 'email', GoogleSuiteScopes::ANALYTICS, GoogleSuiteScopes::SEARCH_CONSOLE], explode(' ', $query['scope']));
        $this->assertSame('offline', $query['access_type']);
        $this->assertArrayNotHasKey('include_granted_scopes', $query); // never inherits YouTube/Ads scopes
        $this->assertSame($query['state'], session('social_oauth_state_google'));
        $this->assertSame(GoogleSuiteAuth::FLOW, session(GoogleSuiteAuth::FLOW_KEY));
    }

    public function test_successful_callback_records_an_encrypted_consent_and_discovers_properties(): void
    {
        $state = $this->startConsent();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3599,
                'scope' => 'openid ' . GoogleSuiteScopes::ANALYTICS . ' ' . GoogleSuiteScopes::SEARCH_CONSOLE . ' https://www.googleapis.com/auth/userinfo.email',
                'id_token' => $this->idToken(['sub' => 'g-42', 'email' => 'owner@brand.com']),
            ]),
            'analyticsadmin.googleapis.com/v1beta/accountSummaries*' => Http::response(['accountSummaries' => [[
                'account' => 'accounts/9', 'displayName' => 'Brand LLC',
                'propertySummaries' => [['property' => 'properties/412883190', 'displayName' => 'brand.com - GA4', 'propertyType' => 'PROPERTY_TYPE_ORDINARY']],
            ]]]),
            'analyticsadmin.googleapis.com/v1beta/properties/412883190/dataStreams*' => Http::response(['dataStreams' => [[
                'type' => 'WEB_DATA_STREAM', 'displayName' => 'Website', 'webStreamData' => ['measurementId' => 'G-8XK2QF41LM', 'defaultUri' => 'https://brand.com'],
            ]]]),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
                ['siteUrl' => 'sc-domain:brand.com', 'permissionLevel' => 'siteOwner'],
                ['siteUrl' => 'https://unverified.com/', 'permissionLevel' => 'siteUnverifiedUser'],
            ]]),
        ]);

        $response = app(SocialAuthService::class)->callback('google', 'auth-code', $state);

        $this->assertSame(route('admin.google.status'), $response->getTargetUrl());
        $this->assertSame('success', session('google_oauth')['status']);

        $connection = SocialConnection::where('step', GoogleDriver::ANALYTICS)->sole();
        $this->assertSame('g-42', $connection->provider_account_id);
        $this->assertSame('new-refresh', $connection->refresh_token);
        $this->assertNotSame('new-refresh', DB::table('social_connections')->where('id', $connection->id)->value('refresh_token'));
        $this->assertSame(['insights'], $connection->capabilities);
        $this->assertSame('owner@brand.com', GoogleSuiteSetting::for($this->user->id)['account']['email']);

        $ga4 = GoogleProperty::where('service', 'ga4')->sole();
        $this->assertSame('G-8XK2QF41LM', $ga4->meta['measurement_id']);
        $this->assertSame('Brand LLC', $ga4->account_name);
        $this->assertFalse($ga4->is_selected); // the customer confirms the choice
        $this->assertSame(['sc-domain:brand.com'], GoogleProperty::where('service', 'search_console')->pluck('external_id')->all());
    }

    public function test_callback_with_a_wrong_state_connects_nothing(): void
    {
        $this->startConsent();
        Http::fake();

        app(SocialAuthService::class)->callback('google', 'auth-code', 'forged-state');

        $this->assertSame(['status' => 'failed', 'code' => 'state_mismatch'], session('google_oauth'));
        $this->assertSame(0, SocialConnection::count());
        Http::assertNothingSent();
    }

    public function test_cancelling_on_googles_screen_is_reported_as_cancelled(): void
    {
        $state = $this->startConsent();

        app(SocialAuthService::class)->callback('google', '', $state, null, 'access_denied');

        $this->assertSame('cancelled', session('google_oauth')['status']);
        $this->assertSame(0, SocialConnection::count());
    }

    public function test_unticked_search_console_permission_is_a_partial_connection(): void
    {
        $state = $this->startConsent();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => 'openid ' . GoogleSuiteScopes::ANALYTICS]),
            'analyticsadmin.googleapis.com/*' => Http::response(['accountSummaries' => []]),
        ]);

        app(SocialAuthService::class)->callback('google', 'auth-code', $state);

        $this->assertSame('partial', session('google_oauth')['status']);
        $this->assertSame(['search_console'], session('google_oauth')['missing']);
        $this->assertSame(['error' => GoogleApiException::MISSING_SCOPE], session('google_oauth')['found']['search_console']);
    }

    public function test_a_later_youtube_connect_is_not_captured_by_an_abandoned_suite_consent(): void
    {
        $this->startConsent();
        app(SocialAuthService::class)->redirect('google');

        $this->assertNull(session(GoogleSuiteAuth::FLOW_KEY));
    }

    public function test_another_workspaces_property_cannot_be_selected(): void
    {
        $this->connection();
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = GoogleProperty::create(['user_id' => $other->id, 'service' => 'ga4', 'external_id' => 'properties/999', 'display_name' => 'Not yours']);

        $this->putJson(route('admin.google.api.properties.select'), ['service' => 'ga4', 'property_id' => $foreign->id])->assertNotFound();
        $this->assertFalse($foreign->fresh()->is_selected);
    }

    public function test_ga4_dashboard_maps_the_data_api_response(): void
    {
        $this->connection();
        $this->property('ga4', ['meta' => ['currency_code' => 'SAR']]);
        $range = ReportRange::make('7d');
        $metricRow = fn (array $values, array $dims = []) => ['dimensionValues' => array_map(fn ($v) => ['value' => $v], $dims), 'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $values)];
        $report = fn (array $dims, array $metrics, array $rows) => ['dimensionHeaders' => array_map(fn ($n) => ['name' => $n], $dims), 'metricHeaders' => array_map(fn ($n) => ['name' => $n], $metrics), 'rows' => $rows];
        $totals = ['activeUsers', 'totalUsers', 'newUsers', 'sessions', 'engagedSessions', 'screenPageViews', 'engagementRate', 'userEngagementDuration', 'keyEvents', 'totalRevenue'];

        Http::fake([
            'analyticsdata.googleapis.com/v1beta/properties/123:batchRunReports' => Http::sequence()
                ->push(['reports' => [
                    $report(['dateRange'], $totals, [$metricRow([100, 120, 40, 150, 90, 400, 0.6, 11200, 7, 0], ['current']), $metricRow([80, 90, 30, 120, 70, 300, 0.58, 8000, 5, 0], ['previous'])]),
                    $report(['date', 'dateRange'], ['activeUsers', 'sessions', 'keyEvents'], [$metricRow([20, 30, 1], [$range->start->format('Ymd'), 'current'])]),
                    $report(['sessionDefaultChannelGroup'], ['sessions', 'engagedSessions', 'keyEvents', 'totalRevenue'], [$metricRow([90, 60, 4, 0], ['Organic Search'])]),
                    $report(['landingPage'], ['sessions', 'activeUsers', 'engagementRate', 'keyEvents'], []),
                    $report(['pagePath'], ['screenPageViews', 'activeUsers', 'userEngagementDuration', 'keyEvents'], [$metricRow([50, 10, 600, 1], ['/ar/'])]),
                ]])
                ->push(['reports' => [
                    $report(['deviceCategory'], ['activeUsers', 'sessions'], [$metricRow([70, 100], ['mobile'])]),
                    $report(['country'], ['activeUsers', 'sessions'], []),
                    $report(['eventName'], ['keyEvents'], [$metricRow([7], ['purchase'])]),
                    $report(['sessionSourceMedium'], ['sessions', 'activeUsers'], []),
                ]]),
        ]);

        $data = $this->getJson(route('admin.google.api.ga4', ['range' => '7d']))->assertOk()->json('data');

        $this->assertSame(150, $data['totals']['current']['sessions']);
        $this->assertSame(120, $data['totals']['previous']['sessions']);
        $this->assertEqualsWithDelta(112.0, $data['totals']['current']['avg_engagement'], 0.001); // 11200s / 100 users
        $this->assertSame(7, count($data['trend']['dates']));
        $this->assertEquals([30, 0, 0, 0, 0, 0, 0], $data['trend']['sessions']['current']);
        $this->assertSame('Organic Search', $data['channels'][0]['channel']);
        $this->assertEqualsWithDelta(60.0, $data['pages'][0]['avg_engagement'], 0.001);
        $this->assertEquals([['event' => 'purchase', 'count' => 7]], $data['key_events']);
        $this->assertSame('SAR', $data['currency']);
        $this->assertNotNull(GoogleProperty::sole()->last_synced_at);

        // Served from cache the second time.
        $this->getJson(route('admin.google.api.ga4', ['range' => '7d']))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_google_errors_become_customer_safe_codes_and_are_recorded(): void
    {
        $this->connection();
        $property = $this->property('search_console');
        Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['error' => ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'User does not have sufficient permission for site sc-domain:example.com']], 403)]);

        $error = $this->getJson(route('admin.google.api.search-console'))->assertOk()->json('error');

        $this->assertSame('insufficient_permissions', $error['code']);
        $this->assertStringNotContainsString('sc-domain', $error['message']); // never the provider text
        $this->assertSame('insufficient_permissions', $property->fresh()->last_error_code);
        $this->assertSame(1, DB::table('google_sync_events')->where('event', 'failed')->count());
    }

    public function test_quota_errors_are_retried_then_reported_as_retryable(): void
    {
        $this->connection();
        $this->property('ga4');
        Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429)]);

        $error = $this->getJson(route('admin.google.api.ga4'))->json('error');

        $this->assertSame('quota_exceeded', $error['code']);
        $this->assertTrue($error['retryable']);
        Http::assertSentCount(3); // backoff retries before giving up
    }

    public function test_an_expiring_token_is_refreshed_with_the_issuing_client(): void
    {
        $connection = $this->connection(['expires_at' => now()->subMinute(), 'provider_app' => 'ads.google']);
        Settings::set('ads.google.client_id', 'ads-client');
        Settings::set('ads.google.client_secret', 'ads-secret');
        $this->property('search_console');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600]),
            'www.googleapis.com/webmasters/*' => Http::response(['rows' => [], 'sitemap' => []]),
        ]);

        $this->getJson(route('admin.google.api.search-console'))->assertOk()->assertJsonMissingPath('error');

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['client_id'] === 'ads-client' && $r['refresh_token'] === 'refresh-1');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'webmasters') && $r->hasHeader('Authorization', 'Bearer fresh-access'));
        $this->assertSame('fresh-access', $connection->fresh()->access_token);
    }

    public function test_a_revoked_grant_asks_to_reconnect(): void
    {
        $connection = $this->connection(['expires_at' => now()->subMinute()]);
        $this->property('ga4');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

        $error = $this->getJson(route('admin.google.api.ga4'))->json('error');

        $this->assertSame('auth_expired', $error['code']);
        $this->assertTrue($error['reconnect']);
        $this->assertSame(SocialConnection::NEEDS_REAUTH, $connection->fresh()->status);
    }

    public function test_disconnect_keeps_the_shared_youtube_grant_and_forgets_suite_data(): void
    {
        $suite = $this->connection();
        SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::OAUTH, 'provider_app' => 'posts.google', 'access_token' => 'yt', 'refresh_token' => 'yt-r', 'status' => 'active']);
        $this->property('ga4');
        GoogleSuiteSetting::store($this->user->id, ['account' => ['email' => 'owner@brand.com']]);
        Http::fake();

        $this->deleteJson(route('admin.google.disconnect'))->assertOk();

        Http::assertNothingSent(); // revoking would also cut YouTube posting on the same client
        $this->assertSame(SocialConnection::REVOKED, $suite->fresh()->status);
        $this->assertNull($suite->fresh()->refresh_token);
        $this->assertSame(0, GoogleProperty::count());
        $this->assertArrayNotHasKey('account', GoogleSuiteSetting::for($this->user->id));
        $this->assertNull(GoogleSuiteAuth::connectionFor($this->user->id));
    }

    public function test_disconnect_revokes_at_google_when_it_is_the_only_consent(): void
    {
        $this->connection();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);

        $this->deleteJson(route('admin.google.disconnect'))->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/revoke') && $r['token'] === 'refresh-1');
    }

    public function test_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $this->connection();
        $this->property('search_console');
        Http::fake(['www.googleapis.com/webmasters/*' => Http::response(['rows' => [
            ['keys' => ['=HYPERLINK("http://evil")'], 'clicks' => 3, 'impressions' => 10, 'ctr' => 0.3, 'position' => 1.5],
            ['keys' => ['متجر الكتروني'], 'clicks' => 2, 'impressions' => 8, 'ctr' => 0.25, 'position' => 4],
        ]])]);

        $response = $this->get(route('admin.google.api.reports.export', ['service' => 'search_console', 'type' => 'queries', 'range' => '28d']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\"", $csv);
        $this->assertStringContainsString('متجر الكتروني', $csv);
    }

    public function test_reports_reject_types_the_service_does_not_have(): void
    {
        $this->connection();
        $this->property('ga4');

        $this->getJson(route('admin.google.api.reports.preview', ['service' => 'ga4', 'type' => 'queries']))->assertStatus(422);
    }

    public function test_preferences_are_validated_and_saved(): void
    {
        $this->putJson(route('admin.google.api.preferences'), ['default_range' => '365d', 'compare_previous' => true, 'widgets' => [], 'sync_frequency' => 'hourly'])
            ->assertStatus(422)->assertJsonValidationErrors('default_range');

        $this->putJson(route('admin.google.api.preferences'), ['default_range' => '90d', 'compare_previous' => false, 'widgets' => ['services', 'activity'], 'sync_frequency' => 'daily'])
            ->assertOk();

        $saved = GoogleSuiteSetting::for($this->user->id);
        $this->assertSame('90d', $saved['default_range']);
        $this->assertSame(['services', 'activity'], $saved['widgets']);
    }

    public function test_url_inspection_is_limited_to_the_selected_property(): void
    {
        $this->connection();
        $this->property('search_console');
        Http::fake();

        $this->postJson(route('admin.google.api.inspect'), ['url' => 'https://someone-else.com/page'])->assertStatus(422)->assertJsonValidationErrors('url');
        Http::assertNothingSent();
    }

    public function test_custom_ranges_are_bounded(): void
    {
        $this->assertSame(31, ReportRange::make('custom', '2026-09-01', '2026-10-01')->days());
        $this->assertSame('2026-08-01', ReportRange::make('custom', '2026-09-01', '2026-10-01')->previous()[0]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ReportRange::make('custom', today()->subYears(2)->toDateString(), today()->toDateString());
    }

    public function test_without_a_selected_property_the_dashboard_says_so(): void
    {
        $this->connection();

        $this->assertSame('no_property', app(GoogleSuiteService::class)->ga4Dashboard($this->user->id, ReportRange::make('28d'))['error']['code']);
    }

    private function pageSpeedBody(): array
    {
        return [
            'id' => 'https://brand.com/',
            'analysisUTCTimestamp' => '2026-10-27T10:00:00Z',
            'loadingExperience' => ['origin_fallback' => true, 'metrics' => ['LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 1, 'category' => 'FAST']]],
            'originLoadingExperience' => ['overall_category' => 'AVERAGE', 'metrics' => [
                'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 3100, 'category' => 'AVERAGE'],
                'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 12, 'category' => 'AVERAGE'],
            ]],
            'lighthouseResult' => [
                'finalDisplayedUrl' => 'https://brand.com/',
                'categories' => ['performance' => ['score' => 0.47, 'auditRefs' => [
                    ['id' => 'largest-contentful-paint', 'group' => 'metrics'],
                    ['id' => 'render-blocking-resources'],
                    ['id' => 'unused-javascript'],
                    ['id' => 'uses-text-compression'],
                ]]],
                'audits' => [
                    'largest-contentful-paint' => ['id' => 'largest-contentful-paint', 'score' => 0.2, 'numericValue' => 5200, 'displayValue' => '5.2 s', 'scoreDisplayMode' => 'numeric'],
                    'render-blocking-resources' => ['id' => 'render-blocking-resources', 'title' => 'Eliminate render-blocking resources', 'description' => 'Resources are blocking the first paint. [Learn how](https://developer.chrome.com/docs/lighthouse/x).', 'score' => 0.3, 'scoreDisplayMode' => 'metricSavings', 'details' => ['overallSavingsMs' => 900]],
                    'unused-javascript' => ['id' => 'unused-javascript', 'title' => 'Reduce unused JavaScript', 'description' => 'Remove dead code.', 'score' => 0.5, 'scoreDisplayMode' => 'metricSavings', 'details' => ['overallSavingsMs' => 1500, 'overallSavingsBytes' => 120000]],
                    'uses-text-compression' => ['id' => 'uses-text-compression', 'title' => 'Enable text compression', 'score' => 1, 'scoreDisplayMode' => 'metricSavings'],
                ],
            ],
        ];
    }

    public function test_pagespeed_works_without_a_google_connection_and_keeps_lab_and_field_data_apart(): void
    {
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response($this->pageSpeedBody())]);

        $data = $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://brand.com/', 'strategy' => 'mobile']))->assertOk()->json('data');

        $this->assertSame(47, $data['score']);
        $this->assertSame('5.2 s', $data['lab']['lcp']['display']);
        // The page itself had too little traffic, so the site-wide (origin) figures are used - and labelled as such.
        $this->assertSame('origin', $data['field']['scope']);
        $this->assertEqualsWithDelta(0.12, $data['field']['metrics']['cls']['value'], 0.0001);
        $this->assertSame(['unused-javascript', 'render-blocking-resources'], array_column($data['opportunities'], 'id'));
        $this->assertSame('https://developer.chrome.com/docs/lighthouse/x', $data['opportunities'][1]['learn_url']);
        $this->assertStringNotContainsString('](', $data['opportunities'][1]['description']);
        $this->assertSame(1, $data['passed']);
        $this->assertSame('https://brand.com/', GoogleSuiteSetting::for($this->user->id)['pagespeed_url']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'strategy=MOBILE') && str_contains($r->url(), 'category=PERFORMANCE'));
    }

    public function test_pagespeed_cached_lookups_never_start_a_test(): void
    {
        Http::fake();

        $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://brand.com/', 'strategy' => 'desktop', 'cached' => 1]))
            ->assertOk()->assertExactJson(['data' => null]);
        Http::assertNothingSent();
    }

    public function test_pagespeed_explains_a_page_google_cannot_load(): void
    {
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['code' => 500, 'message' => 'Lighthouse returned error: FAILED_DOCUMENT_REQUEST.']], 500)]);

        $this->assertSame('page_unreachable', $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://intranet.brand.com/', 'strategy' => 'mobile']))->json('error.code'));
        $this->getJson(route('admin.google.api.pagespeed', ['url' => 'javascript:alert(1)', 'strategy' => 'mobile']))->assertStatus(422);
    }

    public function test_google_ads_results_come_from_the_ads_hub_account(): void
    {
        SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'google', 'name' => 'Brand Ads', 'platform_account_id' => '123-456-7890',
            'access_token' => 'ads-token', 'refresh_token' => 'ads-refresh', 'expires_at' => now()->addHour(), 'has_ads_permission' => true,
        ]);
        $range = ReportRange::make('7d');
        [$start, $end] = $range->current();
        [$previousStart] = $range->previous();
        Http::fake(function (HttpRequest $request) use ($start, $end, $previousStart) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'FROM customer')) {
                return Http::response(['results' => [
                    ['customer' => ['currencyCode' => 'SAR'], 'segments' => ['date' => $start], 'metrics' => ['costMicros' => '150000000', 'clicks' => '30', 'impressions' => '1000', 'conversions' => 3.0, 'conversionsValue' => 600.0]],
                    ['customer' => ['currencyCode' => 'SAR'], 'segments' => ['date' => $end], 'metrics' => ['costMicros' => '50000000', 'clicks' => '10', 'impressions' => '1000', 'conversions' => 1.0]],
                    ['customer' => ['currencyCode' => 'SAR'], 'segments' => ['date' => $previousStart], 'metrics' => ['costMicros' => '100000000', 'clicks' => '20', 'impressions' => '500']],
                ]]);
            }
            if (str_contains($query, 'FROM campaign')) {
                return Http::response(['results' => [['campaign' => ['id' => '9', 'name' => 'Search - Riyadh', 'status' => 'ENABLED', 'advertisingChannelType' => 'SEARCH'], 'metrics' => ['costMicros' => '200000000', 'clicks' => '40', 'impressions' => '2000']]]]);
            }

            return Http::response(['results' => []]); // no enabled conversion actions
        });

        $data = $this->getJson(route('admin.google.api.google-ads', ['range' => '7d']))->assertOk()->json('data');

        $this->assertSame('SAR', $data['currency']);
        $this->assertEquals(200, $data['totals']['current']['cost']);
        $this->assertSame(40, $data['totals']['current']['clicks']);
        $this->assertEquals(50, $data['totals']['current']['cost_per_conversion']);
        $this->assertEquals(3, $data['totals']['current']['roas']);
        // No conversions or value last period: no ratio is invented.
        $this->assertNull($data['totals']['previous']['cost_per_conversion']);
        $this->assertNull($data['totals']['previous']['roas']);
        $this->assertEquals([150, 0, 0, 0, 0, 0, 50], $data['trend']['cost']['current']);
        $this->assertSame('Search - Riyadh', $data['campaigns'][0]['name']);
        $this->assertNull($data['campaigns'][0]['roas']);
        $this->assertFalse($data['conversion_tracking']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'customers/1234567890/googleAds:search') && $r->hasHeader('Authorization', 'Bearer ads-token'));
    }

    public function test_google_ads_without_an_ads_account_says_how_to_connect(): void
    {
        Http::fake();

        $this->assertSame('ads_not_connected', $this->getJson(route('admin.google.api.google-ads'))->json('error.code'));
        Http::assertNothingSent();
    }

    public function test_new_screens_render(): void
    {
        foreach (['advertising', 'website-performance', 'integrations', 'business-profile', 'tag-manager'] as $screen) {
            $this->get(route('admin.google.' . $screen))->assertOk();
        }
    }
}
