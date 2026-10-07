<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AdServices\GoogleAdService;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\GoogleClient;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 9b: one Google client, no developer token, Google driver. */
class GoogleConnectionTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.google.client_id', 'posts-client');
        Settings::set('posts.google.client_secret', 'posts-secret');
        Settings::set('ads.google.client_id', 'ads-client');
        Settings::set('ads.google.client_secret', 'ads-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connection(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::OAUTH, 'provider_app' => 'posts.google',
            'access_token' => 'old-access', 'refresh_token' => 'refresh-1', 'expires_at' => now()->subMinute(), 'status' => 'active',
        ], $attributes));
    }

    // ---- client choice -------------------------------------------------

    public function test_flag_picks_the_client_for_new_consents(): void
    {
        $this->assertSame('posts.google', GoogleClient::current());
        Settings::set('connections.flags.google.oauth_client', 'ads');
        $this->assertSame('ads.google', GoogleClient::current());
        $this->assertSame('ads-client', GoogleClient::credentials()['client_id']);
    }

    public function test_refresh_uses_the_client_that_issued_the_token(): void
    {
        $legacy = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'google', 'platform_account_id' => '1', 'name' => 'Legacy', 'refresh_token' => 'r1']);
        $hub = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'google', 'platform_account_id' => '2', 'name' => 'Hub', 'refresh_token' => 'r2', 'social_connection_id' => $this->connection()->id]);

        $this->assertSame('ads.google', GoogleClient::forAccount($legacy, 'ads.google'));
        $this->assertSame('posts.google', GoogleClient::forAccount($hub->fresh(), 'ads.google'));

        Http::fake(['*' => Http::response(['access_token' => 'new', 'expires_in' => 3600])]);
        app(GoogleAdService::class)->refreshToken($hub->fresh());
        Http::assertSent(fn (HttpRequest $r) => ($r['client_id'] ?? null) === 'posts-client' && ($r['refresh_token'] ?? null) === 'r2');
    }

    public function test_ads_headers_send_developer_token_only_when_set(): void
    {
        $this->assertArrayNotHasKey('developer-token', GoogleClient::adsHeaders('t'));

        Settings::set('ads.google.developer_token', 'dev');
        Settings::set('ads.google.login_customer_id', '123-456-7890');
        $headers = GoogleClient::adsHeaders('t');
        $this->assertSame('dev', $headers['developer-token']);
        $this->assertSame('1234567890', $headers['login-customer-id']);
    }

    // ---- unified consent -----------------------------------------------

    public function test_redirect_is_incremental_and_business_profile_is_flagged(): void
    {
        parse_str(parse_url(app(SocialAuthService::class)->redirect('google')->getTargetUrl(), PHP_URL_QUERY), $q);
        $this->assertSame('posts-client', $q['client_id']);
        $this->assertSame('true', $q['include_granted_scopes']);
        $this->assertStringNotContainsString('business.manage', $q['scope']);

        Settings::set('connections.flags.google.business_profile', '1');
        parse_str(parse_url(app(SocialAuthService::class)->redirect('google')->getTargetUrl(), PHP_URL_QUERY), $q);
        $this->assertStringContainsString('auth/business.manage', $q['scope']);
    }

    public function test_callback_records_one_google_connection_and_lists_ads_without_developer_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g-access', 'refresh_token' => 'g-refresh', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/adwords']),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC1', 'snippet' => ['title' => 'My Channel']]]]),
            'googleads.googleapis.com/*customers:listAccessibleCustomers' => Http::response(['resourceNames' => []]),
            '*' => Http::response([], 404),
        ]);
        session(['social_oauth_state_google' => 's']);

        app(SocialAuthService::class)->callback('google', 'code', 's');

        $connection = SocialConnection::where('platform', 'google')->sole();
        $this->assertSame(GoogleDriver::OAUTH, $connection->step);
        $this->assertSame('posts.google', $connection->provider_app);
        $this->assertSame('g-refresh', $connection->refresh_token);
        $this->assertSame(['posting', 'ads'], $connection->capabilities);
        $this->assertSame(SocialConnection::ACTIVE, $connection->status); // refreshable: hourly access-token expiry is fine
        $this->assertSame($connection->id, SocialAccount::where('platform', 'youtube')->sole()->social_connection_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'listAccessibleCustomers') && ! $r->hasHeader('developer-token'));
    }

    // ---- driver --------------------------------------------------------

    public function test_validate_refreshes_with_the_issuing_client_and_reads_scopes(): void
    {
        $connection = $this->connection(['provider_app' => 'ads.google', 'step' => GoogleDriver::ADS_LEGACY]);
        $asset = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'google', 'platform_account_id' => 'c1', 'name' => 'Ads', 'access_token' => 'old-access', 'social_connection_id' => $connection->id]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
            'oauth2.googleapis.com/tokeninfo*' => Http::response(['scope' => 'https://www.googleapis.com/auth/adwords', 'expires_in' => 3599]),
        ]);

        $result = app(GoogleDriver::class)->validate($connection);

        $this->assertSame(SocialConnection::ACTIVE, $result->status);
        $this->assertSame(['ads'], $result->capabilities);
        $this->assertSame('fresh', $result->fresh()->access_token);
        $this->assertSame('fresh', $asset->fresh()->access_token);
        Http::assertSent(fn (HttpRequest $r) => ($r['client_id'] ?? null) === 'ads-client');
    }

    public function test_validate_maps_invalid_grant_to_reconnect(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)
            ->push(['error' => 'unauthorized_client'], 400)]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(GoogleDriver::class)->validate($this->connection())->status);
        $this->assertSame(SocialConnection::ERROR, app(GoogleDriver::class)->validate($this->connection(['provider_account_id' => 'x']))->status);
    }

    public function test_disconnect_revokes_the_refresh_token(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::response([])]);

        app(GoogleDriver::class)->disconnect($connection);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth2.googleapis.com/revoke') && ($r['token'] ?? null) === 'refresh-1');
        $this->assertSame(SocialConnection::REVOKED, $connection->fresh()->status);
        $this->assertNull($connection->fresh()->refresh_token);
    }
}
