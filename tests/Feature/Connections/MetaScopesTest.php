<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Services\AdServices\SocialAdManagerService;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Settings;
use Tests\TestCase;

/** The permissions each Meta dialog asks for. */
class MetaScopesTest extends TestCase
{
    use CreatesConnectionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        foreach (['posts.facebook', 'ads.facebook'] as $app) {
            Settings::set("{$app}.client_id", 'app-1');
            Settings::set("{$app}.client_secret", 'app-secret');
        }
        $this->actingAs(User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]));
    }

    private function scopes(string $location): array
    {
        $this->assertStringContainsString('facebook.com/', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return explode(',', $query['scope'] ?? '');
    }

    public function test_hub_meta_login_asks_for_every_permission_it_uses(): void
    {
        $location = app(ConnectionService::class)->connect('meta', MetaDriver::LOGIN)->headers->get('Location');

        $this->assertSame([
            'pages_show_list', 'pages_manage_posts', 'pages_read_engagement',
            'pages_manage_metadata', 'pages_read_user_content', 'pages_manage_engagement',
            'pages_messaging', 'read_insights', 'business_management',
            'instagram_basic', 'instagram_content_publish', 'instagram_manage_comments',
            'instagram_manage_insights', 'instagram_manage_messages', 'ads_management', 'ads_read',
            'pages_manage_ads',
        ], $this->scopes($location));
    }

    public function test_ads_module_facebook_connect_asks_for_pages_manage_ads(): void
    {
        $location = app(SocialAdManagerService::class)->redirect('facebook')->headers->get('Location');

        $this->assertSame(['ads_management', 'ads_read', 'pages_show_list', 'pages_read_engagement', 'pages_manage_ads', 'business_management', 'instagram_basic'], $this->scopes($location));
    }

    public function test_a_login_configuration_replaces_the_scope_list(): void
    {
        Settings::set('connections.meta.login_config_id', 'cfg-123');

        $location = app(ConnectionService::class)->connect('meta', MetaDriver::LOGIN)->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        // Its permissions live in the App Dashboard configuration - pages_manage_ads must be added there.
        $this->assertSame('cfg-123', $query['config_id']);
        $this->assertArrayNotHasKey('scope', $query);
    }
}
