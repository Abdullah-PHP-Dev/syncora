<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Services\Connections\HubPresenter;
use App\Support\Settings;
use Tests\TestCase;

/** Commit 9c: Google in the Hub - card, asset groups, upgrade, backfill. */
class GoogleHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.google.client_id', 'posts-client');
        Settings::set('posts.facebook.client_id', 'meta-app');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function account(array $attributes): SocialAccount
    {
        return SocialAccount::create(array_merge(['user_id' => $this->user->id, 'platform_account_id' => uniqid(), 'name' => 'A', 'is_token_valid' => true], $attributes));
    }

    public function test_backfill_groups_google_accounts_by_consent(): void
    {
        $channel = $this->account(['platform' => 'youtube', 'access_token' => 'a1', 'refresh_token' => 'shared', 'expires_at' => now()->subHour(), 'has_posting_permission' => true]);
        $sameConsentAds = $this->account(['platform' => 'google', 'access_token' => 'a1', 'refresh_token' => 'shared', 'has_ads_permission' => true]);
        $legacyAds = $this->account(['platform' => 'google', 'access_token' => 'a2', 'refresh_token' => 'ads-only', 'has_ads_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $oauth = SocialConnection::where('step', GoogleDriver::OAUTH)->sole();
        $this->assertSame('posts.google', $oauth->provider_app);
        $this->assertSame('shared', $oauth->refresh_token);
        $this->assertSame(SocialConnection::ACTIVE, $oauth->status); // refreshable: expired access token is fine
        $this->assertSame(['posting', 'ads'], $oauth->capabilities);
        $this->assertSame($oauth->id, $channel->fresh()->social_connection_id);
        $this->assertSame($oauth->id, $sameConsentAds->fresh()->social_connection_id);

        $legacy = SocialConnection::where('step', GoogleDriver::ADS_LEGACY)->sole();
        $this->assertSame('ads.google', $legacy->provider_app);
        $this->assertSame('ads-only', $legacy->refresh_token);
        $this->assertSame($legacy->id, $legacyAds->fresh()->social_connection_id);
    }

    public function test_hub_shows_a_google_card_with_its_assets_and_upgrade(): void
    {
        $legacy = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::ADS_LEGACY, 'provider_app' => 'ads.google', 'refresh_token' => 'r', 'access_token' => 'a', 'status' => 'active', 'capabilities' => ['ads']]);
        $this->account(['platform' => 'google', 'has_ads_permission' => true, 'social_connection_id' => $legacy->id]);
        $oauth = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'google', 'step' => GoogleDriver::OAUTH, 'provider_app' => 'posts.google', 'refresh_token' => 'r2', 'access_token' => 'a2', 'status' => 'active', 'capabilities' => ['posting']]);
        $this->account(['platform' => 'youtube', 'has_posting_permission' => true, 'social_connection_id' => $oauth->id]);

        $card = collect(app(HubPresenter::class)->forUser($this->user->id)['cards'])->firstWhere('platform', 'google');

        $this->assertSame('YouTube · Google Ads · Analytics', $card['presentation']['subtitle']);
        $this->assertSame(['posting', 'ads', 'insights'], $card['presentation']['benefits']);
        $connections = collect($card['connections'])->keyBy('step');

        $old = $connections[GoogleDriver::ADS_LEGACY];
        $this->assertSame('Google Ads (earlier connection)', $old['step_label']);
        $this->assertNotNull($old['upgrade']);
        $this->assertStringContainsString('connections/google/connect/google.oauth', $old['upgrade']['url']);
        $this->assertFalse($old['upgradable']);
        $this->assertSame(['ads'], $old['assets']['google_ads'][0]['available_capabilities']);

        $new = $connections[GoogleDriver::OAUTH];
        $this->assertNull($new['upgrade']);
        $this->assertTrue($new['upgradable']);
        $this->assertSame(['posting'], $new['assets']['youtube'][0]['available_capabilities']);
    }

    public function test_google_left_the_upcoming_list(): void
    {
        $upcoming = collect(app(HubPresenter::class)->forUser($this->user->id)['upcoming'])->pluck('key');

        $this->assertNotContains('google', $upcoming);
        $this->assertContains('linkedin', $upcoming);
    }
}
