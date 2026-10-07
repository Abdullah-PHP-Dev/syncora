<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Commit 4: social_connections + connections:backfill (Meta). */
class BackfillConnectionsTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
    }

    private function account(array $attributes): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => $this->user->id,
            'platform_account_id' => uniqid(),
            'name' => 'Acct',
            'is_token_valid' => true,
        ], $attributes));
    }

    private function metaFixture(): array
    {
        $page = $this->account(['platform' => 'facebook', 'account_type' => 'page', 'token_type' => 'page', 'access_token' => 'page-token', 'asset_token' => 'page-token', 'user_token' => 'old-user-token', 'expires_at' => now()->addDays(30), 'has_posting_permission' => true, 'has_messaging_permission' => true]);
        $ig = $this->account(['platform' => 'instagram', 'account_type' => 'business_account', 'token_type' => 'page', 'access_token' => 'page-token', 'expires_at' => now()->addDays(30), 'has_posting_permission' => true]);
        $ad = $this->account(['platform' => 'facebook', 'account_type' => 'ad_account', 'access_token' => 'new-user-token', 'expires_at' => now()->addDays(50), 'has_ads_permission' => true]);
        // Newest row wins the user-token pick.
        DB::table('social_accounts')->where('id', $page->id)->update(['updated_at' => now()->subDay()]);

        return [$page, $ig, $ad];
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->metaFixture();

        $this->artisan('connections:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame(0, SocialConnection::count());
        $this->assertSame(0, SocialAccount::whereNotNull('social_connection_id')->count());
    }

    public function test_meta_rows_become_one_login_connection_with_newest_user_token(): void
    {
        [$page, $ig, $ad] = $this->metaFixture();

        $this->artisan('connections:backfill')->assertSuccessful();

        $connection = SocialConnection::sole();
        $this->assertSame('meta', $connection->platform);
        $this->assertSame('meta.login', $connection->step);
        $this->assertSame('posts.facebook', $connection->provider_app);
        $this->assertSame('new-user-token', $connection->access_token);
        $this->assertSame(SocialConnection::ACTIVE, $connection->status);
        // No scopes recorded yet -> legacy flags.
        $this->assertSame(['posting', 'messaging', 'ads'], $connection->capabilities);
        foreach ([$page, $ig, $ad] as $asset) {
            $this->assertSame($connection->id, $asset->fresh()->social_connection_id);
        }
        // Token stored encrypted.
        $this->assertNotSame('new-user-token', DB::table('social_connections')->value('access_token'));
    }

    public function test_capabilities_come_from_granted_scopes_when_known(): void
    {
        $this->account(['platform' => 'facebook', 'account_type' => 'ad_account', 'access_token' => 'u', 'expires_at' => now()->addDays(40), 'scopes' => ['ads_read', 'pages_manage_posts'], 'has_ads_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame(['posting', 'ads'], SocialConnection::sole()->capabilities);
        $this->assertSame(['ads_read', 'pages_manage_posts'], SocialConnection::sole()->granted_scopes);
    }

    public function test_expired_or_missing_meta_token_needs_reauth(): void
    {
        $this->account(['platform' => 'facebook', 'account_type' => 'ad_account', 'access_token' => null, 'is_token_valid' => false, 'has_ads_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();
        $this->assertSame(SocialConnection::NEEDS_REAUTH, SocialConnection::sole()->status);

        SocialConnection::query()->delete();
        SocialAccount::query()->delete();
        $this->account(['platform' => 'facebook', 'account_type' => 'ad_account', 'access_token' => 'u', 'expires_at' => now()->subDay(), 'has_ads_permission' => true]);
        $this->artisan('connections:backfill')->assertSuccessful();
        $this->assertSame(SocialConnection::NEEDS_REAUTH, SocialConnection::sole()->status);
    }

    public function test_instagram_login_and_whatsapp_get_their_own_connections(): void
    {
        $igLogin = $this->account(['platform' => 'instagram', 'platform_account_id' => 'ig-1', 'access_token' => 'ig-user-token', 'expires_at' => now()->addDays(3), 'has_posting_permission' => true]);
        $wa = $this->account(['platform' => 'whatsapp', 'platform_account_id' => 'phone-1', 'access_token' => 'wa-token', 'has_messaging_permission' => true]);

        $this->artisan('connections:backfill')->assertSuccessful();

        $ig = SocialConnection::where('step', 'meta.instagram_login')->sole();
        $this->assertSame('ig-1', $ig->provider_account_id);
        $this->assertSame('posts.instagram', $ig->provider_app);
        $this->assertSame(SocialConnection::EXPIRING, $ig->status);
        $this->assertSame($ig->id, $igLogin->fresh()->social_connection_id);

        $whatsapp = SocialConnection::where('step', 'meta.whatsapp')->sole();
        $this->assertSame('phone-1', $whatsapp->provider_account_id);
        $this->assertSame(SocialConnection::ACTIVE, $whatsapp->status);
        $this->assertSame($whatsapp->id, $wa->fresh()->social_connection_id);
    }

    public function test_idempotent_and_never_overwrites_a_real_connection(): void
    {
        $this->metaFixture();
        $real = SocialConnection::create(['user_id' => $this->user->id, 'platform' => 'meta', 'step' => 'meta.login', 'access_token' => 'from-real-connect', 'status' => 'active', 'capabilities' => ['ads']]);

        $this->artisan('connections:backfill')->assertSuccessful();
        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame(1, SocialConnection::count());
        $this->assertSame('from-real-connect', $real->fresh()->access_token);
        $this->assertSame(3, SocialAccount::where('social_connection_id', $real->id)->count());
    }

    public function test_other_platforms_are_left_for_their_own_commits(): void
    {
        // A messaging channel platform that never has a Hub card.
        $telegram = $this->account(['platform' => 'telegram', 'access_token' => 't']);

        $this->artisan('connections:backfill')->assertSuccessful();

        $this->assertSame(0, SocialConnection::count());
        $this->assertNull($telegram->fresh()->social_connection_id);
    }
}
