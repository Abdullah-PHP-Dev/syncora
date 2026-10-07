<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AdServices\SocialAdManagerService;
use App\Services\Connections\Drivers\XDriver;
use App\Support\Connections\HubReturn;
use App\Support\Connections\XOAuth1;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** X Ads from the Hub: OAuth 1.0a with the app's API Key (not the OAuth 2.0 Client ID). */
class XAdsConnectTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('ads.x.client_id', 'b2VNOAuth2ClientId');      // OAuth 2.0 Client ID - must not be used
        Settings::set('ads.x.client_secret', 'oauth2-client-secret');
        Settings::set('posts.x.consumer_key', 'api-key');
        Settings::set('posts.x.consumer_secret', 'api-key-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_consumer_pair_prefers_a_separate_ads_app_then_posts_x(): void
    {
        $this->assertSame(['api-key', 'api-key-secret'], XOAuth1::consumer());

        Settings::set('ads.x.consumer_key', 'ads-api-key');
        Settings::set('ads.x.consumer_secret', 'ads-api-secret');
        $this->assertSame(['ads-api-key', 'ads-api-secret'], XOAuth1::consumer());
    }

    public function test_request_token_is_signed_with_the_api_key(): void
    {
        Http::fake(['api.x.com/oauth/request_token' => Http::response('oauth_token=req&oauth_token_secret=req-secret&oauth_callback_confirmed=true')]);

        $response = app(SocialAdManagerService::class)->redirect('x');

        $this->assertSame('https://api.x.com/oauth/authorize?oauth_token=req', $response->headers->get('Location'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->header('Authorization')[0] ?? '', 'oauth_consumer_key="api-key"'));
    }

    public function test_unapproved_callback_returns_to_the_hub_with_the_fix(): void
    {
        HubReturn::mark();
        Http::fake(['*' => Http::response('<?xml version="1.0"?><errors><error code="415">Callback URL not approved for this client application.</error></errors>', 403)]);

        $response = app(SocialAdManagerService::class)->redirect('x');

        $this->assertStringEndsWith('/connections', $response->headers->get('Location'));
        $this->assertStringContainsString('/ads/x/callback to the X app', session('error'));
    }

    public function test_wrong_credentials_are_named(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['code' => 32, 'message' => 'Could not authenticate you.']]], 401)]);

        app(SocialAdManagerService::class)->redirect('x');

        $this->assertStringContainsString('check the API Key and Secret', session('error'));
    }

    public function test_callback_links_ads_accounts_to_one_consent_and_returns_to_the_hub(): void
    {
        HubReturn::mark();
        session(['x_oauth_token_secret' => 'req-secret']);
        request()->merge(['oauth_token' => 'req', 'oauth_verifier' => 'v']);
        Http::fake([
            'api.x.com/oauth/access_token' => Http::response('oauth_token=acc&oauth_token_secret=acc-secret&user_id=42&screen_name=socialeaz'),
            '*/accounts*' => Http::response(['data' => [
                ['id' => 'ads-1', 'name' => 'Socialeaz Ads', 'approval_status' => 'ACCEPTED', 'timezone' => 'Asia/Riyadh'],
                ['id' => 'ads-2', 'name' => 'Rejected', 'approval_status' => 'REJECTED'],
                ['id' => 'ads-3', 'name' => 'No status'],
            ]]),
        ]);

        $response = app(SocialAdManagerService::class)->callback('x');

        $this->assertStringEndsWith('/connections', $response->headers->get('Location'));
        $this->assertSame('Connected 2 X Ads account(s).', session('success'));
        $connection = SocialConnection::where('step', XDriver::ADS)->sole();
        $this->assertSame('acc-secret', $connection->token_secret);
        $this->assertSame('42', $connection->provider_account_id);
        $this->assertEqualsCanonicalizing(['ads-1', 'ads-3'], SocialAccount::where('social_connection_id', $connection->id)->pluck('platform_account_id')->all());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth/access_token') && str_contains($r->header('Authorization')[0] ?? '', 'oauth_consumer_key="api-key"'));
    }
}
