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
        $this->assertSame([TikTokDriver::LOGIN_KIT, TikTokDriver::BUSINESS, TikTokDriver::COMMENTS], collect(app(TikTokDriver::class)->steps())->pluck('key')->all());
        // Comments: account-holder authorization of the Business app (no PKCE, no trailing slash).
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize?', app(ConnectionService::class)->connect('tiktok', TikTokDriver::COMMENTS)->headers->get('Location'));
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

    // ---- drawer audit: three sign-ins, each offering only what it can grant ----

    private const ACCOUNT_HOLDER_SCOPES = ['video.list', 'video.insights', 'comment.list', 'user.info.basic', 'user.info.stats', 'comment.list.manage', 'video.publish', 'video.upload'];

    private function card(): array
    {
        return app(\App\Services\Connections\HubPresenter::class)->card($this->user->id, 'tiktok');
    }

    /** Login Kit posting account plus its comments consent, as the two callbacks record them. */
    private function postingAndComments(): array
    {
        $posting = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'oid-1', 'name' => 'Me', 'access_token' => 'lk', 'refresh_token' => 'lk-r', 'has_posting_permission' => true, 'is_token_valid' => true]);
        $loginKit = \App\Services\Connections\ConnectionRecorder::record($this->user->id, 'tiktok', TikTokDriver::LOGIN_KIT, 'oid-1', [
            'access_token' => 'lk', 'refresh_token' => 'lk-r', 'expires_at' => now()->addDay(), 'refresh_expires_at' => now()->addDays(365),
            'granted_scopes' => ['user.info.basic', 'user.info.stats', 'video.list', 'video.publish', 'video.upload'],
        ], [$posting->id]);
        $biz = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => 'biz_B1', 'name' => 'Me', 'account_type' => \App\Services\PostServices\TiktokCommentsService::ACCOUNT_TYPE, 'access_token' => 'biz', 'refresh_token' => 'biz-r', 'expires_at' => now()->addDay(), 'is_token_valid' => true, 'has_posting_permission' => false, 'metadata' => ['business_id' => 'B1', 'posting_account_id' => $posting->id]]);
        $comments = \App\Services\Connections\ConnectionRecorder::record($this->user->id, 'tiktok', TikTokDriver::COMMENTS, 'B1', [
            'access_token' => 'biz', 'refresh_token' => 'biz-r', 'expires_at' => now()->addDay(), 'refresh_expires_at' => now()->addDays(365),
            'granted_scopes' => self::ACCOUNT_HOLDER_SCOPES,
        ], [$biz->id]);

        return [$posting, $loginKit, $biz, $comments];
    }

    public function test_comments_consent_grants_engagement_not_posting(): void
    {
        [, $loginKit, , $comments] = $this->postingAndComments();

        // Same video.publish / user.info.stats scopes, different products.
        $this->assertSame(['posting', 'insights'], $loginKit->capabilities);
        $this->assertSame(['engagement'], $comments->capabilities);

        Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 0, 'data' => ['username' => 'me']])]);
        $this->assertSame(['engagement'], app(TikTokDriver::class)->validate($comments)->capabilities, 'validation keeps the step map');
    }

    public function test_each_consent_offers_only_what_its_sign_in_can_grant(): void
    {
        $this->postingAndComments();
        $ads = $this->connection(TikTokDriver::BUSINESS, ['refresh_token' => null, 'expires_at' => null, 'has_ads_permission' => true]);
        $ads->update(['capabilities' => ['ads']]);

        $connections = collect($this->card()['connections'])->keyBy('step');

        $this->assertSame(['posting', 'insights'], $connections[TikTokDriver::LOGIN_KIT]['offered_capabilities']);
        $this->assertSame(['ads'], $connections[TikTokDriver::BUSINESS]['offered_capabilities']);
        $this->assertSame(['engagement'], $connections[TikTokDriver::COMMENTS]['offered_capabilities']);
        // A comments consent missing comment scopes can ask for them again.
        $this->assertTrue($connections[TikTokDriver::COMMENTS]['upgradable']);
    }

    public function test_comments_account_is_usable_pausable_and_linked_to_its_posting_account(): void
    {
        [$posting, , $biz] = $this->postingAndComments();

        $asset = collect($this->card()['connections'])->firstWhere('step', TikTokDriver::COMMENTS)['assets']['tiktok_comments'][0];
        $this->assertSame([['engagement'], ['engagement'], $posting->id], [$asset['available_capabilities'], $asset['enabled_capabilities'], $asset['linked_asset_id']]);
        $this->assertNull(collect($this->card()['connections'])->firstWhere('step', TikTokDriver::LOGIN_KIT)['assets']['tiktok_account'][0]['linked_asset_id']);

        $this->patchJson(route('admin.connections.assets.update', $biz), ['enabled_capabilities' => []])->assertOk();
        $this->assertSame([], $biz->fresh()->enabled_capabilities);
        $this->patchJson(route('admin.connections.assets.update', $biz), ['enabled_capabilities' => ['engagement']])->assertOk();
        $this->assertSame(['engagement'], $biz->fresh()->enabled_capabilities);
    }

    public function test_expiry_follows_the_refresh_token_not_the_24h_access_token(): void
    {
        $this->postingAndComments();

        $loginKit = collect($this->card()['connections'])->firstWhere('step', TikTokDriver::LOGIN_KIT);
        $this->assertGreaterThan(360, $loginKit['expires_in_days']);
        $this->assertSame(SocialConnection::ACTIVE, $loginKit['status']);

        // Without a refresh token the access token governs, as before.
        SocialConnection::where('step', TikTokDriver::LOGIN_KIT)->update(['refresh_token' => null]);
        $this->assertSame(0, collect($this->card()['connections'])->firstWhere('step', TikTokDriver::LOGIN_KIT)['expires_in_days']);
    }

    public function test_connected_steps_offer_only_useful_actions(): void
    {
        $steps = collect($this->card()['steps'])->keyBy('key');

        $this->assertArrayNotHasKey('connected_action', $steps[TikTokDriver::LOGIN_KIT]); // keeps "Add or change accounts"
        $this->assertSame('Add advertisers', $steps[TikTokDriver::BUSINESS]['connected_action']);
        $this->assertArrayHasKey('connected_action', $steps[TikTokDriver::COMMENTS]);
        $this->assertNull($steps[TikTokDriver::COMMENTS]['connected_action']);
    }

    public function test_disconnecting_comments_leaves_the_posting_account_alone(): void
    {
        [$posting, $loginKit, $biz, $comments] = $this->postingAndComments();
        Http::fake();

        app(TikTokDriver::class)->disconnect($comments);

        Http::assertNothingSent(); // the Accounts API has no user-facing revoke
        $this->assertSame(SocialConnection::REVOKED, $comments->fresh()->status);
        $this->assertSame([false, null], [(bool) $biz->fresh()->is_token_valid, $biz->fresh()->access_token]);
        $this->assertSame(['lk', true], [$posting->fresh()->access_token, (bool) $posting->fresh()->is_token_valid]);
        $this->assertSame(SocialConnection::ACTIVE, $loginKit->fresh()->status);
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
