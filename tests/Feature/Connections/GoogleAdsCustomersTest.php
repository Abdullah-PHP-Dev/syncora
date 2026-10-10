<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\GoogleAdService;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Google connects keep only enabled client Google Ads accounts. */
class GoogleAdsCustomersTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        foreach (['posts.google', 'ads.google'] as $app) {
            Settings::set("{$app}.client_id", "{$app}-id");
            Settings::set("{$app}.client_secret", "{$app}-secret");
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);

        $customer = fn (array $c) => Http::response(['results' => [['customer' => $c]]]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g', 'refresh_token' => 'r', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/adwords']),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []]),
            '*customers:listAccessibleCustomers' => Http::response(['resourceNames' => ['customers/1', 'customers/2', 'customers/3', 'customers/4', 'customers/5']]),
            '*customers/1/googleAds:search' => $customer(['id' => '1', 'descriptiveName' => 'Socialeaz Ads', 'currencyCode' => 'SAR', 'status' => 'ENABLED']),
            '*customers/2/googleAds:search' => $customer(['id' => '2', 'descriptiveName' => 'Agency MCC', 'manager' => true, 'status' => 'ENABLED']),
            '*customers/3/googleAds:search' => $customer(['id' => '3', 'descriptiveName' => 'Old', 'status' => 'CANCELED']),
            '*customers/4/googleAds:search' => $customer(['id' => '4', 'descriptiveName' => 'Sandbox', 'testAccount' => true, 'status' => 'ENABLED']),
            '*customers/5/googleAds:search' => Http::response(['error' => ['message' => 'The customer account can\'t be accessed because it is not yet enabled or has been deactivated.']], 403),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_connections_sign_in_keeps_only_enabled_client_accounts(): void
    {
        $old = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'google', 'platform_account_id' => '3', 'name' => 'Old', 'account_type' => 'ad_account', 'access_token' => 't', 'has_ads_permission' => true]);

        session(['social_oauth_state_google' => 's']);
        app(SocialAuthService::class)->callback('google', 'code', 's');

        $kept = SocialAccount::where('platform', 'google')->where('has_ads_permission', true)->get();
        $this->assertSame(['1'], $kept->pluck('platform_account_id')->all());
        $this->assertNull($kept->first()->avatar_url); // no personal profile photo
        $this->assertFalse($old->fresh()->has_ads_permission); // cancelled since the last connect
        $this->assertSame(2, SocialAccount::where('platform', 'google')->count()); // kept + retired; nothing saved for 2, 4, 5
        $this->assertStringContainsString('Skipped 1 manager (MCC) account, 1 cancelled or suspended account, 1 test account and 1 account Google would not share details for', session('success'));
    }

    public function test_ads_module_sign_in_applies_the_same_rule(): void
    {
        request()->merge(['code' => 'c']);

        $response = app(GoogleAdService::class)->callback('google');

        $this->assertSame(['1'], SocialAccount::where('platform', 'google')->pluck('platform_account_id')->all());
        $this->assertNull(SocialAccount::where('platform', 'google')->value('avatar_url'));
        $this->assertStringContainsString('Connected 1 Google Ads account(s). Skipped 1 manager (MCC) account', session('success'));
        $this->assertTrue($response->isRedirect());
    }
}
