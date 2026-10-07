<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\XDriver;
use App\Services\Connections\HubPresenter;
use App\Support\Connections\HubLink;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Commit 10: X in the Hub - card, asset groups, backfill with secret move. */
class XHubTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.x.client_id', 'x-client');
        Settings::set('ads.x.client_id', 'ads-consumer');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function account(array $attributes): SocialAccount
    {
        return SocialAccount::create(array_merge(['user_id' => $this->user->id, 'platform' => 'x', 'name' => 'A', 'is_token_valid' => true], $attributes));
    }

    public function test_backfill_links_x_accounts_and_moves_the_ads_secret(): void
    {
        $me = $this->account(['platform_account_id' => 'u1', 'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->subHour(), 'has_posting_permission' => true, 'has_messaging_permission' => true, 'scopes' => ['tweet.write', 'dm.write']]);
        $ads1 = $this->account(['platform_account_id' => 'ads1', 'access_token' => 'oauth1-token', 'has_ads_permission' => true, 'metadata' => ['legacy_token_secret' => 'plain', 'x_user_id' => 'u1', 'timezone' => 'UTC']]);
        $ads2 = $this->account(['platform_account_id' => 'ads2', 'access_token' => 'oauth1-token', 'has_ads_permission' => true, 'metadata' => ['legacy_token_secret' => 'plain', 'x_user_id' => 'u1']]);

        $this->artisan('connections:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('plain', $ads1->fresh()->metadata['legacy_token_secret']); // dry run keeps it

        $this->artisan('connections:backfill')->assertSuccessful();

        $oauth2 = SocialConnection::where('step', XDriver::OAUTH2)->sole();
        $this->assertSame('u1', $oauth2->provider_account_id);
        $this->assertSame(SocialConnection::ACTIVE, $oauth2->status);
        $this->assertSame(['posting', 'messaging'], $oauth2->capabilities);
        $this->assertSame($oauth2->id, $me->fresh()->social_connection_id);

        $ads = SocialConnection::where('step', XDriver::ADS)->sole();
        $this->assertSame('plain', $ads->token_secret);
        $this->assertNotSame('plain', DB::table('social_connections')->where('id', $ads->id)->value('token_secret')); // encrypted at rest
        $this->assertSame(['ads'], $ads->capabilities);
        foreach ([$ads1, $ads2] as $row) {
            $this->assertSame($ads->id, $row->fresh()->social_connection_id);
            $this->assertArrayNotHasKey('legacy_token_secret', $row->fresh()->metadata);
        }
        $this->assertSame('UTC', $ads1->fresh()->metadata['timezone']); // other metadata kept
    }

    public function test_x_card_groups_accounts_and_x_left_upcoming(): void
    {
        $oauth2 = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::OAUTH2, 'provider_account_id' => 'u1', 'access_token' => 'a', 'refresh_token' => 'r', 'status' => 'active', 'capabilities' => ['posting', 'messaging']]);
        $this->account(['platform_account_id' => 'u1', 'has_posting_permission' => true, 'has_messaging_permission' => true, 'social_connection_id' => $oauth2->id]);
        $ads = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'x', 'step' => XDriver::ADS, 'provider_account_id' => 'u1', 'access_token' => 't', 'token_secret' => 's', 'status' => 'active', 'capabilities' => ['ads']]);
        $this->account(['platform_account_id' => 'ads1', 'has_ads_permission' => true, 'social_connection_id' => $ads->id]);

        $hub = app(HubPresenter::class)->forUser($this->user->id);
        $card = collect($hub['cards'])->firstWhere('platform', 'x');
        $connections = collect($card['connections'])->keyBy('step');

        $this->assertSame(['posting', 'messaging'], $connections[XDriver::OAUTH2]['assets']['x_account'][0]['available_capabilities']);
        $this->assertSame(['ads'], $connections[XDriver::ADS]['assets']['x_ads'][0]['available_capabilities']);
        $this->assertSame('X Ads', $connections[XDriver::ADS]['step_label']);
        $this->assertNotContains('x', collect($hub['upcoming'])->pluck('key'));
        $this->assertStringEndsWith('/connections#x', HubLink::for('x'));
    }
}
