<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\LinkedinAdService;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** LinkedIn connects keep only active Company Page ad accounts the member manages. */
class LinkedInAdAccountsTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        foreach (['posts.linkedin', 'ads.linkedin'] as $app) {
            Settings::set("{$app}.client_id", "{$app}-id");
            Settings::set("{$app}.client_secret", "{$app}-secret");
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);

        $acct = fn (array $a) => Http::response($a + ['currency' => 'SAR']);
        $user = fn (string $id, string $role) => ['account' => 'urn:li:sponsoredAccount:' . $id, 'role' => $role];
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li', 'expires_in' => 5184000]),
            'api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => []]),
            'api.linkedin.com/rest/adAccountUsers*' => Http::response(['elements' => [
                $user('1', 'ACCOUNT_MANAGER'), $user('2', 'ACCOUNT_BILLING_ADMIN'), $user('3', 'CAMPAIGN_MANAGER'),
                $user('4', 'VIEWER'), $user('5', 'ACCOUNT_MANAGER'),
            ]]),
            'api.linkedin.com/rest/adAccounts/1' => $acct(['name' => 'Socialeaz Ads', 'status' => 'ACTIVE', 'reference' => 'urn:li:organization:555']),
            'api.linkedin.com/rest/adAccounts/2' => $acct(['name' => 'Zaheer Ahmad', 'status' => 'ACTIVE', 'reference' => 'urn:li:person:abc']),
            'api.linkedin.com/rest/adAccounts/3' => $acct(['name' => 'New', 'status' => 'DRAFT', 'reference' => 'urn:li:organization:555']),
            'api.linkedin.com/rest/adAccounts/4' => $acct(['name' => 'Partner', 'status' => 'ACTIVE', 'reference' => 'urn:li:organization:777']),
            'api.linkedin.com/rest/adAccounts/5' => $acct(['name' => 'Sandbox', 'status' => 'ACTIVE', 'test' => true, 'reference' => 'urn:li:organization:555']),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_the_posting_sign_in_never_saves_ad_accounts(): void
    {
        // Content posting (posts.linkedin, Community Management API) and Ads
        // (ads.linkedin, Advertising API) are separate LinkedIn apps: the
        // posting token isn't valid for ad accounts, so they aren't read.
        session(['social_oauth_state_linkedin' => 's']);
        app(SocialAuthService::class)->callback('linkedin', 'code', 's');

        $this->assertSame(0, SocialAccount::where('account_type', 'ad_account')->count());
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'adAccountUsers') || str_contains($r->url(), 'adAccounts/'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth/v2/accessToken') && $r['client_id'] === 'posts.linkedin-id');
    }

    public function test_posting_and_ads_consents_use_their_own_app_and_scopes(): void
    {
        $query = function ($response) {
            parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $q);

            return [$q['client_id'], explode(' ', $q['scope'])];
        };

        [$postingApp, $posting] = $query(app(SocialAuthService::class)->redirect('linkedin'));
        [$adsApp, $ads] = $query(app(\App\Services\AdServices\LinkedinAdService::class)->redirect('linkedin', 'state'));

        $this->assertSame(['posts.linkedin-id', 'ads.linkedin-id'], [$postingApp, $adsApp]);
        $this->assertSame([], array_intersect($posting, ['r_ads', 'rw_ads', 'r_ads_reporting']));
        $this->assertContains('r_organization_social_feed', $posting);
        $this->assertSame(['r_ads', 'rw_ads', 'r_ads_reporting', 'w_organization_social', 'r_organization_social'], $ads);
        $this->assertSame([], array_intersect($ads, ['r_organization_social_feed', 'w_organization_social_feed']));
    }

    public function test_single_app_sign_in_keeps_only_managed_company_page_accounts(): void
    {
        // One LinkedIn app with both products (flag linkedin.single_app): ads come along.
        Settings::set('connections.flags.linkedin.single_app', '1');
        $old = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'linkedin', 'platform_account_id' => '2', 'name' => 'Zaheer Ahmad', 'account_type' => 'ad_account', 'access_token' => 't', 'has_ads_permission' => true]);

        session(['social_oauth_state_linkedin' => 's']);
        app(SocialAuthService::class)->callback('linkedin', 'code', 's');

        $kept = SocialAccount::where('platform', 'linkedin')->where('has_ads_permission', true)->get();
        $this->assertSame(['1'], $kept->pluck('platform_account_id')->all());
        $this->assertSame('555', $kept->first()->metadata['profile_id']);
        $this->assertFalse($old->fresh()->has_ads_permission); // personal: retired
        $this->assertStringContainsString('Skipped 1 personal ad account, 1 draft or cancelled account, 1 account where you can only view and 1 test account', session('success'));
    }

    public function test_ads_module_sign_in_applies_the_same_rule(): void
    {
        request()->merge(['code' => 'c']);

        app(LinkedinAdService::class)->callback('linkedin');

        $this->assertSame(['1'], SocialAccount::where('platform', 'linkedin')->pluck('platform_account_id')->all());
        $this->assertStringContainsString('Connected 1 LinkedIn Ad Account(s). Skipped 1 personal ad account', session('success'));
    }
}
