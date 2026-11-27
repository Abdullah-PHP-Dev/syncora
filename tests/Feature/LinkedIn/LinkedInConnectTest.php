<?php

namespace Tests\Feature\LinkedIn;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LinkedIn posting connect (SocialAuthService::callbackLinkedin): Company
 * Pages the user administers are saved as posting accounts, using an
 * active LinkedIn-Version, and failures are reported instead of a silent
 * "Connected 0".
 */
class LinkedInConnectTest extends TestCase
{
    use \Tests\Feature\Connections\CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
            '2026_10_07_100000_add_asset_and_user_tokens_to_social_accounts_table.php',
            '2026_10_07_110000_create_social_connections_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('admin_settings')) {
            $this->createSettingsTable();
        }
        \App\Support\Settings::set('posts.linkedin.client_id', 'li-client');
        $this->user = User::create(['name' => 'Seller', 'email' => 'seller@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        session(['social_oauth_state_linkedin' => 'state-1']);
    }

    private function fakeLinkedIn(array $acls, int $aclsStatus = 200): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-token', 'expires_in' => 5184000]),
            'api.linkedin.com/rest/organizationAcls*' => Http::response($acls, $aclsStatus),
            'api.linkedin.com/rest/organizations/555060042' => Http::response(['localizedName' => 'Socialeaz', 'vanityName' => 'socialeaz']),
            'api.linkedin.com/rest/adAccountUsers*' => Http::response(['elements' => []]),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_company_page_is_saved_as_a_posting_account(): void
    {
        $this->fakeLinkedIn(['elements' => [['organization' => 'urn:li:organization:555060042', 'role' => 'ADMINISTRATOR']]]);

        $response = app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('Connected 1 LinkedIn Organization', session('success'));
        $account = SocialAccount::where('platform', 'linkedin')->firstOrFail();
        $this->assertSame('555060042', $account->platform_account_id);
        $this->assertSame('Socialeaz', $account->name);
        $this->assertSame('organization', $account->account_type);
        $this->assertTrue($account->has_posting_permission);
        // An active API version, not the retired 202401.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'organizationAcls') && $r->hasHeader('LinkedIn-Version', '202606'));
        Http::assertNotSent(fn (HttpRequest $r) => $r->hasHeader('LinkedIn-Version', '202401'));
        $this->assertTrue($response->isRedirect());
    }

    public function test_rejected_acls_call_shows_linkedins_reason(): void
    {
        $this->fakeLinkedIn(['status' => 426, 'message' => 'Requested version 20240101 is not active'], 426);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('Requested version 20240101 is not active', session('error'));
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_no_admin_pages_explains_what_to_do(): void
    {
        $this->fakeLinkedIn(['elements' => []]);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $this->assertStringContainsString('No LinkedIn Company Pages found', session('error'));
    }

    public function test_provider_error_is_shown(): void
    {
        Http::fake();

        app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'The user cancelled LinkedIn login');

        $this->assertStringContainsString('The user cancelled LinkedIn login', session('error'));
        Http::assertNothingSent();
    }

    /** @return string[] the scopes a LinkedIn consent redirect asks for */
    private function requestedScopes($response): array
    {
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

        return explode(' ', $query['scope']);
    }

    public function test_the_consent_asks_for_the_comment_and_statistics_permissions(): void
    {
        $response = app(SocialAuthService::class)->redirect('linkedin');

        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization?', $response->getTargetUrl());
        $this->assertSame(SocialAuthService::LINKEDIN_SCOPES, $this->requestedScopes($response));
        foreach (['r_organization_social_feed', 'w_organization_social_feed', 'rw_organization_admin', 'w_organization_social', 'r_organization_followers'] as $scope) {
            $this->assertContains($scope, $this->requestedScopes($response));
        }
    }

    public function test_a_scope_the_app_isnt_approved_for_is_dropped_and_the_consent_restarted(): void
    {
        Http::fake();

        // LinkedIn: ?error=unauthorized_scope_error&error_description=Scope "..." is not authorized for your application
        $response = app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'Scope &quot;r_organization_social_feed&quot; is not authorized for your application');

        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization?', $response->getTargetUrl());
        $this->assertNotContains('r_organization_social_feed', $this->requestedScopes($response));
        $this->assertContains('rw_organization_admin', $this->requestedScopes($response));
        $this->assertNull(session('error'));
        // Remembered: later connects skip it straight away.
        $this->assertNotContains('r_organization_social_feed', $this->requestedScopes(app(SocialAuthService::class)->redirect('linkedin')));
        Http::assertNothingSent();
    }

    public function test_a_refused_posting_scope_or_a_repeat_refusal_fails_with_linkedins_reason(): void
    {
        // Posting scopes are never dropped - the connection would be useless.
        app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'Scope "w_organization_social" is not authorized for your application');
        $this->assertStringContainsString('w_organization_social', session('error'));

        // An optional scope already dropped and refused again: no redirect loop.
        \App\Support\Settings::set(SocialAuthService::linkedinRefusedKey(), 'rw_organization_admin');
        session(['social_oauth_state_linkedin' => 'state-1']);
        $response = app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'Scope "rw_organization_admin" is not authorized for your application');
        $this->assertStringNotContainsString('linkedin.com/oauth', $response->getTargetUrl());
    }

    public function test_the_consent_asks_only_for_scopes_the_posting_app_has(): void
    {
        // The posting app's Auth tab (2026-11-27). LinkedIn rejects the whole
        // consent for any scope outside it - r_organization_admin was one.
        $approved = ['r_basicprofile', 'w_member_social', 'w_member_social_feed', 'w_organization_social', 'w_organization_social_feed', 'r_organization_social',
            'r_organization_social_feed', 'r_organization_followers', 'rw_organization_admin', 'r_member_postAnalytics', 'r_member_profileAnalytics', 'r_1st_connections_size'];

        $scopes = $this->requestedScopes(app(SocialAuthService::class)->redirect('linkedin'));

        $this->assertSame([], array_diff($scopes, $approved));
        $this->assertNotContains('r_organization_admin', $scopes);
        foreach (['openid', 'profile', 'email', 'r_liteprofile', 'r_emailaddress'] as $notGranted) {
            $this->assertNotContains($notGranted, $scopes);
        }
        $this->assertContains('rw_organization_admin', $scopes);
    }

    public function test_a_refused_required_scope_explains_what_the_app_needs(): void
    {
        $response = app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'Scope &quot;rw_organization_admin&quot; is not authorized for your application');

        $this->assertStringNotContainsString('linkedin.com/oauth', $response->getTargetUrl());
        $this->assertStringContainsString('Scope "rw_organization_admin" is not authorized', session('error'));
        $this->assertStringContainsString('posts.linkedin.client_id', session('error'));
        $this->assertNull(adminSetting(SocialAuthService::linkedinRefusedKey()));
    }

    public function test_a_scope_refused_by_one_app_is_asked_again_with_another_app(): void
    {
        app(SocialAuthService::class)->callback('linkedin', '', 'state-1', null, 'Scope "r_organization_social_feed" is not authorized for your application');
        $this->assertNotContains('r_organization_social_feed', $this->requestedScopes(app(SocialAuthService::class)->redirect('linkedin')));

        // posts.linkedin.client_id switched to the Community Management app.
        \App\Support\Settings::set('posts.linkedin.client_id', 'li-community-app');
        $this->assertContains('r_organization_social_feed', $this->requestedScopes(app(SocialAuthService::class)->redirect('linkedin')));
    }

    public function test_the_callback_url_sent_is_the_non_localized_one(): void
    {
        parse_str((string) parse_url(app(SocialAuthService::class)->redirect('linkedin')->getTargetUrl(), PHP_URL_QUERY), $query);

        $this->assertStringEndsWith('/social-accounts/linkedin/callback', $query['redirect_uri']);
        $this->assertStringNotContainsString('/en/', $query['redirect_uri']);
    }

    public function test_the_refresh_token_is_kept_on_the_posting_account(): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-token', 'expires_in' => 5184000, 'refresh_token' => 'li-refresh', 'refresh_token_expires_in' => 31536000,
                'scope' => 'r_organization_social_feed,w_organization_social,rw_organization_admin']),
            'api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:555060042', 'role' => 'ADMINISTRATOR']]]),
            'api.linkedin.com/rest/organizations/555060042' => Http::response(['localizedName' => 'Socialeaz']),
            '*' => Http::response([], 404),
        ]);

        app(SocialAuthService::class)->callback('linkedin', 'code-1', 'state-1');

        $account = SocialAccount::where('platform', 'linkedin')->firstOrFail();
        $this->assertSame('li-refresh', $account->refresh_token);
        $this->assertContains('r_organization_social_feed', $account->scopes);
    }
}
