<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Connections\InstagramDuplicates;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The same Instagram account via a Facebook Page and via Instagram Login. */
class InstagramDuplicatesTest extends TestCase
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

    private function pageLinked(string $igId = '17841440159861121'): SocialAccount
    {
        return SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'instagram', 'platform_account_id' => $igId, 'name' => 'social.eaz', 'username' => 'social.eaz', 'access_token' => 'page-tok', 'has_posting_permission' => true, 'is_token_valid' => true]);
    }

    private function instagramLogin(array $settings = []): SocialAccount
    {
        return SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'instagram', 'platform_account_id' => '28172509515715641', 'name' => 'Socialeaz', 'username' => 'social.eaz', 'access_token' => 'ig-tok', 'has_posting_permission' => true, 'is_token_valid' => true,
            'metadata' => ['settings' => ['auth_type' => 'instagram_login'] + $settings]]);
    }

    public function test_instagram_login_copy_of_a_page_linked_account_stops_posting(): void
    {
        $twin = $this->pageLinked();
        $login = $this->instagramLogin(['ig_user_id' => '17841440159861121']);
        Http::fake();

        $pairs = InstagramDuplicates::resolve($this->user->id);

        $this->assertCount(1, $pairs);
        $login->refresh();
        $this->assertSame($twin->id, $login->metadata['settings'][InstagramDuplicates::MARK]);
        $this->assertNotContains('posting', $login->enabled_capabilities);
        $this->assertNotContains('messaging', $login->enabled_capabilities);
        // The composer's account list no longer offers it; the Page-linked one stays.
        $this->assertSame([$twin->id], SocialAccount::where('platform', 'instagram')->usableFor('posting')->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_older_rows_ask_instagram_for_the_account_id_once(): void
    {
        $this->pageLinked();
        $login = $this->instagramLogin();
        Http::fake(['graph.instagram.com/*' => Http::response(['id' => '28172509515715641', 'user_id' => '17841440159861121'])]);

        $this->assertCount(1, InstagramDuplicates::resolve($this->user->id));
        $this->assertSame('17841440159861121', $login->fresh()->metadata['settings']['ig_user_id']);

        // Marked: never re-checked, so turning it back on in the Hub sticks.
        $login->fresh()->forceFill(['enabled_capabilities' => ['posting']])->saveQuietly();
        $this->assertSame([], InstagramDuplicates::resolve($this->user->id));
        Http::assertSentCount(1);
    }

    public function test_different_accounts_and_dry_runs_are_left_alone(): void
    {
        $this->pageLinked('1784-other');
        $login = $this->instagramLogin(['ig_user_id' => '17841440159861121']);
        $this->assertSame([], InstagramDuplicates::resolve($this->user->id));

        $this->pageLinked();
        $this->assertCount(1, InstagramDuplicates::resolve($this->user->id, dryRun: true));
        $this->assertNull($login->fresh()->enabled_capabilities);
    }

    public function test_backfill_reports_and_resolves_existing_pairs(): void
    {
        $this->pageLinked();
        $login = $this->instagramLogin(['ig_user_id' => '17841440159861121']);

        $this->artisan('connections:backfill', ['--dry-run' => true])->expectsOutputToContain('Would turn off posting and inbox')->assertSuccessful();
        $this->assertNull($login->fresh()->enabled_capabilities);

        $this->artisan('connections:backfill')->expectsOutputToContain('Turned off posting and inbox')->assertSuccessful();
        $this->assertNotContains('posting', $login->fresh()->enabled_capabilities);
    }
}
