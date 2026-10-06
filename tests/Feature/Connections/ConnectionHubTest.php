<?php

namespace Tests\Feature\Connections;

use App\Http\Controllers\Admin\ConnectionHubController;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** Commit 6: the Hub page, asset picker, check and disconnect. */
class ConnectionHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    private SocialConnection $connection;

    private SocialAccount $page;

    private SocialAccount $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.facebook.client_id', 'app-1');
        Settings::set('posts.facebook.client_secret', 'app-secret');

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);

        $this->connection = SocialConnection::create([
            'user_id' => $this->user->id, 'platform' => 'meta', 'step' => MetaDriver::LOGIN, 'provider_account_id' => 'fb-1',
            'access_token' => 'user-token', 'status' => SocialConnection::ACTIVE, 'expires_at' => now()->addDays(40),
            'capabilities' => ['posting', 'messaging', 'ads'],
        ]);
        $this->page = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'account_type' => 'page', 'platform_account_id' => '111', 'name' => 'Page One', 'access_token' => 'p', 'asset_token' => 'p', 'is_token_valid' => true, 'has_posting_permission' => true, 'social_connection_id' => $this->connection->id]);
        $this->ad = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'account_type' => 'ad_account', 'platform_account_id' => 'act_9', 'name' => 'Ads', 'access_token' => 'user-token', 'is_token_valid' => true, 'has_ads_permission' => true, 'social_connection_id' => $this->connection->id]);
    }

    private function controller(): ConnectionHubController
    {
        return app(ConnectionHubController::class);
    }

    public function test_hub_shows_the_meta_card_with_steps_status_and_grouped_assets(): void
    {
        $hub = $this->controller()->index()->getData()['hub'];

        $card = $hub['cards'][0];
        $this->assertSame('meta', $card['platform']);
        $this->assertTrue($card['connected']);
        $this->assertSame([MetaDriver::LOGIN, MetaDriver::WHATSAPP, MetaDriver::INSTAGRAM_LOGIN], collect($card['steps'])->pluck('key')->all());
        $this->assertTrue($card['steps'][0]['connected']);
        $this->assertStringContainsString('connections/meta/connect/meta.login', $card['steps'][0]['connect_url']);

        $conn = $card['connections'][0];
        $this->assertSame('active', $conn['status']);
        $this->assertFalse($conn['needs_attention']);
        // Page gets the page-relevant capabilities the consent allows; ad account only ads.
        $this->assertSame(['posting', 'messaging'], $conn['assets']['page'][0]['available_capabilities']);
        $this->assertSame(['posting', 'messaging'], $conn['assets']['page'][0]['enabled_capabilities']);
        $this->assertSame(['ads'], $conn['assets']['ad_account'][0]['available_capabilities']);
        $this->assertSame(1, $hub['summary']['connected']);
    }

    public function test_page_renders(): void
    {
        view()->share('errors', new ViewErrorBag); // normally shared by middleware
        $html = $this->controller()->index()->render();

        $this->assertStringContainsString('<connection-hub', $html);
    }

    public function test_asset_picker_saves_enabled_capabilities(): void
    {
        $response = $this->controller()->updateAsset(new Request(['enabled_capabilities' => ['messaging']]), $this->page);

        $this->assertSame(['messaging'], $this->page->fresh()->enabled_capabilities);
        $this->assertSame(['messaging'], $response->getData(true)['connection']['assets']['page'][0]['enabled_capabilities']);

        // Turning an account off entirely.
        $this->controller()->updateAsset(new Request(['enabled_capabilities' => []]), $this->ad);
        $this->assertSame([], $this->ad->fresh()->enabled_capabilities);
    }

    public function test_asset_picker_rejects_unknown_capabilities(): void
    {
        $this->expectException(ValidationException::class);
        $this->controller()->updateAsset(new Request(['enabled_capabilities' => ['delete_everything']]), $this->page);
    }

    public function test_other_users_assets_and_connections_are_404(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($other);

        try {
            $this->controller()->updateAsset(new Request(['enabled_capabilities' => []]), $this->page);
            $this->fail('Expected 404');
        } catch (NotFoundHttpException) {
        }

        $this->expectException(NotFoundHttpException::class);
        $this->controller()->check($this->connection);
    }

    public function test_check_now_validates_with_meta(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Session expired', 'code' => 190, 'error_subcode' => 463]], 400)]);

        $data = $this->controller()->check($this->connection)->getData(true)['connection'];

        $this->assertSame('needs_reauth', $data['status']);
        $this->assertTrue($data['needs_attention']);
        $this->assertSame('Session expired', $data['last_error']);
    }

    public function test_disconnect_returns_the_updated_card(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $card = $this->controller()->disconnect($this->connection)->getData(true)['card'];

        $this->assertFalse($card['connected']);
        $this->assertSame('revoked', $card['connections'][0]['status']);
        $this->assertFalse($this->page->fresh()->is_token_valid);
    }
}
