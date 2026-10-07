<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\PinterestDriver;
use App\Services\Connections\Drivers\SnapchatDriver;
use App\Services\Connections\Drivers\ThreadsDriver;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 11d: Snapchat, Threads and Pinterest in the Hub. */
class RemainingPlatformsHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        foreach (['ads.snapchat', 'posts.threads', 'posts.pinterest'] as $app) {
            Settings::set("{$app}.client_id", "{$app}-id");
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connection(string $platform, string $step, array $asset = []): SocialConnection
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => $platform, 'step' => $step, 'provider_account_id' => uniqid(), 'status' => 'active']);
        SocialAccount::create(array_merge(['user_id' => $this->user->id, 'platform' => $platform, 'platform_account_id' => uniqid(), 'name' => 'A', 'access_token' => 'tok', 'expires_at' => now()->addHour(), 'social_connection_id' => $connection->id], $asset));

        return $connection;
    }

    public function test_connect_targets(): void
    {
        $service = app(ConnectionService::class);

        $this->assertStringContainsString('ads/snapchat/redirect', $service->connect('snapchat', SnapchatDriver::MARKETING)->headers->get('Location'));
        $this->assertStringContainsString('post-accounts/threads/redirect', $service->connect('threads', ThreadsDriver::LOGIN)->headers->get('Location'));
        $this->assertStringContainsString('post-accounts/pinterest/redirect', $service->connect('pinterest', PinterestDriver::LOGIN)->headers->get('Location'));
        $this->assertSame('hub', session('social_oauth_return_to'));
    }

    public function test_probes_map_token_errors_to_reconnect(): void
    {
        Http::fake([
            'adsapi.snapchat.com/*' => Http::response(['request_status' => 'ERROR'], 401),
            'graph.threads.net/*' => Http::response(['error' => ['code' => 190, 'message' => 'Session has expired']], 400),
            'api.pinterest.com/*' => Http::response(['code' => 2, 'message' => 'Authentication failed.'], 401),
        ]);

        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(SnapchatDriver::class)->validate($this->connection('snapchat', SnapchatDriver::MARKETING, ['refresh_token' => 'r']))->status);
        $threads = app(ThreadsDriver::class)->validate($this->connection('threads', ThreadsDriver::LOGIN));
        $this->assertSame(SocialConnection::NEEDS_REAUTH, $threads->status);
        $this->assertSame('Session has expired', $threads->last_error);
        $this->assertSame(SocialConnection::NEEDS_REAUTH, app(PinterestDriver::class)->validate($this->connection('pinterest', PinterestDriver::LOGIN, ['refresh_token' => 'r']))->status);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.pinterest.com/v5/user_account') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_threads_status_follows_its_long_lived_token(): void
    {
        Http::fake();

        // No refresh token and expired: needs a new sign-in, no provider call.
        $result = app(ThreadsDriver::class)->validate($this->connection('threads', ThreadsDriver::LOGIN, ['expires_at' => now()->subDay()]));

        $this->assertSame(SocialConnection::NEEDS_REAUTH, $result->status);
        Http::assertNothingSent();
    }

    public function test_backfill_links_each_platform(): void
    {
        $snap = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'snapchat', 'platform_account_id' => 's1', 'name' => 'Snap', 'access_token' => 't', 'refresh_token' => 'r', 'has_ads_permission' => true]);
        $threads = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'threads', 'platform_account_id' => 'th1', 'name' => 'Th', 'access_token' => 't', 'expires_at' => now()->addDays(30), 'has_posting_permission' => true]);
        $pin = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'pinterest', 'platform_account_id' => 'p1', 'name' => 'Pin', 'access_token' => 't', 'refresh_token' => 'r', 'has_posting_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame([SnapchatDriver::MARKETING, ['ads']], [$snap->fresh()->connection->step, $snap->fresh()->connection->capabilities]);
        $this->assertSame(['th1', 'posts.threads'], [$threads->fresh()->connection->provider_account_id, $threads->fresh()->connection->provider_app]);
        $this->assertSame([PinterestDriver::LOGIN, ['posting']], [$pin->fresh()->connection->step, $pin->fresh()->connection->capabilities]);
    }
}
