<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AdServices\XAdService;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\XDriver;
use App\Services\MessagingServices\XMessagingService;
use App\Support\Connections\XOAuth1;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 10a: X driver on posts.x, X Ads secret encrypted on the connection. */
class XConnectionTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        Settings::set('posts.x.client_id', 'x-oauth2-client');
        Settings::set('posts.x.client_secret', 'x-oauth2-secret');
        // OAuth 1.0a signs with the app's API Key/Secret; the OAuth 2.0
        // Client ID in ads.x.client_id is not a consumer key.
        Settings::set('ads.x.client_id', 'x-oauth2-client');
        Settings::set('posts.x.consumer_key', 'api-key');
        Settings::set('posts.x.consumer_secret', 'api-key-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_signer_matches_xs_published_example(): void
    {
        // X's "Creating a signature" documentation example.
        $params = [
            'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!',
            'include_entities' => 'true',
            'oauth_consumer_key' => 'xvz1evFS4wEEPTGEFPHBog',
            'oauth_nonce' => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '1318622958',
            'oauth_token' => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
            'oauth_version' => '1.0',
        ];

        $this->assertSame('hCtSmYh+iHYCEqBWrE7C7hYmtUk=', XOAuth1::signature(
            'POST',
            'https://api.twitter.com/1.1/statuses/update.json',
            $params,
            'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
            'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE',
        ));
    }

    public function test_ads_token_secret_comes_from_the_connection_before_legacy_metadata(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::ADS, 'access_token' => 't', 'token_secret' => 'encrypted-secret', 'status' => 'active']);
        $account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'ads1', 'name' => 'Ads', 'access_token' => 't', 'has_ads_permission' => true, 'metadata' => ['legacy_token_secret' => 'plain-secret'], 'social_connection_id' => $connection->id]);
        $legacy = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'ads2', 'name' => 'Old', 'access_token' => 't', 'has_ads_permission' => true, 'metadata' => ['legacy_token_secret' => 'plain-secret']]);

        $service = app(XAdService::class);
        $secret = fn (SocialAccount $a) => (function () use ($a) { $this->account = $a; return $this->tokenSecret(); })->call($service);

        $this->assertSame('encrypted-secret', $secret($account->fresh()));
        $this->assertSame('plain-secret', $secret($legacy));
        $this->assertNotSame('encrypted-secret', \DB::table('social_connections')->value('token_secret'));
    }

    public function test_x_card_steps_and_connect_targets(): void
    {
        $steps = collect(app(XDriver::class)->steps())->keyBy('key');
        $this->assertTrue($steps[XDriver::OAUTH2]['primary']);
        $this->assertTrue($steps[XDriver::ADS]['available']);

        $this->assertStringContainsString('messaging/auth/x/redirect', app(ConnectionService::class)->connect('x', XDriver::OAUTH2)->headers->get('Location'));
        $this->assertSame('hub', session('social_oauth_return_to'));
        $this->assertStringContainsString('ads/x/redirect', app(ConnectionService::class)->connect('x', XDriver::ADS)->headers->get('Location'));

        Settings::set('connections.flags.x.ads', '0');
        $this->assertSame([XDriver::OAUTH2], collect(app(XDriver::class)->steps())->pluck('key')->all());
    }

    public function test_oauth2_validation_mirrors_the_accounts_rotated_tokens(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::OAUTH2, 'access_token' => 'stale', 'refresh_token' => 'stale-r', 'status' => 'error']);
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'u1', 'name' => 'Me', 'access_token' => 'rotated', 'refresh_token' => 'rotated-r', 'expires_at' => now()->addHour(), 'social_connection_id' => $connection->id]);
        Http::fake(['api.x.com/2/users/me' => Http::response(['data' => ['id' => 'u1']])]);

        $result = app(XDriver::class)->validate($connection);

        $this->assertSame(SocialConnection::ACTIVE, $result->status);
        $this->assertSame('rotated', $result->fresh()->access_token);
        $this->assertSame('rotated-r', $result->fresh()->refresh_token);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer rotated'));
    }

    public function test_oauth2_validation_never_refreshes_and_maps_401(): void
    {
        $expired = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::OAUTH2, 'status' => 'active', 'provider_account_id' => 'a']);
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'e1', 'name' => 'E', 'access_token' => 'old', 'refresh_token' => 'r', 'expires_at' => now()->subHour(), 'social_connection_id' => $expired->id]);
        $revoked = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::OAUTH2, 'status' => 'active', 'provider_account_id' => 'b']);
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => 'r1', 'name' => 'R', 'access_token' => 'dead', 'refresh_token' => 'r', 'expires_at' => now()->addHour(), 'social_connection_id' => $revoked->id]);
        Http::fake(['*' => Http::response(['title' => 'Unauthorized'], 401)]);

        // Expired access token: no call (a refresh would rotate the token under the services).
        $this->assertSame(SocialConnection::ACTIVE, app(XDriver::class)->validate($expired)->status);
        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(XDriver::class)->validate($revoked)->status);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth2/token'));
    }

    public function test_ads_validation_is_signed_and_maps_401(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::ADS, 'access_token' => 'tok', 'token_secret' => 'sec', 'status' => 'active']);
        Http::fake(['*' => Http::response(['errors' => [['message' => 'Unauthorized']]], 401)]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(XDriver::class)->validate($connection)->status);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'ads-api.x.com/12/accounts')
            && str_contains($r->header('Authorization')[0] ?? '', 'oauth_consumer_key="api-key"')
            && str_contains($r->header('Authorization')[0] ?? '', 'oauth_token="tok"'));
    }

    public function test_disconnect_revokes_with_the_right_protocol(): void
    {
        $oauth2 = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::OAUTH2, 'refresh_token' => 'r2', 'status' => 'active', 'provider_account_id' => 'a']);
        $ads = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::ADS, 'access_token' => 'tok', 'token_secret' => 'sec', 'status' => 'active', 'provider_account_id' => 'b']);
        Http::fake(['*' => Http::response([])]);

        app(XDriver::class)->disconnect($oauth2);
        app(XDriver::class)->disconnect($ads);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '2/oauth2/revoke') && $r['token'] === 'r2' && $r->hasHeader('Authorization', 'Basic ' . base64_encode('x-oauth2-client:x-oauth2-secret')));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth/invalidate_token'));
        $this->assertSame(SocialConnection::REVOKED, $ads->fresh()->status);
        $this->assertNull($ads->fresh()->token_secret);
    }

    public function test_inbox_x_connect_records_one_consent_covering_posting(): void
    {
        session(['x_messaging_code_verifier' => 'v']);
        Http::fake([
            'api.x.com/2/oauth2/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 7200, 'scope' => 'tweet.read tweet.write users.read dm.read dm.write offline.access']),
            'api.x.com/2/users/me*' => Http::response(['data' => ['id' => '42', 'name' => 'Me', 'username' => 'me']]),
            '*' => Http::response([], 404),
        ]);

        app(XMessagingService::class)->handleCallback('code');

        $account = SocialAccount::where('platform', 'x')->sole();
        $this->assertTrue($account->has_posting_permission);
        $this->assertTrue($account->has_messaging_permission);
        $connection = SocialConnection::where('platform', 'x')->sole();
        $this->assertSame(XDriver::OAUTH2, $connection->step);
        $this->assertSame('42', $connection->provider_account_id);
        $this->assertSame(['posting', 'messaging'], $connection->capabilities);
        $this->assertSame($connection->id, $account->social_connection_id);
    }
}
