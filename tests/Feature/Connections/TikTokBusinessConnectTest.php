<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AdServices\SocialAdManagerService;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\TikTokDriver;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "TikTok for Business" from the Hub: advertiser authorization -> oauth2/access_token/ -> advertisers. */
class TikTokBusinessConnectTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'https://business-api.tiktok.com/open_api/v1.3/';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('ads.tiktok.client_id', 'tt-ads');
        Settings::set('ads.tiktok.client_secret', 'tt-ads-secret');
        Settings::set('ads.tiktok.base_url', self::API);
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    /** Hub "Connect" for the step, then TikTok's redirect back with auth_code + state. */
    private function connectAndReturn(): \Illuminate\Http\RedirectResponse
    {
        $location = app(ConnectionService::class)->connect('tiktok', TikTokDriver::BUSINESS)->headers->get('Location');
        app(SocialAdManagerService::class)->redirect('tiktok');
        request()->merge(['auth_code' => 'auth-code', 'code' => 'auth-code', 'state' => session('ad_state')]);
        $this->assertStringContainsString('ads/tiktok/redirect', $location);

        return app(SocialAdManagerService::class)->callback('tiktok');
    }

    private function fakeTikTok(array $advertisers = ['7000000000000000001', '7000000000000000002']): void
    {
        Http::fake([
            self::API . 'oauth2/access_token/' => Http::response(['code' => 0, 'message' => 'OK', 'data' => [
                'access_token' => 'adv-token', 'advertiser_ids' => $advertisers, 'scope' => [4, 5, 7],
            ]]),
            self::API . 'advertiser/info/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['list' => [
                ['advertiser_id' => '7000000000000000001', 'name' => 'Brand A', 'currency' => 'USD'],
            ]]]),
        ]);
    }

    public function test_callback_connects_every_advertiser_and_returns_to_the_hub(): void
    {
        $this->fakeTikTok();

        $response = $this->connectAndReturn();

        $this->assertSame(route('admin.connections.index'), $response->getTargetUrl());
        $this->assertSame('Connected 2 TikTok advertiser account(s).', session('success'));
        $accounts = SocialAccount::where('platform', 'tiktok')->orderBy('platform_account_id')->get();
        $this->assertSame(['Brand A', 'TikTok Advertiser 7000000000000000002'], $accounts->pluck('name')->all());
        $this->assertTrue($accounts->every(fn ($a) => $a->has_ads_permission && $a->access_token === 'adv-token'));
        $connection = SocialConnection::where('step', TikTokDriver::BUSINESS)->sole();
        $this->assertSame([['ads'], SocialConnection::ACTIVE], [$connection->capabilities, $connection->status]);
        $this->assertSame($accounts->pluck('id')->sort()->values()->all(), $connection->assets()->pluck('id')->sort()->values()->all());
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::API . 'oauth2/access_token/' && $r['app_id'] === 'tt-ads' && $r['secret'] === 'tt-ads-secret' && $r['auth_code'] === 'auth-code');
    }

    public function test_a_sandbox_or_slashless_base_url_still_connects(): void
    {
        foreach (['https://sandbox-ads.tiktok.com/open_api/v1.3/', 'https://business-api.tiktok.com/open_api/v1.3'] as $base) {
            Settings::set('ads.tiktok.base_url', $base);
            $this->fakeTikTok();

            $this->connectAndReturn();

            $this->assertSame('Connected 2 TikTok advertiser account(s).', session('success'), $base);
            Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'sandbox') || str_contains($r->url(), 'v1.3oauth2'));
        }
    }

    public function test_a_refused_exchange_returns_to_the_hub_with_tiktoks_reason(): void
    {
        Http::fake([self::API . 'oauth2/access_token/' => Http::response(['code' => 40002, 'message' => 'The app_id does not exist or the secret key provided is incorrect.'])]);

        $response = $this->connectAndReturn();

        $this->assertSame(route('admin.connections.index'), $response->getTargetUrl());
        $this->assertSame('TikTok did not issue a token: The app_id does not exist or the secret key provided is incorrect.', session('error'));
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_reconnecting_updates_instead_of_duplicating(): void
    {
        $this->fakeTikTok();
        $this->connectAndReturn();
        $this->connectAndReturn();

        $this->assertSame(2, SocialAccount::where('platform', 'tiktok')->count());
        $this->assertSame(1, SocialConnection::where('step', TikTokDriver::BUSINESS)->count());
    }
}
