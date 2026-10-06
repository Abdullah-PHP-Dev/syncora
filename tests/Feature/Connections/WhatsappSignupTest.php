<?php

namespace Tests\Feature\Connections;

use App\Http\Controllers\Admin\PostAccountController;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\MetaDriver;
use App\Services\Connections\HubPresenter;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 8c: WhatsApp Embedded Signup on the one Meta app, inside the Hub. */
class WhatsappSignupTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.facebook.client_id', 'meta-app');
        Settings::set('posts.facebook.client_secret', 'meta-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_signup_settings_use_the_one_meta_app(): void
    {
        $this->assertNull(MetaDriver::whatsappSignup());

        Settings::set('messaging.meta.whatsapp_config_id', 'legacy-cfg');
        $this->assertSame('legacy-cfg', MetaDriver::whatsappSignup()['config_id']);

        Settings::set('connections.meta.whatsapp_config_id', 'wa-cfg');
        Settings::set('messaging.meta.app_id', 'old-messaging-app');
        $signup = MetaDriver::whatsappSignup();
        $this->assertSame('wa-cfg', $signup['config_id']);
        $this->assertSame('meta-app', $signup['app_id']);
        $this->assertStringContainsString('post-accounts/whatsapp/embedded', $signup['store_url']);
    }

    public function test_hub_card_offers_in_page_signup_once_configured(): void
    {
        Settings::set('connections.meta.whatsapp_config_id', 'wa-cfg');

        $card = app(HubPresenter::class)->card($this->user->id, 'meta');

        $this->assertSame('wa-cfg', $card['whatsapp_signup']['config_id']);
        $this->assertTrue(collect($card['steps'])->firstWhere('key', MetaDriver::WHATSAPP)['available']);
    }

    public function test_code_is_exchanged_with_the_one_meta_app_and_recorded(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'wa-token']),
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [['permission' => 'whatsapp_business_messaging', 'status' => 'granted']]]),
            'graph.facebook.com/*/phone-1*' => Http::response(['verified_name' => 'Shop', 'display_phone_number' => '+966 5']),
            '*' => Http::response([], 404),
        ]);

        $response = app()->call([app(PostAccountController::class), 'storeWhatsappEmbedded'], [
            'request' => new Request(['code' => 'c', 'phone_number_id' => 'phone-1', 'waba_id' => 'waba-1']),
        ]);

        $this->assertTrue($response->getData(true)['success']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth/access_token') && str_contains($r->url(), 'client_id=meta-app'));
        $connection = SocialConnection::where('step', MetaDriver::WHATSAPP)->sole();
        $this->assertSame('phone-1', $connection->provider_account_id);
        $this->assertSame(['posting', 'messaging'], $connection->capabilities); // whatsapp_business_messaging
        $this->assertSame($connection->id, SocialAccount::where('platform', 'whatsapp')->sole()->social_connection_id);
    }

    public function test_whatsapp_connect_route_lands_on_the_hub(): void
    {
        Settings::set('connections.meta.whatsapp_config_id', 'wa-cfg');

        $location = app(ConnectionService::class)->connect('meta', MetaDriver::WHATSAPP)->headers->get('Location');

        $this->assertStringEndsWith('/connections#meta', $location);
    }
}
