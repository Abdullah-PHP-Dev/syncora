<?php

namespace Tests\Feature\Navigation;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\AdAccountSelection;
use Tests\TestCase;

/** Which ad account Ads Manager pages and *AdService constructors use. */
class AdAccountSelectionTest extends TestCase
{
    private User $seller;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $this->seller = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
    }

    private function account(User $user, string $name, bool $ads = true, string $platform = 'facebook'): SocialAccount
    {
        return SocialAccount::create(['user_id' => $user->id, 'platform' => $platform, 'platform_account_id' => uniqid(), 'name' => $name, 'has_ads_permission' => $ads]);
    }

    public function test_defaults_to_first_ad_account_not_a_posting_page(): void
    {
        $this->account($this->seller, 'Posting Page', false);
        $ad = $this->account($this->seller, 'ABC Ads');

        $this->assertSame($ad->id, AdAccountSelection::resolve($this->seller->id, 'facebook')->id);
    }

    public function test_selection_is_remembered_and_used(): void
    {
        $this->account($this->seller, 'ABC');
        $xyz = $this->account($this->seller, 'XYZ Store');

        $this->assertTrue(AdAccountSelection::select($this->seller->id, 'facebook', $xyz->id));
        $this->assertSame($xyz->id, AdAccountSelection::resolve($this->seller->id, 'facebook')->id);
        $this->assertCount(2, AdAccountSelection::options($this->seller->id, 'facebook'));
    }

    public function test_cannot_select_another_sellers_account(): void
    {
        $mine = $this->account($this->seller, 'Mine');
        $theirs = $this->account($this->other, 'Theirs');

        $this->assertFalse(AdAccountSelection::select($this->seller->id, 'facebook', $theirs->id));
        $this->assertSame($mine->id, AdAccountSelection::resolve($this->seller->id, 'facebook')->id);
    }

    public function test_youtube_uses_the_google_ads_account(): void
    {
        $google = $this->account($this->seller, 'Google Ads', true, 'google');

        $this->assertSame($google->id, AdAccountSelection::resolve($this->seller->id, 'youtube')->id);
    }

    public function test_falls_back_to_previous_behaviour_without_ads_permission_flag(): void
    {
        $legacy = $this->account($this->seller, 'Legacy row', false, 'snapchat');

        $this->assertSame($legacy->id, AdAccountSelection::resolve($this->seller->id, 'snapchat')->id);
        $this->assertNull(AdAccountSelection::resolve($this->seller->id, 'tiktok'));
    }
}
