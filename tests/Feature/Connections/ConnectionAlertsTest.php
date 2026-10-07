<?php

namespace Tests\Feature\Connections;

use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\ConnectionService;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\Connections\Drivers\LinkedInDriver;
use App\Services\Connections\Drivers\TikTokDriver;
use App\Services\Connections\Drivers\XDriver;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Commit 12a: ensure() across a platform's steps, and the inline module prompts. */
class ConnectionAlertsTest extends TestCase
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

    private function connection(string $platform, string $step, array $capabilities, string $status = 'active', ?string $error = null): SocialConnection
    {
        return SocialConnection::create(['user_id' => $this->user->id, 'platform' => $platform, 'step' => $step, 'provider_account_id' => uniqid(), 'status' => $status, 'capabilities' => $capabilities, 'last_error' => $error]);
    }

    private function ensure(string $platform, string $capability): array
    {
        return app(ConnectionService::class)->ensure($this->user->id, $platform, $capability);
    }

    public function test_links_point_at_the_step_that_grants_the_capability(): void
    {
        $this->assertStringEndsWith('/connections/x/connect/' . XDriver::ADS, $this->ensure('x', 'ads')['url']);
        $this->assertStringEndsWith('/connections/linkedin/connect/' . LinkedInDriver::PAGES, $this->ensure('linkedin', 'posting')['url']);
        $this->assertStringEndsWith('/connections/tiktok/connect/' . TikTokDriver::BUSINESS, $this->ensure('tiktok', 'ads')['url']);
        $this->assertStringEndsWith('/connections/google/connect/' . GoogleDriver::OAUTH, $this->ensure('google', 'ads')['url']);
    }

    public function test_any_step_of_the_platform_can_satisfy_a_capability(): void
    {
        // The legacy Google Ads consent still counts for ads.
        $this->connection('google', GoogleDriver::ADS_LEGACY, ['ads']);
        $this->assertTrue($this->ensure('google', 'ads')['ok']);

        // Pages connected, ads never requested -> upgrade, not "not connected".
        $this->connection('linkedin', LinkedInDriver::PAGES, ['posting']);
        $this->assertSame('upgrade', $this->ensure('linkedin', 'ads')['reason']);

        // The ads consent broke while posting still works -> reconnect for ads only.
        $this->connection('tiktok', TikTokDriver::LOGIN_KIT, ['posting']);
        $this->connection('tiktok', TikTokDriver::BUSINESS, ['ads'], SocialConnection::NEEDS_REAUTH);
        $this->assertTrue($this->ensure('tiktok', 'posting')['ok']);
        $this->assertSame('reconnect', $this->ensure('tiktok', 'ads')['reason']);
    }

    public function test_a_user_disconnect_reads_as_not_connected(): void
    {
        $this->connection('x', XDriver::OAUTH2, ['posting'], SocialConnection::REVOKED, SocialConnection::DISCONNECTED_BY_USER);

        $this->assertSame('not_connected', $this->ensure('x', 'posting')['reason']);
    }

    public function test_component_prompts_only_for_platforms_in_use(): void
    {
        $this->connection('linkedin', LinkedInDriver::PAGES, ['posting'], SocialConnection::EXPIRED);
        $this->connection('tiktok', TikTokDriver::LOGIN_KIT, ['posting']);

        $html = Blade::render('<x-connection-alerts capability="posting" />');

        $this->assertStringContainsString('LinkedIn needs you to reconnect', $html);
        $this->assertStringContainsString('connect/' . LinkedInDriver::PAGES, $html);
        $this->assertStringNotContainsString('TikTok', $html);   // healthy
        $this->assertStringNotContainsString('Pinterest', $html); // never connected
    }

    public function test_component_single_platform_form(): void
    {
        // Module key instagram maps to the Meta card.
        $this->assertStringContainsString('Connect Meta', Blade::render('<x-connection-alerts capability="ads" platform="instagram" show-not-connected />'));
        $this->assertSame('', trim(Blade::render('<x-connection-alerts capability="ads" platform="instagram" />')));

        $this->connection('linkedin', LinkedInDriver::PAGES, ['posting']);
        $html = Blade::render('<x-connection-alerts capability="ads" platform="linkedin" />');
        $this->assertStringContainsString('Upgrade access', $html);
        $this->assertStringContainsString('connect/' . LinkedInDriver::ADS, $html);
    }

    public function test_renders_nothing_when_all_is_well(): void
    {
        $this->assertSame('', trim(Blade::render('<x-connection-alerts capability="ads" />')));
    }
}
