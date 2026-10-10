<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Meta connects keep only active Business ad accounts, stored by bare id. */
class MetaAdAccountsTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        Settings::set('posts.facebook.client_id', 'app-1');
        Settings::set('posts.facebook.client_secret', 'app-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connect(array $adAccounts): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token', 'expires_in' => 5183944]),
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [['permission' => 'ads_read', 'status' => 'granted']]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []]),
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => $adAccounts]),
            'graph.facebook.com/*/me?*' => Http::response(['id' => 'fb-user-77']),
            '*' => Http::response([], 404),
        ]);
        session(['social_oauth_state_facebook' => 's']);
        app(SocialAuthService::class)->callback('facebook', 'code', 's');
    }

    private function adAccount(string $id, array $extra = []): array
    {
        return array_merge(['id' => 'act_' . $id, 'account_id' => $id, 'name' => 'Ads ' . $id, 'account_status' => 1, 'disable_reason' => 0, 'business' => ['id' => 'biz']], $extra);
    }

    public function test_only_active_business_ad_accounts_are_kept(): void
    {
        $this->connect([
            $this->adAccount('1'),
            $this->adAccount('2', ['business' => null, 'name' => 'Zaheer Ahmad']),  // personal
            $this->adAccount('3', ['disable_reason' => 3]),                        // disabled by Meta
            $this->adAccount('4', ['account_status' => 101]),                      // closed
        ]);

        $this->assertSame(['1'], SocialAccount::where('account_type', 'ad_account')->pluck('platform_account_id')->all());
        $this->assertStringContainsString('Skipped 1 personal ad account, 1 disabled one and 1 inactive one', session('success'));
    }

    public function test_reconnect_retires_accounts_that_are_no_longer_eligible_and_fixes_act_ids(): void
    {
        $connection = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'meta', 'step' => 'meta.login', 'status' => 'active']);
        $old = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'act_1', 'name' => 'Old', 'account_type' => 'ad_account', 'access_token' => 't', 'has_ads_permission' => true, 'social_connection_id' => $connection->id]);
        $personal = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'act_2', 'name' => 'Zaheer Ahmad', 'account_type' => 'ad_account', 'access_token' => 't', 'has_ads_permission' => true, 'social_connection_id' => $connection->id]);

        $this->connect([$this->adAccount('1'), $this->adAccount('2', ['business' => null])]);

        // Same row, now with the bare id the Ads API expects.
        $this->assertSame('1', $old->fresh()->platform_account_id);
        $this->assertTrue($old->fresh()->has_ads_permission);
        $this->assertSame(1, SocialAccount::where('account_type', 'ad_account')->where('platform_account_id', 'like', '%1')->count());

        // Kept for campaign history, but no longer offered to Ads or the Hub.
        $personal->refresh();
        $this->assertFalse($personal->has_ads_permission);
        $this->assertNull($personal->social_connection_id);
    }

    public function test_migration_renames_act_ids_and_retires_duplicates(): void
    {
        $renamed = DB::table('social_accounts')->insertGetId(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'act_5', 'name' => 'A', 'has_ads_permission' => true]);
        $bare = DB::table('social_accounts')->insertGetId(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => '6', 'name' => 'B', 'has_ads_permission' => true]);
        $dupe = DB::table('social_accounts')->insertGetId(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'act_6', 'name' => 'B old', 'has_ads_permission' => true]);

        (require database_path('migrations/2026_10_10_120000_normalize_meta_ad_account_ids.php'))->up();

        $this->assertSame('5', DB::table('social_accounts')->where('id', $renamed)->value('platform_account_id'));
        $this->assertSame('6', DB::table('social_accounts')->where('id', $bare)->value('platform_account_id'));
        $this->assertSame([ 'act_6', 0 ], [DB::table('social_accounts')->where('id', $dupe)->value('platform_account_id'), (int) DB::table('social_accounts')->where('id', $dupe)->value('has_ads_permission')]);
    }
}
