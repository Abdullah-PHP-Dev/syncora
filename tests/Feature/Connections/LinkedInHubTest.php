<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\LinkedInDriver;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 11b: LinkedIn in the Hub. */
class LinkedInHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.linkedin.client_id', 'li-posts');
        Settings::set('posts.linkedin.client_secret', 'li-posts-secret');
        Settings::set('ads.linkedin.client_id', 'li-ads');
        Settings::set('ads.linkedin.client_secret', 'li-ads-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connection(array $attributes = []): SocialConnection
    {
        $connection = SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'linkedin', 'step' => LinkedInDriver::ADS, 'provider_app' => 'ads.linkedin',
            'access_token' => 'old', 'status' => 'active', 'expires_at' => now()->addDays(30),
        ], $attributes));
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => uniqid(), 'name' => 'Ads', 'access_token' => 'current', 'refresh_token' => $attributes['refresh_token'] ?? null, 'expires_at' => $attributes['expires_at'] ?? now()->addDays(30), 'has_ads_permission' => true, 'social_connection_id' => $connection->id]);

        return $connection;
    }

    public function test_introspection_marks_active_with_scopes_using_the_steps_app(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::response(['active' => true, 'status' => 'active', 'scope' => 'r_ads,rw_ads,r_ads_reporting'])]);

        $result = app(LinkedInDriver::class)->validate($connection);

        $this->assertSame(SocialConnection::ACTIVE, $result->status);
        $this->assertSame(['ads', 'insights'], $result->capabilities);
        $this->assertSame('current', $result->fresh()->access_token); // mirrored from the account
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'introspectToken') && $r['client_id'] === 'li-ads' && $r['token'] === 'current');
    }

    public function test_introspection_statuses(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['active' => false, 'status' => 'revoked'])
            ->push(['active' => false, 'status' => 'expired'])
            ->push(['active' => false, 'status' => 'expired'])
            ->push([], 401)]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(LinkedInDriver::class)->validate($this->connection())->status);
        // Expired access token but a refresh token with time left: fine.
        $this->assertSame(SocialConnection::ACTIVE, app(LinkedInDriver::class)->validate($this->connection(['provider_account_id' => 'b', 'refresh_token' => 'r', 'refresh_expires_at' => now()->addDays(200), 'expires_at' => now()->subDay()]))->status);
        // Expired and no refresh token: needs a new consent.
        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(LinkedInDriver::class)->validate($this->connection(['provider_account_id' => 'c', 'expires_at' => now()->subDay()]))->status);
        $this->assertSame(SocialConnection::ERROR, app(LinkedInDriver::class)->validate($this->connection(['provider_account_id' => 'd']))->status);
    }

    public function test_pages_callback_records_the_consent_with_refresh_lifetime(): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-token', 'expires_in' => 5184000, 'refresh_token' => 'li-refresh', 'refresh_token_expires_in' => 31536000, 'scope' => 'w_organization_social,r_organization_admin']),
            'api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:555', 'role' => 'ADMINISTRATOR']]]),
            'api.linkedin.com/rest/organizations/555' => Http::response(['localizedName' => 'Acme']),
            '*' => Http::response(['elements' => []]),
        ]);
        session(['social_oauth_state_linkedin' => 's']);

        app(SocialAuthService::class)->callback('linkedin', 'code', 's');

        $connection = SocialConnection::where('platform', 'linkedin')->sole();
        $this->assertSame(LinkedInDriver::PAGES, $connection->step);
        $this->assertSame('posts.linkedin', $connection->provider_app);
        $this->assertTrue($connection->refresh_expires_at->gt(now()->addDays(360)));
        $this->assertSame(['posting', 'insights'], $connection->capabilities);
        $this->assertSame($connection->id, SocialAccount::where('platform', 'linkedin')->sole()->social_connection_id);
    }

    public function test_backfill_splits_pages_and_ads_by_app(): void
    {
        $page = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => 'o1', 'name' => 'Acme', 'access_token' => 'p', 'expires_at' => now()->addDays(40), 'has_posting_permission' => true]);
        $ad = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => 'a1', 'name' => 'Ads', 'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addDays(40), 'has_ads_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame('posts.linkedin', $page->fresh()->connection->provider_app);
        $this->assertSame(LinkedInDriver::PAGES, $page->fresh()->connection->step);
        $this->assertSame('ads.linkedin', $ad->fresh()->connection->provider_app);
        $this->assertSame(['ads'], $ad->fresh()->connection->capabilities);
    }

    public function test_disconnect_is_local_only(): void
    {
        $connection = $this->connection();
        Http::fake();

        app(LinkedInDriver::class)->disconnect($connection);

        Http::assertNothingSent();
        $this->assertSame(SocialConnection::REVOKED, $connection->fresh()->status);
    }
}
