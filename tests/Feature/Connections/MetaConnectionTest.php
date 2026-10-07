<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\MetaDriver;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\ConnectionFlags;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** Commit 5: ConnectionService, flags and the Meta driver. */
class MetaConnectionTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        Settings::set('posts.facebook.client_id', 'app-1');
        Settings::set('posts.facebook.client_secret', 'app-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function service(): ConnectionService
    {
        return app(ConnectionService::class);
    }

    private function connection(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'meta', 'step' => MetaDriver::LOGIN,
            'access_token' => 'user-token', 'status' => SocialConnection::ACTIVE, 'capabilities' => ['posting', 'ads'],
            'expires_at' => now()->addDays(40),
        ], $attributes));
    }

    // ---- flags & steps -------------------------------------------------

    public function test_flags_default_from_config_and_override_via_admin_setting(): void
    {
        $this->assertTrue(ConnectionFlags::on('meta.instagram_login'));
        $this->assertSame('posts', ConnectionFlags::get('google.oauth_client'));

        Settings::set('connections.flags.meta.instagram_login', '0');
        Settings::set('connections.flags.google.oauth_client', 'ads');

        $this->assertFalse(ConnectionFlags::on('meta.instagram_login'));
        $this->assertSame('ads', ConnectionFlags::get('google.oauth_client'));
        $this->expectException(\InvalidArgumentException::class);
        ConnectionFlags::get('nope');
    }

    public function test_meta_card_steps_follow_flags(): void
    {
        $steps = collect(app(MetaDriver::class)->steps())->keyBy('key');
        $this->assertTrue($steps[MetaDriver::LOGIN]['available']);
        $this->assertFalse($steps[MetaDriver::WHATSAPP]['available']); // no Embedded Signup config yet
        $this->assertNotNull($steps[MetaDriver::WHATSAPP]['note']);
        $this->assertTrue($steps->has(MetaDriver::INSTAGRAM_LOGIN));

        Settings::set('connections.flags.meta.whatsapp_in_main_config', '1');
        Settings::set('connections.flags.meta.instagram_login', '0');
        $keys = collect(app(MetaDriver::class)->steps())->pluck('key')->all();
        $this->assertSame([MetaDriver::LOGIN], $keys);
    }

    public function test_unavailable_or_unknown_steps_404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->service()->connect('meta', MetaDriver::WHATSAPP);
    }

    // ---- connect -------------------------------------------------------

    public function test_connect_uses_scope_list_with_instagram_messages_and_returns_to_hub(): void
    {
        $response = $this->service()->connect('meta', MetaDriver::LOGIN);

        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('facebook.com', $url);
        $this->assertContains('instagram_manage_messages', explode(',', $query['scope']));
        $this->assertArrayNotHasKey('config_id', $query);
        $this->assertSame('hub', session('social_oauth_return_to'));
    }

    public function test_connect_uses_login_for_business_config_id_when_set(): void
    {
        Settings::set('connections.meta.login_config_id', 'cfg-123');

        parse_str(parse_url($this->service()->connect('meta', MetaDriver::LOGIN)->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('cfg-123', $query['config_id']);
        $this->assertArrayNotHasKey('scope', $query);
    }

    private function fakeFacebookCallback(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token', 'expires_in' => 5183944]),
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_manage_posts', 'status' => 'granted'],
                ['permission' => 'pages_messaging', 'status' => 'granted'],
                ['permission' => 'ads_read', 'status' => 'granted'],
            ]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => '111', 'name' => 'Page One', 'access_token' => 'page-token']]]),
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_9', 'name' => 'Ads', 'account_status' => 1]]]),
            'graph.facebook.com/*/me?*' => Http::response(['id' => 'fb-user-77']),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_facebook_callback_records_one_connection_and_links_assets(): void
    {
        $this->fakeFacebookCallback();

        session(['social_oauth_state_facebook' => 's1']);
        app(SocialAuthService::class)->callback('facebook', 'code', 's1');
        session(['social_oauth_state_facebook' => 's2']);
        app(SocialAuthService::class)->callback('facebook', 'code', 's2'); // reconnect

        $connection = SocialConnection::sole();
        $this->assertSame('fb-user-77', $connection->provider_account_id);
        $this->assertSame('user-token', $connection->access_token);
        $this->assertSame(['ads_read', 'pages_manage_posts', 'pages_messaging'], $connection->granted_scopes);
        $this->assertSame(['posting', 'messaging', 'ads'], $connection->capabilities);
        $this->assertSame(SocialConnection::ACTIVE, $connection->status);
        $this->assertSame(2, SocialAccount::where('social_connection_id', $connection->id)->count());
    }

    public function test_first_connect_adopts_the_backfilled_connection(): void
    {
        $backfilled = $this->connection(['provider_account_id' => null, 'access_token' => 'old']);
        $this->fakeFacebookCallback();

        session(['social_oauth_state_facebook' => 's1']);
        app(SocialAuthService::class)->callback('facebook', 'code', 's1');

        $this->assertSame(1, SocialConnection::count());
        $this->assertSame('fb-user-77', $backfilled->fresh()->provider_account_id);
        $this->assertSame('user-token', $backfilled->fresh()->access_token);
    }

    // ---- tokenFor / ensure ---------------------------------------------

    public function test_token_for_picks_asset_token_then_own_token_then_connection(): void
    {
        $connection = $this->connection();
        $page = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'p', 'name' => 'P', 'access_token' => 'page-legacy', 'asset_token' => 'page-token', 'social_connection_id' => $connection->id]);
        $ad = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'a', 'name' => 'A', 'access_token' => 'ad-own', 'social_connection_id' => $connection->id]);
        $tokenless = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 't', 'name' => 'T', 'social_connection_id' => $connection->id]);
        $unlinked = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'x', 'name' => 'X', 'access_token' => 'x-legacy']);

        $this->assertSame('page-token', $this->service()->tokenFor($page, 'posting'));
        $this->assertSame('ad-own', $this->service()->tokenFor($ad, 'ads'));
        $this->assertSame('user-token', $this->service()->tokenFor($tokenless, 'ads'));
        $this->assertSame('x-legacy', $this->service()->tokenFor($unlinked, 'posting'));

        $page->update(['enabled_capabilities' => ['messaging']]);
        $this->assertNull($this->service()->tokenFor($page->fresh(), 'posting'));

        $connection->update(['status' => SocialConnection::REVOKED]);
        $this->assertNull($this->service()->tokenFor($ad->fresh(), 'ads'));
    }

    public function test_ensure_says_connect_reconnect_or_upgrade(): void
    {
        $this->assertSame('not_connected', $this->service()->ensure($this->user->id, 'meta', 'ads')['reason']);

        $connection = $this->connection(['capabilities' => ['posting']]);
        $result = $this->service()->ensure($this->user->id, 'meta', 'ads');
        $this->assertSame('upgrade', $result['reason']);
        $this->assertStringContainsString('connections/meta/connect/meta.login', $result['url']);

        $this->assertTrue($this->service()->ensure($this->user->id, 'meta', 'posting')['ok']);

        $connection->update(['status' => SocialConnection::NEEDS_REAUTH]);
        $this->assertSame('reconnect', $this->service()->ensure($this->user->id, 'meta', 'posting')['reason']);
    }

    // ---- validate / disconnect -----------------------------------------

    public function test_validate_refreshes_scopes_and_status(): void
    {
        $connection = $this->connection(['granted_scopes' => null, 'status' => SocialConnection::ERROR]);
        Http::fake(['graph.facebook.com/*/me/permissions*' => Http::response(['data' => [['permission' => 'instagram_manage_messages', 'status' => 'granted']]])]);

        $this->service()->validate($connection);

        $connection->refresh();
        $this->assertSame(SocialConnection::ACTIVE, $connection->status);
        $this->assertSame(['messaging'], $connection->capabilities);
        $this->assertNotNull($connection->last_checked_at);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'appsecret_proof='));
    }

    public function test_validate_maps_meta_token_errors(): void
    {
        $connection = $this->connection();
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['message' => 'App not installed', 'code' => 190, 'error_subcode' => 458]], 400)
            ->push(['error' => ['message' => 'Session expired', 'code' => 190, 'error_subcode' => 463]], 400)
            ->push(['error' => ['message' => 'Rate limited', 'code' => 4]], 400)]);

        $this->assertSame(SocialConnection::REVOKED, $this->service()->validate($connection)->status);
        $this->assertNotNull($connection->fresh()->revoked_at);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, $this->service()->validate($connection->fresh())->status);

        $result = $this->service()->validate($connection->fresh());
        $this->assertSame(SocialConnection::ERROR, $result->status);
        $this->assertSame('Rate limited', $result->last_error);
    }

    public function test_disconnect_revokes_at_meta_and_locally(): void
    {
        $connection = $this->connection();
        $page = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'p', 'name' => 'P', 'access_token' => 'page', 'asset_token' => 'page', 'is_token_valid' => true, 'social_connection_id' => $connection->id]);
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->service()->disconnect($connection);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), 'me/permissions'));
        $connection->refresh();
        $this->assertSame(SocialConnection::REVOKED, $connection->status);
        $this->assertNull($connection->access_token);
        $page->refresh();
        $this->assertFalse($page->is_token_valid);
        $this->assertNull($page->asset_token);
        $this->assertSame($connection->id, $page->social_connection_id); // history kept
    }
}
