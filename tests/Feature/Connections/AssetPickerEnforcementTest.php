<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\AdAccountSelection;
use Tests\TestCase;

/** Commit 8a: the Hub's asset picker decides which accounts modules use. */
class AssetPickerEnforcementTest extends TestCase
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
        return SocialAccount::create(array_merge(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => uniqid(), 'name' => 'A'], $attributes));
    }

    public function test_enabled_for_follows_the_picker_with_null_meaning_everything(): void
    {
        $untouched = $this->account(['enabled_capabilities' => null]);
        $inboxOnly = $this->account(['enabled_capabilities' => ['messaging']]);
        $off = $this->account(['enabled_capabilities' => []]);

        $this->assertEqualsCanonicalizing([$untouched->id, $inboxOnly->id], SocialAccount::enabledFor('messaging')->pluck('id')->all());
        $this->assertSame([$untouched->id], SocialAccount::enabledFor('posting')->pluck('id')->all());
        $this->assertTrue($inboxOnly->isEnabledFor('messaging'));
        $this->assertFalse($off->isEnabledFor('messaging'));
    }

    public function test_usable_for_needs_the_permission_and_the_picker(): void
    {
        $page = $this->account(['has_posting_permission' => true]);
        $pageOff = $this->account(['has_posting_permission' => true, 'enabled_capabilities' => ['messaging']]);
        $adOnly = $this->account(['has_ads_permission' => true]);

        $this->assertSame([$page->id], SocialAccount::usableFor('posting')->pluck('id')->all());
        $this->assertSame([$page->id], SocialAccount::withPostingPermission()->pluck('id')->all());
        $this->assertSame([$adOnly->id], SocialAccount::usableFor('ads')->pluck('id')->all());
        $this->assertNotContains($pageOff->id, SocialAccount::usableFor('posting')->pluck('id')->all());
    }

    public function test_ads_manager_only_offers_ad_accounts_left_on(): void
    {
        $on = $this->account(['has_ads_permission' => true, 'name' => 'On']);
        $this->account(['has_ads_permission' => true, 'name' => 'Off', 'enabled_capabilities' => []]);

        $this->assertSame([$on->id], AdAccountSelection::options($this->user->id, 'facebook')->pluck('id')->all());
    }
}
