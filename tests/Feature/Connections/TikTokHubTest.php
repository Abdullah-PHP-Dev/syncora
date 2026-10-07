<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\TikTokDriver;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Commit 11c: TikTok in the Hub; the TikTok DM connect (flow #14) is gone. */
class TikTokHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.tiktok.client_id', 'tt-key');
        Settings::set('posts.tiktok.client_secret', 'tt-secret');
        Settings::set('ads.tiktok.client_id', 'tt-ads');
        Settings::set('ads.tiktok.client_secret', 'tt-ads-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connection(string $step, array $asset = []): SocialConnection
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'step' => $step, 'provider_account_id' => uniqid(), 'status' => 'active']);
        SocialAccount::create(array_merge(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => uniqid(), 'name' => 'T', 'access_token' => 'tok', 'refresh_token' => 'r', 'expires_at' => now()->addHour(), 'social_connection_id' => $connection->id], $asset));

        return $connection;
    }

    public function test_dm_connect_flow_is_removed(): void
    {
        $this->assertFalse(Route::has('admin.messaging.auth.tiktok.redirect'));
        $this->assertFalse(Route::has('admin.messaging.auth.tiktok.callback'));
        $this->assertFalse(method_exists(\App\Services\MessagingServices\TiktokMessagingService::class, 'handleCallback'));
    }

    public function test_steps_and_connect_targets(): void
    {
        $this->assertSame([TikTokDriver::LOGIN_KIT, TikTokDriver::BUSINESS], collect(app(TikTokDriver::class)->steps())->pluck('key')->all());
        $this->assertStringContainsString('tiktok.com/v2/auth/authorize', app(ConnectionService::class)->connect('tiktok', TikTokDriver::LOGIN_KIT)->headers->get('Location'));
        $this->assertStringContainsString('ads/tiktok/redirect', app(ConnectionService::class)->connect('tiktok', TikTokDriver::BUSINESS)->headers->get('Location'));
    }

    public function test_probes(): void
    {
        Http::fake([
            'open.tiktokapis.com/*' => Http::response(['error' => ['code' => 'access_token_invalid', 'message' => 'The access token is invalid']], 401),
            'business-api.tiktok.com/*' => Http::response(['code' => 40105, 'message' => 'Access token is incorrect or has been revoked.']),
        ]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(TikTokDriver::class)->validate($this->connection(TikTokDriver::LOGIN_KIT))->status);
        $business = app(TikTokDriver::class)->validate($this->connection(TikTokDriver::BUSINESS, ['refresh_token' => null, 'expires_at' => null, 'has_ads_permission' => true]));
        $this->assertSame(SocialConnection::NEEDS_REAUTH, $business->status);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth2/advertiser/get') && $r->hasHeader('Access-Token', 'tok'));
    }

    public function test_login_kit_disconnect_revokes(): void
    {
        $connection = $this->connection(TikTokDriver::LOGIN_KIT);
        Http::fake(['*' => Http::response([])]);

        app(TikTokDriver::class)->disconnect($connection);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'v2/oauth/revoke') && $r['client_key'] === 'tt-key' && $r['token'] === 'tok');
        $this->assertSame(SocialConnection::REVOKED, $connection->fresh()->status);
    }

    public function test_login_kit_callback_records_the_consent(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'open_id' => 'oid-1', 'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'scope' => 'user.info.basic,video.publish']),
            '*' => Http::response(['data' => ['user' => ['display_name' => 'Me']]]),
        ]);
        session(['social_oauth_state_tiktok' => 's', 'tiktok_code_verifier' => 'v', 'social_oauth_code_verifier_tiktok' => 'v']);

        app(SocialAuthService::class)->callback('tiktok', 'code', 's', 'v');

        $connection = SocialConnection::where('platform', 'tiktok')->sole();
        $this->assertSame(TikTokDriver::LOGIN_KIT, $connection->step);
        $this->assertSame('oid-1', $connection->provider_account_id);
        $this->assertSame(['posting'], $connection->capabilities);
        $this->assertTrue($connection->refresh_expires_at->gt(now()->addDays(360)));
    }

    public function test_backfill_skips_dm_only_rows(): void
    {
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'msg_1', 'name' => 'DM', 'account_type' => 'business_messaging', 'access_token' => 't']);
        $profile = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'oid', 'name' => 'P', 'account_type' => 'profile', 'access_token' => 't', 'refresh_token' => 'r', 'has_posting_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame(TikTokDriver::LOGIN_KIT, $profile->fresh()->connection->step);
        $this->assertNull(SocialAccount::where('platform_account_id', 'msg_1')->value('social_connection_id'));
    }
}
