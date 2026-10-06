<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Step 0c: connections:move-page-tokens. */
class PageTokensTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
            '2026_10_07_100000_add_asset_and_user_tokens_to_social_accounts_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
    }

    private function raw(array $columns): int
    {
        return DB::table('social_accounts')->insertGetId(array_merge([
            'user_id' => $this->user->id,
            'platform' => 'facebook',
            'platform_account_id' => uniqid(),
            'name' => 'Page',
            'account_type' => 'page',
            'token_type' => 'page',
            'is_token_valid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $columns));
    }

    private function row(int $id): object
    {
        return DB::table('social_accounts')->where('id', $id)->first();
    }

    public function test_dry_run_reports_and_writes_nothing(): void
    {
        $id = $this->raw(['access_token' => 'page-token', 'refresh_token' => 'page-token']);

        $this->artisan('connections:move-page-tokens', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame('page-token', $this->row($id)->refresh_token);
        $this->assertNull($this->row($id)->asset_token);
    }

    public function test_duplicate_page_token_is_dropped_from_refresh_token(): void
    {
        $id = $this->raw(['access_token' => 'page-token', 'refresh_token' => 'page-token']);

        $this->artisan('connections:move-page-tokens')->assertSuccessful();

        $row = $this->row($id);
        $this->assertNull($row->refresh_token);
        $this->assertNull($row->user_token);
        $this->assertSame('page-token', $row->asset_token);
        $this->assertSame('page-token', $row->access_token);
    }

    public function test_equality_is_judged_on_decrypted_values(): void
    {
        // Same token, encrypted twice -> different ciphertexts.
        $id = $this->raw(['access_token' => Crypt::encryptString('page-token'), 'refresh_token' => Crypt::encryptString('page-token')]);

        $this->artisan('connections:move-page-tokens')->assertSuccessful();

        $this->assertNull($this->row($id)->user_token);
        $this->assertSame('page-token', SocialAccount::find($id)->asset_token);
    }

    public function test_user_token_in_refresh_token_is_moved_not_lost(): void
    {
        $id = $this->raw(['access_token' => 'page-token', 'refresh_token' => 'user-token']);

        $this->artisan('connections:move-page-tokens')->assertSuccessful();

        $account = SocialAccount::find($id);
        $this->assertNull($account->refresh_token);
        $this->assertSame('user-token', $account->user_token);
        $this->assertSame('page-token', $account->asset_token);
    }

    public function test_non_page_rows_are_untouched_and_run_is_idempotent(): void
    {
        $ad = $this->raw(['account_type' => 'ad_account', 'token_type' => null, 'access_token' => 'user-token', 'refresh_token' => 'real-refresh']);
        $page = $this->raw(['access_token' => 'page-token', 'refresh_token' => 'user-token']);

        $this->artisan('connections:move-page-tokens')->assertSuccessful();
        $this->artisan('connections:move-page-tokens')->assertSuccessful();

        $this->assertSame('real-refresh', $this->row($ad)->refresh_token);
        $this->assertNull($this->row($ad)->asset_token);
        $this->assertSame('user-token', $this->row($page)->user_token);
    }

    public function test_unused_platform_pages_tokens_are_cleared(): void
    {
        Schema::create('platform_pages', function ($table) {
            $table->id();
            $table->string('page_id');
            $table->text('access_token')->nullable();
            $table->timestamps();
        });
        DB::table('platform_pages')->insert(['page_id' => '1', 'access_token' => 'plain-page-token']);

        $this->artisan('connections:move-page-tokens', ['--dry-run' => true])
            ->expectsOutputToContain('platform_pages.access_token (unused) to clear: 1')
            ->assertSuccessful();
        $this->assertSame('plain-page-token', DB::table('platform_pages')->value('access_token'));

        $this->artisan('connections:move-page-tokens')->assertSuccessful();
        $this->assertNull(DB::table('platform_pages')->value('access_token'));
    }
}
