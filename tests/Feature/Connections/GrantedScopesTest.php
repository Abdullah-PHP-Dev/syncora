<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\GrantedScopes;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Step 0b: OAuth callbacks store what was granted, not what was requested. */
class GrantedScopesTest extends TestCase
{
    use CreatesConnectionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->actingAs(User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]));
    }

    // ---- parser --------------------------------------------------------

    public function test_token_response_formats_are_normalised(): void
    {
        $this->assertSame(['r_ads', 'w_member_social'], GrantedScopes::fromTokenResponse(['scope' => 'w_member_social r_ads']));
        $this->assertSame(['user.info.basic', 'video.publish'], GrantedScopes::fromTokenResponse(['scope' => 'video.publish,user.info.basic']));
        $this->assertSame(['a', 'b'], GrantedScopes::fromTokenResponse(['scope' => ['b', 'a', 'a', ' ']]));
        // Instagram Login: `permissions`, wrapped in data[0].
        $this->assertSame(['instagram_business_basic', 'instagram_business_content_publish'], GrantedScopes::fromTokenResponse([
            'data' => [['access_token' => 't', 'permissions' => 'instagram_business_content_publish,instagram_business_basic']],
        ]));
    }

    public function test_missing_scopes_are_null_and_omitted(): void
    {
        $this->assertNull(GrantedScopes::fromTokenResponse(['access_token' => 't']));
        $this->assertNull(GrantedScopes::fromTokenResponse(['scope' => '']));
        $this->assertNull(GrantedScopes::fromTokenResponse(null));
        $this->assertSame([], GrantedScopes::attributes(null));
        $this->assertSame(['scopes' => ['x']], GrantedScopes::attributes(['x']));
    }

    public function test_meta_permissions_exclude_declined(): void
    {
        $this->assertSame(['ads_read', 'pages_show_list'], GrantedScopes::fromMetaPermissions(['data' => [
            ['permission' => 'pages_show_list', 'status' => 'granted'],
            ['permission' => 'ads_management', 'status' => 'declined'],
            ['permission' => 'ads_read', 'status' => 'granted'],
        ]]));
        $this->assertNull(GrantedScopes::fromMetaPermissions(['error' => ['message' => 'x']]));
    }

    // ---- callbacks -----------------------------------------------------

    private function fakeLinkedIn(array $tokenExtra): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-token', 'expires_in' => 5184000] + $tokenExtra),
            'api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:555060042', 'role' => 'ADMINISTRATOR']]]),
            'api.linkedin.com/rest/organizations/555060042' => Http::response(['localizedName' => 'Socialeaz', 'vanityName' => 'socialeaz']),
            'api.linkedin.com/rest/adAccountUsers*' => Http::response(['elements' => []]),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_linkedin_callback_stores_granted_scopes(): void
    {
        session(['social_oauth_state_linkedin' => 'state-1']);
        $this->fakeLinkedIn(['scope' => 'w_organization_social,r_organization_social']);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertSame(['r_organization_social', 'w_organization_social'], SocialAccount::where('platform', 'linkedin')->firstOrFail()->scopes);
    }

    public function test_reconnect_without_scope_info_keeps_recorded_scopes(): void
    {
        session(['social_oauth_state_linkedin' => 'state-1']);
        $this->fakeLinkedIn(['scope' => 'w_organization_social']);
        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        session(['social_oauth_state_linkedin' => 'state-2']);
        $this->fakeLinkedIn([]);
        app(SocialAuthService::class)->callback('linkedin', 'code-2', 'state-2');

        $this->assertSame(['w_organization_social'], SocialAccount::where('platform', 'linkedin')->firstOrFail()->scopes);
    }

    public function test_facebook_callback_stores_granted_permissions_on_page_and_ad_rows(): void
    {
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        Settings::set('posts.facebook.client_id', 'app-1');
        Settings::set('posts.facebook.client_secret', 'app-secret');
        session(['social_oauth_state_facebook' => 'state-fb']);
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token', 'expires_in' => 5183944]),
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
                ['permission' => 'pages_show_list', 'status' => 'granted'],
                ['permission' => 'ads_management', 'status' => 'granted'],
                ['permission' => 'instagram_manage_messages', 'status' => 'declined'],
            ]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
                ['id' => '111', 'name' => 'Page One', 'access_token' => 'page-token', 'category' => 'Brand'],
            ]]),
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [
                ['id' => 'act_9', 'name' => 'Ads One', 'account_status' => 1, 'currency' => 'SAR'],
            ]]),
            '*' => Http::response([], 404),
        ]);

        app(SocialAuthService::class)->callback('facebook', 'code-fb', 'state-fb');

        $expected = ['ads_management', 'pages_show_list'];
        $this->assertSame($expected, SocialAccount::where('platform_account_id', '111')->firstOrFail()->scopes);
        $this->assertSame($expected, SocialAccount::where('platform_account_id', 'act_9')->firstOrFail()->scopes);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'me/permissions') && str_contains($r->url(), 'appsecret_proof'));

        // Step 0c: page token on the asset, user token kept, no fake refresh token.
        $page = SocialAccount::where('platform_account_id', '111')->firstOrFail();
        $this->assertNull($page->refresh_token);
        $this->assertSame('page-token', $page->asset_token);
        $this->assertSame('page-token', $page->access_token);
        $this->assertSame('user-token', $page->user_token);
    }
}
