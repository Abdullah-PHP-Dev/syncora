<?php

namespace Tests\Feature\Connections;

use App\Http\Controllers\Admin\ConnectionHubController;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\AdServices\SocialAdManagerService;
use App\Services\Connections\Drivers\OpenAiDriver;
use App\Services\Connections\HubPresenter;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** ChatGPT Ads: connect with an OpenAI Ads API key on the Hub. */
class OpenAiConnectTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function submitKey(string $key)
    {
        return app(ConnectionHubController::class)->connectOpenAi(new Request(['api_key' => $key]));
    }

    public function test_a_valid_key_connects_its_ad_account(): void
    {
        Http::fake(['api.ads.openai.com/v1/ad_account' => Http::response(['id' => 'adacct_123', 'name' => 'Acme Ads', 'currency' => 'USD'])]);

        $response = $this->submitKey('sk-ads-' . str_repeat('a', 30));

        $this->assertStringEndsWith('/connections#openai', $response->getTargetUrl());
        $this->assertSame('Connected ChatGPT Ads account "Acme Ads".', session('success'));
        $account = SocialAccount::where('platform', 'openai')->sole();
        $this->assertSame(['adacct_123', 'ad_account', true], [$account->platform_account_id, $account->account_type, $account->has_ads_permission]);
        $this->assertSame('sk-ads-' . str_repeat('a', 30), $account->access_token);
        $this->assertNotSame($account->access_token, \DB::table('social_accounts')->where('id', $account->id)->value('access_token')); // stored encrypted
        $connection = SocialConnection::where('platform', 'openai')->sole();
        $this->assertSame([OpenAiDriver::API_KEY, ['ads'], $connection->id], [$connection->step, $connection->capabilities, $account->social_connection_id]);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer sk-ads-' . str_repeat('a', 30)));
    }

    public function test_a_rejected_key_saves_nothing(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $this->submitKey('sk-ads-' . str_repeat('b', 30));

        $this->assertStringContainsString('OpenAI did not accept this API key', session('error'));
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_hourly_check_marks_a_revoked_key_for_reconnect(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'openai', 'step' => OpenAiDriver::API_KEY, 'status' => 'active']);
        SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'openai', 'platform_account_id' => 'adacct_1', 'name' => 'A', 'access_token' => 'sk-revoked', 'has_ads_permission' => true, 'social_connection_id' => $connection->id]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(OpenAiDriver::class)->validate($connection)->status);
    }

    public function test_hub_card_and_ads_connect_link(): void
    {
        $card = app(HubPresenter::class)->card($this->user->id, 'openai');
        $this->assertSame(['ChatGPT Ads', 'api_key'], [$card['label'], $card['steps'][0]['input']]);

        $this->assertStringEndsWith('/connections#openai', app(SocialAdManagerService::class)->redirect('openai')->getTargetUrl());
    }
}
