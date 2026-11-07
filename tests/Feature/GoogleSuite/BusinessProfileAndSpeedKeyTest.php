<?php

namespace Tests\Feature\GoogleSuite;

use App\Models\GoogleSuiteSetting;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\GoogleSuite\BusinessProfileService;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\AdAccountSelection;
use App\Support\Google\ReportRange;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Website Performance: the workspace's own PageSpeed key. Business Profile: location import, performance, reviews, replies. */
class BusinessProfileAndSpeedKeyTest extends TestCase
{
    use CreatesConnectionTables;

    private const KEY = 'AIzaSyA1234567890abcdefghijklmnopqrstuv';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        // The Business Profile dashboard lists the location's recent posts.
        \Illuminate\Support\Facades\Schema::create('posts', function ($t) {
            $t->id();
            $t->string('platform')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->text('error_message')->nullable();
            $t->string('post_url')->nullable();
            $t->boolean('schedule_mode')->default(false);
            $t->timestamp('schedule_at')->nullable();
            $t->timestamps();
        });
        \Illuminate\Support\Facades\Schema::create('post_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->text('media_url')->nullable();
            $t->timestamps();
        });
        Settings::set('posts.google.client_id', 'client-1');
        Settings::set('posts.google.client_secret', 'secret-1');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    /* ---------------- PageSpeed key ---------------- */

    public function test_a_saved_key_is_encrypted_never_sent_back_and_used_for_tests(): void
    {
        $this->putJson(route('admin.google.api.pagespeed-key'), ['api_key' => 'not-a-key'])->assertStatus(422);

        $response = $this->putJson(route('admin.google.api.pagespeed-key'), ['api_key' => self::KEY])->assertOk();
        $this->assertSame(['saved' => true, 'last4' => 'stuv'], array_intersect_key($response->json('pagespeed_key'), ['saved' => 1, 'last4' => 1]));
        $this->assertStringNotContainsString(self::KEY, $response->getContent());

        // Stored encrypted, and kept out of every preferences payload and the page itself.
        $stored = GoogleSuiteSetting::for($this->user->id)[GoogleSuiteSetting::PAGESPEED_KEY];
        $this->assertNotSame(self::KEY, $stored);
        $this->assertSame(self::KEY, GoogleSuiteSetting::pageSpeedKey($this->user->id));
        $this->assertArrayNotHasKey(GoogleSuiteSetting::PAGESPEED_KEY, GoogleSuiteSetting::visible(GoogleSuiteSetting::for($this->user->id)));
        $page = $this->get(route('admin.google.website-performance'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::KEY, $page);
        $this->assertStringNotContainsString($stored, $page);

        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.9, 'auditRefs' => []]], 'audits' => []]])]);
        $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://brand.com/', 'strategy' => 'mobile']))->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('X-goog-api-key', self::KEY) && ! str_contains($r->url(), self::KEY));

        $this->deleteJson(route('admin.google.api.pagespeed-key'))->assertOk()->assertJsonPath('pagespeed_key.saved', false);
        $this->assertNull(GoogleSuiteSetting::pageSpeedKey($this->user->id));
    }

    public function test_a_refused_workspace_key_says_so_instead_of_blaming_setup(): void
    {
        GoogleSuiteSetting::setPageSpeedKey($this->user->id, self::KEY);
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400)]);

        $this->assertSame('pagespeed_key_invalid', $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://brand.com/', 'strategy' => 'mobile']))->json('error.code'));
    }

    public function test_without_any_key_a_refused_test_points_to_setup(): void
    {
        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded']], 429)]);

        $this->assertSame('pagespeed_setup', $this->getJson(route('admin.google.api.pagespeed', ['url' => 'https://brand.com/', 'strategy' => 'mobile']))->json('error.code'));
    }

    /* ---------------- Business Profile ---------------- */

    private function signIn(array $scopes = [BusinessProfileService::SCOPE]): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::OAUTH, 'provider_app' => 'posts.google',
            'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_at' => now()->addHour(), 'status' => SocialConnection::ACTIVE,
            'granted_scopes' => $scopes, 'capabilities' => ['posting'],
        ]);
    }

    private function location(array $attributes = []): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'google', 'account_type' => 'location', 'name' => 'Riyadh Store',
            'platform_account_id' => '555', 'access_token' => 'access-1', 'has_posting_permission' => true,
            'metadata' => ['parent_account_id' => '111', 'address' => 'King Fahd Rd, Riyadh', 'maps_uri' => 'https://maps.google.com/?cid=1'],
        ], $attributes));
    }

    public function test_signing_in_imports_business_profile_locations(): void
    {
        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/111', 'accountName' => 'Brand']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response(['locations' => [[
                'name' => 'locations/555', 'title' => 'Riyadh Store', 'websiteUri' => 'https://brand.com',
                'storefrontAddress' => ['addressLines' => ['King Fahd Rd'], 'locality' => 'Riyadh', 'regionCode' => 'SA'],
                'metadata' => ['mapsUri' => 'https://maps.google.com/?cid=1', 'newReviewUri' => 'https://g.page/r/x/review', 'placeId' => 'ChIJ1'],
            ]]]),
        ]);

        $assetIds = [];
        $method = new \ReflectionMethod(SocialAuthService::class, 'connectBusinessLocations');
        $count = $method->invokeArgs(app(SocialAuthService::class), ['access-1', 'refresh-1', now()->addHour(), $this->user->id, [BusinessProfileService::SCOPE], &$assetIds]);

        $this->assertSame(1, $count);
        $location = SocialAccount::where(['platform' => 'google', 'account_type' => 'location'])->sole();
        $this->assertSame('555', $location->platform_account_id);
        // What GooglePostService needs for local posts and review replies.
        $this->assertSame('111', $location->metadata['parent_account_id']);
        $this->assertSame('King Fahd Rd, Riyadh, SA', $location->metadata['address']);
        $this->assertSame([$location->id], $assetIds);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'accounts/111/locations') && str_contains($r->url(), 'readMask='));

        // Without the scope nothing is called.
        Http::fake();
        $this->assertNull($method->invokeArgs(app(SocialAuthService::class), ['a', null, now(), $this->user->id, [], &$assetIds]));
        Http::assertNothingSent();
    }

    public function test_performance_totals_and_trend_come_from_google_with_omitted_zeros(): void
    {
        $this->signIn();
        $location = $this->location();
        $range = ReportRange::make('7d');
        $day = fn ($date) => ['year' => $date->year, 'month' => $date->month, 'day' => $date->day];

        Http::fake(['businessprofileperformance.googleapis.com/*' => Http::response(['multiDailyMetricTimeSeries' => [['dailyMetricTimeSeries' => [
            ['dailyMetric' => 'BUSINESS_IMPRESSIONS_MOBILE_MAPS', 'timeSeries' => ['datedValues' => [['date' => $day($range->start), 'value' => '40'], ['date' => $day($range->previousStart), 'value' => '10']]]],
            ['dailyMetric' => 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'timeSeries' => ['datedValues' => [['date' => $day($range->end), 'value' => '60']]]],
            // Google omits "value" for zero days.
            ['dailyMetric' => 'CALL_CLICKS', 'timeSeries' => ['datedValues' => [['date' => $day($range->start), 'value' => '5'], ['date' => $day($range->end)]]]],
            ['dailyMetric' => 'WEBSITE_CLICKS', 'timeSeries' => ['datedValues' => [['date' => $day($range->end), 'value' => '15']]]],
        ]]]])]);

        $data = $this->getJson(route('admin.google.api.business-profile', ['range' => '7d', 'location' => $location->id]))->assertOk()->json('data');

        $this->assertSame(100, $data['totals']['current']['views']);
        $this->assertSame(10, $data['totals']['previous']['views']);
        $this->assertSame(5, $data['totals']['current']['calls']);
        $this->assertSame(0, $data['totals']['current']['directions']);
        $this->assertSame(['search' => 60, 'maps' => 40], $data['view_sources']);
        $this->assertEqualsWithDelta(0.2, $data['action_rate'], 0.0001);
        $this->assertCount(7, $data['trend']['dates']);
        $this->assertSame(40, $data['trend']['views']['current'][0]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'locations/555:fetchMultiDailyMetricsTimeSeries')
            && str_contains($r->url(), 'dailyMetrics=CALL_CLICKS&dailyMetrics=')
            && ! str_contains($r->url(), 'dailyMetrics%5B'));
    }

    public function test_reviews_are_listed_and_a_reply_is_published(): void
    {
        $this->signIn();
        $location = $this->location();
        Http::fake([
            'mybusiness.googleapis.com/v4/accounts/111/locations/555/reviews/r1/reply' => Http::response(['comment' => 'Thank you!', 'updateTime' => '2026-11-07T10:00:00Z']),
            'mybusiness.googleapis.com/v4/accounts/111/locations/555/reviews*' => Http::response([
                'averageRating' => 4.56, 'totalReviewCount' => 2, 'nextPageToken' => 'p2',
                'reviews' => [
                    ['reviewId' => 'r1', 'reviewer' => ['displayName' => 'Sara'], 'starRating' => 'FIVE', 'comment' => 'Great service', 'createTime' => '2026-11-01T09:00:00Z'],
                    ['reviewId' => 'r2', 'reviewer' => ['isAnonymous' => true, 'displayName' => 'x'], 'starRating' => 'THREE', 'reviewReply' => ['comment' => 'Thanks', 'updateTime' => '2026-11-02T09:00:00Z']],
                ],
            ]),
        ]);

        $data = $this->getJson(route('admin.google.api.business-profile.reviews', ['location' => $location->id]))->assertOk()->json('data');
        $this->assertSame(4.6, $data['average_rating']);
        $this->assertSame('p2', $data['next_page_token']);
        $this->assertSame([5, 3], array_column($data['reviews'], 'rating'));
        $this->assertNull($data['reviews'][1]['author']);
        $this->assertSame('Thanks', $data['reviews'][1]['reply']['comment']);

        $this->putJson(route('admin.google.api.business-profile.reply'), ['location' => $location->id, 'review_id' => 'r1', 'comment' => '  Thank you!  '])
            ->assertOk()->assertJsonPath('data.reply.comment', 'Thank you!');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/reviews/r1/reply') && $r['comment'] === 'Thank you!');

        $this->putJson(route('admin.google.api.business-profile.reply'), ['location' => $location->id, 'review_id' => '../x', 'comment' => 'hi'])->assertStatus(422);
    }

    public function test_missing_permission_and_other_workspaces_locations_are_refused(): void
    {
        $this->signIn(['https://www.googleapis.com/auth/youtube']);
        $location = $this->location();
        Http::fake();

        $this->assertSame('missing_scope', $this->getJson(route('admin.google.api.business-profile', ['location' => $location->id]))->json('error.code'));

        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $foreign = $this->location(['user_id' => $other->id, 'platform_account_id' => '999']);
        $this->assertSame('not_found', $this->getJson(route('admin.google.api.business-profile.reviews', ['location' => $foreign->id]))->json('error.code'));
        Http::assertNothingSent();
    }

    public function test_the_page_boots_with_locations_and_no_tokens(): void
    {
        $this->signIn();
        $this->location();

        $status = BusinessProfileService::status($this->user->id);
        $this->assertTrue($status['enabled'] && $status['connected'] && $status['granted']);
        $this->assertSame('Riyadh Store', $status['locations'][0]['name']);

        $page = $this->get(route('admin.google.business-profile'))->assertOk()->getContent();
        $this->assertStringContainsString('Riyadh Store', $page);
        $this->assertStringNotContainsString('access-1', $page);
        $this->assertStringNotContainsString('refresh-1', $page);
    }

    public function test_a_location_is_never_picked_as_a_google_ads_account(): void
    {
        $this->location();

        $this->assertNull(AdAccountSelection::resolve($this->user->id, 'google'));
    }
}
