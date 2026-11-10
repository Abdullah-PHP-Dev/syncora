<?php

namespace Tests\Feature\Connections;

use App\Exceptions\SocialAccountConnectedElsewhere;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\AccountOwnership;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** One Page / account, one workspace: blocked across workspaces and organizations by platform ID. */
class AccountOwnershipTest extends TestCase
{
    use CreatesConnectionTables;

    private User $orgA;

    private User $orgB;

    private User $workspaceOfA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createMessageChannelsTable();
        (require database_path('migrations/2026_11_09_100000_create_workspaces.php'))->up();
        Settings::set('posts.facebook.client_id', 'app-1');
        Settings::set('posts.facebook.client_secret', 'app-secret');
        $this->orgA = User::create(['name' => 'Org A', 'email' => 'a@example.com', 'password' => bcrypt('x')]);
        $this->orgB = User::create(['name' => 'Org B', 'email' => 'b@example.com', 'password' => bcrypt('x')]);
        // A second workspace (brand/client) of organization A.
        $this->workspaceOfA = User::create(['name' => 'Client of A', 'email' => 'ws@example.invalid', 'password' => bcrypt('x'), 'workspace_owner_id' => $this->orgA->id]);
    }

    /** The Meta callback returning these Pages (Page 111 also has a linked Instagram). */
    private function metaCallback(User $as, array $pages = [['id' => '111', 'name' => 'Socialeaz'], ['id' => '222', 'name' => 'Socialeaz Store']], string $adAccount = '9'): void
    {
        // A fresh fake per callback: stubs from an earlier tenant's callback must not answer this one.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'user-token-'.$as->id, 'expires_in' => 5183944]),
            'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [['permission' => 'pages_manage_posts', 'status' => 'granted'], ['permission' => 'ads_read', 'status' => 'granted']]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => array_map(fn ($p) => $p + ['access_token' => 'page-token-'.$as->id.'-'.$p['id']]
                + ($p['id'] === '111' ? ['instagram_business_account' => ['id' => '17841', 'username' => 'social.eaz']] : []), $pages)]),
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_'.$adAccount, 'account_id' => $adAccount, 'name' => 'Socialeaz Ads', 'account_status' => 1, 'business' => ['id' => 'b1']]]]),
            'graph.facebook.com/*/me?*' => Http::response(['id' => 'fb-user-'.$as->id]),
            '*' => Http::response([], 404),
        ]);
        $this->actingAs($as);
        session(['social_oauth_state_facebook' => 's-'.$as->id]);
        app(SocialAuthService::class)->callback('facebook', 'code', 's-'.$as->id);
    }

    private function owners(string $platform, string $id): array
    {
        return SocialAccount::where('platform', $platform)->whereIn('platform_account_id', AccountOwnership::variants($platform, $id))->pluck('user_id')->unique()->sort()->values()->all();
    }

    public function test_a_new_page_connects_and_reconnecting_in_the_same_workspace_does_not_duplicate(): void
    {
        $this->metaCallback($this->orgA);
        $this->metaCallback($this->orgA);

        $this->assertSame(1, SocialAccount::where(['platform' => 'facebook', 'platform_account_id' => '111'])->count());
        $this->assertSame([$this->orgA->id], $this->owners('facebook', '111'));
        $this->assertSame([], session(AccountOwnership::SESSION_KEY, []));
    }

    public function test_pages_held_by_another_workspace_or_organization_are_left_out_one_by_one(): void
    {
        // A has Page 111 (+ its Instagram) and the ad account; B selects 111, a new Page 333, and the same ad account.
        $this->metaCallback($this->orgA, [['id' => '111', 'name' => 'Socialeaz']]);
        $tokenBefore = SocialAccount::where(['platform' => 'facebook', 'platform_account_id' => '111'])->sole()->getAttributes()['access_token'];

        $this->metaCallback($this->orgB, [['id' => '111', 'name' => 'Socialeaz'], ['id' => '333', 'name' => 'B Bakery']]);

        $this->assertSame([$this->orgA->id], $this->owners('facebook', '111'));
        $this->assertSame([$this->orgA->id], $this->owners('instagram', '17841'));
        $this->assertSame([$this->orgA->id], $this->owners('facebook', '9'));
        $this->assertSame([$this->orgB->id], $this->owners('facebook', '333')); // a different Page still connects
        // Named individually; the Page's linked Instagram goes with the Page.
        $this->assertSame(['Socialeaz', 'Socialeaz Ads'], session(AccountOwnership::SESSION_KEY));
        // A's connection and token are untouched.
        $this->assertSame($tokenBefore, SocialAccount::where(['platform' => 'facebook', 'platform_account_id' => '111'])->sole()->getAttributes()['access_token']);
    }

    public function test_another_workspace_of_the_same_organization_is_blocked_too(): void
    {
        $this->metaCallback($this->orgA, [['id' => '111', 'name' => 'Socialeaz']]);
        $this->metaCallback($this->workspaceOfA, [['id' => '111', 'name' => 'Socialeaz'], ['id' => '222', 'name' => 'Socialeaz Store']]);

        $this->assertSame([$this->orgA->id], $this->owners('facebook', '111'));
        $this->assertSame([$this->workspaceOfA->id], $this->owners('facebook', '222'));
    }

    public function test_the_connections_page_names_what_was_left_out_without_naming_the_other_tenant(): void
    {
        $this->metaCallback($this->orgA, [['id' => '111', 'name' => 'Socialeaz']]);
        $this->metaCallback($this->orgB, [['id' => '111', 'name' => 'Socialeaz']]);
        session()->flash('success', 'Facebook connected.');

        $flash = $this->get(route('admin.connections.index'))->assertOk()->viewData('flash');

        $this->assertStringContainsString('Socialeaz', $flash['error']);
        $this->assertStringContainsString('already connected to another organization or workspace', $flash['error']);
        $this->assertStringNotContainsString('Org A', $flash['error']);
        $this->assertSame([], session(AccountOwnership::SESSION_KEY, [])); // shown once
    }

    public function test_direct_writes_and_requests_cannot_bypass_it(): void
    {
        SocialAccount::create(['user_id' => $this->orgA->id, 'platform' => 'facebook', 'platform_account_id' => 'act_9', 'name' => 'Socialeaz Ads', 'access_token' => 't']);

        try {
            SocialAccount::create(['user_id' => $this->orgB->id, 'platform' => 'facebook', 'platform_account_id' => '9', 'name' => 'Socialeaz Ads', 'access_token' => 'u']);
            $this->fail('A second owner was created.');
        } catch (SocialAccountConnectedElsewhere $e) {
            $this->assertStringContainsString('Socialeaz Ads', $e->getMessage());
        }
        // Moving an account to another tenant is refused as well.
        $mine = SocialAccount::create(['user_id' => $this->orgB->id, 'platform' => 'x', 'platform_account_id' => 'x-1', 'access_token' => 't']);
        SocialAccount::create(['user_id' => $this->orgA->id, 'platform' => 'x', 'platform_account_id' => 'x-2', 'access_token' => 't']);
        $this->expectException(SocialAccountConnectedElsewhere::class);
        $mine->update(['platform_account_id' => 'x-2']);
    }

    public function test_requests_get_a_409_or_go_back_to_connections_with_the_message(): void
    {
        SocialAccount::create(['user_id' => $this->orgA->id, 'platform' => 'tiktok', 'platform_account_id' => 'tt-1', 'name' => 'Bakery', 'access_token' => 't']);
        Route::middleware('web')->post('/_connect-probe', fn () => SocialAccount::create(['user_id' => auth()->id(), 'platform' => 'tiktok', 'platform_account_id' => 'tt-1', 'name' => 'Bakery', 'access_token' => 'u']));

        $this->actingAs($this->orgB)->postJson('/_connect-probe')->assertStatus(409)->assertJsonPath('code', 'account_connected_elsewhere');
        $this->post('/_connect-probe')->assertRedirect(route('admin.connections.index'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Bakery'));
        $this->assertSame([$this->orgA->id], $this->owners('tiktok', 'tt-1'));
    }

    public function test_a_concurrent_connect_of_the_same_account_is_refused(): void
    {
        config(['connections.ownership_lock_wait' => 0]);
        // Another workspace's callback is mid-way through connecting this account.
        $lock = Cache::lock(AccountOwnership::lockKey('youtube', 'UC1'), 15);
        $this->assertTrue($lock->get());

        try {
            SocialAccount::create(['user_id' => $this->orgB->id, 'platform' => 'youtube', 'platform_account_id' => 'UC1', 'access_token' => 't']);
            $this->fail('Connected while another workspace held the account.');
        } catch (SocialAccountConnectedElsewhere) {
            $this->assertSame([], $this->owners('youtube', 'UC1'));
        } finally {
            $lock->release();
        }
        // Once released (committed elsewhere or abandoned), the normal rule applies.
        SocialAccount::create(['user_id' => $this->orgB->id, 'platform' => 'youtube', 'platform_account_id' => 'UC1', 'access_token' => 't']);
        $this->assertSame([$this->orgB->id], $this->owners('youtube', 'UC1'));
    }

    public function test_a_page_disconnected_elsewhere_can_be_connected(): void
    {
        $this->metaCallback($this->orgA, [['id' => '111', 'name' => 'Socialeaz']]);
        // Disconnect keeps history rows but clears their credentials (BaseDriver::disconnectLocally).
        SocialAccount::where('user_id', $this->orgA->id)->update(['access_token' => null, 'asset_token' => null, 'user_token' => null, 'is_token_valid' => false]);

        $this->metaCallback($this->orgB, [['id' => '111', 'name' => 'Socialeaz']]);

        $this->assertSame([$this->orgA->id, $this->orgB->id], $this->owners('facebook', '111'));
        $this->assertSame([], session(AccountOwnership::SESSION_KEY, []));
    }
}
