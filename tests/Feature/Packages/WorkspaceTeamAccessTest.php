<?php

namespace Tests\Feature\Packages;

use App\Models\Bundle;
use App\Models\SellerTeamMember;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SellerTeamService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

/**
 * Workspace-based team access: The Marketing Hub agency, four client workspaces,
 * five teammates with different workspaces, roles and modules.
 */
class WorkspaceTeamAccessTest extends TestCase
{
    use CreatesSupportModuleTables;

    private User $owner;

    /** @var array<string, User> client workspace accounts by name */
    private array $clients = [];

    /** Module => probe route (GET reads, POST writes); each answers with the account the request ran as. */
    private const PROBES = ['publishing' => 'posts', 'advertising' => 'ads', 'inbox' => 'chats', 'comments' => 'comments', 'email_marketing' => 'email'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->createSupportModuleTables();
        foreach (['2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table', '2026_08_18_000800_create_subscription_cycles_table', '2026_10_17_120000_create_package_entitlements', '2026_10_18_120000_add_seller_package_access', '2026_10_18_140000_create_seller_team_members', '2026_11_11_100000_add_role_to_seller_team_members', '2026_11_11_120000_add_name_to_seller_team_members', '2026_11_09_100000_create_workspaces', '2026_11_10_140000_add_logo_to_workspaces'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('platform')->default('facebook');
                $table->string('name')->nullable();
                $table->boolean('has_posting_permission')->default(false);
                $table->boolean('has_ads_permission')->default(false);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('social_connections')) {
            (require database_path('migrations/2026_10_07_110000_create_social_connections_table.php'))->up();
        }
        foreach (['admin', 'customer_support', 'seller'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (self::PROBES as $module => $prefix) {
            Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])->group(function () use ($prefix) {
                Route::get("/_probe/$prefix", fn () => response()->json(['account' => auth()->id()]))->name("admin.$prefix.probe");
                Route::post("/_probe/$prefix", fn () => response()->json(['account' => auth()->id()]))->name("admin.$prefix.probe-write");
            });
        }
        Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])->get('/_probe/connections', fn () => response()->json(['account' => auth()->id()]))->name('admin.connections.probe');

        // The Marketing Hub and its four clients.
        $this->owner = User::factory()->create(['name' => 'The Marketing Hub', 'email' => 'owner@marketinghub.test']);
        $this->owner->assignRole('seller');
        $bundle = Bundle::create([
            'name_en' => 'Agency', 'name_ar' => 'وكالة', 'slug' => 'agency', 'price' => 99, 'is_active' => true,
            'access_settings' => array_fill_keys(array_keys(config('package_access.modules')), ['mode' => 'included', 'tier' => 'standard']),
            'entitlement_settings' => ['publishing_accounts' => 10, 'advertising_accounts' => 10, 'ai_credits' => 10, 'media_storage_gb' => 1, 'team_members' => 10, 'workspaces' => 5],
            'meta' => ['allowance_period' => 'monthly'],
        ]);
        Subscription::create(['user_id' => $this->owner->id, 'bundle_id' => $bundle->id, 'bundle_name' => 'Agency', 'status' => 'active', 'is_active' => true, 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'billing_period' => 12]);
        Workspace::create(['owner_id' => $this->owner->id, 'account_id' => $this->owner->id, 'name' => 'The Marketing Hub']);
        foreach (['Al Nasar Club', 'Dr Suliman Al Habib Hospital', 'Al Najd Village Restaurant', 'Riyadh Airport'] as $i => $name) {
            $account = User::factory()->create(['name' => $name, 'email' => "client$i@workspace.invalid", 'workspace_owner_id' => $this->owner->id]);
            $account->assignRole('seller');
            Workspace::create(['owner_id' => $this->owner->id, 'account_id' => $account->id, 'name' => $name, 'kind' => 'client']);
            $this->clients[$name] = $account;
        }
    }

    /** Each person has their own browser session; test sessions otherwise carry over between users. */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function id(string $client): int
    {
        return $this->clients[$client]->id;
    }

    /** The owner invites through the API; the person signs up and accepts the link. */
    private function inviteAndJoin(string $name, string $email, array $workspaces): User
    {
        $url = $this->as($this->owner)->postJson('/seller-team', ['name' => $name, 'email' => $email, 'workspaces' => $workspaces])
            ->assertCreated()->json('invitation_url');
        $this->assertNotNull($url);
        $user = User::factory()->create(['name' => $name, 'email' => $email]);
        $user->assignRole('seller');
        $this->as($user)->post(parse_url($url, PHP_URL_PATH))->assertRedirect();

        return $user;
    }

    private function admin(): array
    {
        return ['role' => 'admin'];
    }

    private function member(array $modules): array
    {
        return ['role' => 'member', 'permissions' => array_fill_keys($modules, 'write')];
    }

    private function rowFor(User $user, string $client): SellerTeamMember
    {
        return SellerTeamMember::where('user_id', $user->id)->where('owner_id', $this->id($client))->firstOrFail();
    }

    /** The workspaces this person sees on the picker. */
    private function visibleWorkspaces(User $user): array
    {
        return collect($this->as($user)->get('/workspaces')->assertOk()->viewData('picker')['workspaces'])
            ->where('kind', 'joined')->pluck('name')->sort()->values()->all();
    }

    /** Opens a workspace as this person, then checks each module (read and write). */
    private function assertModules(User $user, string $client, array $allowed): void
    {
        $this->as($user)->post('/workspaces/'.$this->rowFor($user, $client)->id.'/enter')->assertRedirect();
        foreach (self::PROBES as $module => $prefix) {
            if (in_array($module, $allowed, true)) {
                $this->getJson("/_probe/$prefix")->assertOk()->assertJsonPath('account', $this->id($client));
                $this->postJson("/_probe/$prefix")->assertOk()->assertJsonPath('account', $this->id($client));
            } else {
                $this->getJson("/_probe/$prefix")->assertForbidden();
                $this->postJson("/_probe/$prefix")->assertForbidden();
            }
        }
        $this->post('/workspaces/leave');
    }

    public function test_the_five_teammates_see_and_use_exactly_what_was_assigned(): void
    {
        $all = array_keys($this->clients);
        $everyModule = array_keys(self::PROBES);

        $ahsan = $this->inviteAndJoin('Ahsan', 'ahsan@hub.test', collect($all)->mapWithKeys(fn ($c) => [$this->id($c) => $this->admin()])->all());
        $abdullah = $this->inviteAndJoin('Abdullah', 'abdullah@hub.test', [$this->id('Riyadh Airport') => $this->admin()]);
        $zahid = $this->inviteAndJoin('Zahid', 'zahid@hub.test', [$this->id('Dr Suliman Al Habib Hospital') => $this->admin()]);
        // CRM is not a module yet; Email Marketing stands in as "one module only".
        $latif = $this->inviteAndJoin('Latif', 'latif@hub.test', collect($all)->mapWithKeys(fn ($c) => [$this->id($c) => $this->member(['email_marketing'])])->all());
        // Only two modules means the Member role (an Admin always has every module).
        $waseem = $this->inviteAndJoin('Waseem', 'waseem@hub.test', [$this->id('Al Najd Village Restaurant') => $this->member(['advertising', 'inbox'])]);

        // Seats: owner + 5 people, however many workspaces each has.
        $this->assertSame(6, app(SellerTeamService::class)->seats($this->owner));

        // Workspace visibility.
        $this->assertSame(collect($all)->sort()->values()->all(), $this->visibleWorkspaces($ahsan));
        $this->assertSame(['Riyadh Airport'], $this->visibleWorkspaces($abdullah));
        $this->assertSame(['Dr Suliman Al Habib Hospital'], $this->visibleWorkspaces($zahid));
        $this->assertSame(collect($all)->sort()->values()->all(), $this->visibleWorkspaces($latif));
        $this->assertSame(['Al Najd Village Restaurant'], $this->visibleWorkspaces($waseem));

        // Roles per workspace.
        foreach ($all as $client) {
            $this->assertTrue($this->rowFor($ahsan, $client)->isAdmin());
            $this->assertFalse($this->rowFor($latif, $client)->isAdmin());
        }
        $this->assertTrue($this->rowFor($abdullah, 'Riyadh Airport')->isAdmin());
        $this->assertTrue($this->rowFor($zahid, 'Dr Suliman Al Habib Hospital')->isAdmin());
        $this->assertFalse($this->rowFor($waseem, 'Al Najd Village Restaurant')->isAdmin());

        // Module access, read and write, each request running as that client's account only.
        foreach ($all as $client) {
            $this->assertModules($ahsan, $client, $everyModule);
            $this->assertModules($latif, $client, ['email_marketing']);
        }
        $this->assertModules($abdullah, 'Riyadh Airport', $everyModule);
        $this->assertModules($zahid, 'Dr Suliman Al Habib Hospital', $everyModule);
        $this->assertModules($waseem, 'Al Najd Village Restaurant', ['advertising', 'inbox']);

        // Admins also manage connections; Members don't.
        $this->as($abdullah)->post('/workspaces/'.$this->rowFor($abdullah, 'Riyadh Airport')->id.'/enter');
        $this->getJson('/_probe/connections')->assertOk()->assertJsonPath('account', $this->id('Riyadh Airport'));
        $this->post('/workspaces/leave');
        $this->as($waseem)->post('/workspaces/'.$this->rowFor($waseem, 'Al Najd Village Restaurant')->id.'/enter');
        $this->getJson('/_probe/connections')->assertForbidden();
        $this->getJson('/seller-team')->assertForbidden();
        $this->getJson('/subscription/checkout')->assertForbidden();
    }

    public function test_unassigned_workspaces_cannot_be_reached_by_url_or_api(): void
    {
        $waseem = $this->inviteAndJoin('Waseem', 'waseem@hub.test', [$this->id('Al Najd Village Restaurant') => $this->member(['advertising', 'inbox'])]);
        $zahid = $this->inviteAndJoin('Zahid', 'zahid@hub.test', [$this->id('Dr Suliman Al Habib Hospital') => $this->admin()]);

        // Opening someone else's access row (another workspace) is refused.
        $this->as($waseem)->post('/workspaces/'.$this->rowFor($zahid, 'Dr Suliman Al Habib Hospital')->id.'/enter')->assertForbidden();
        // Owned-workspace routes are the owner's only.
        $hospital = Workspace::where('account_id', $this->id('Dr Suliman Al Habib Hospital'))->first();
        $this->assertContains($this->post('/workspaces/'.$hospital->id.'/open')->status(), [403, 404]);
        $this->assertGreaterThanOrEqual(400, $this->patchJson('/workspaces/'.$hospital->id, ['name' => 'Hijacked'])->status());
        $this->assertSame('Dr Suliman Al Habib Hospital', $hospital->fresh()->name);
        // Without opening a workspace, nothing runs as the agency or its clients.
        $this->getJson('/_probe/ads')->assertJsonMissing(['account' => $this->id('Al Najd Village Restaurant')]);
        $this->assertNotContains($this->owner->id, [$this->getJson('/_probe/ads')->json('account')]);
        // Forged team API calls.
        $this->postJson('/seller-team', ['email' => 'x@hub.test', 'workspaces' => [$this->id('Riyadh Airport') => $this->admin()]])->assertForbidden();
    }

    public function test_permission_changes_take_effect_on_the_next_request(): void
    {
        $all = array_keys($this->clients);
        $latif = $this->inviteAndJoin('Latif', 'latif@hub.test', collect($all)->mapWithKeys(fn ($c) => [$this->id($c) => $this->member(['email_marketing'])])->all());
        $nasarRow = $this->rowFor($latif, 'Al Nasar Club');
        $this->as($latif)->post('/workspaces/'.$nasarRow->id.'/enter')->assertRedirect();
        $this->getJson('/_probe/email')->assertOk();

        // The owner drops Al Nasar Club, and adds Content Posting in Riyadh Airport.
        $keep = collect($all)->reject(fn ($c) => $c === 'Al Nasar Club')
            ->mapWithKeys(fn ($c) => [$this->id($c) => $this->member($c === 'Riyadh Airport' ? ['email_marketing', 'publishing'] : ['email_marketing'])])->all();
        $this->as($this->owner)->patchJson('/seller-team/'.$nasarRow->id, ['workspaces' => $keep])->assertOk();

        // Latif's open Al Nasar Club session stops working straight away.
        $this->actingAs($latif)->withSession(['seller_workspace_member' => $nasarRow->id])->getJson('/_probe/email')->assertForbidden();
        $this->assertSame(['Al Najd Village Restaurant', 'Dr Suliman Al Habib Hospital', 'Riyadh Airport'], $this->visibleWorkspaces($latif));
        $this->assertModules($latif, 'Riyadh Airport', ['email_marketing', 'publishing']);
        $this->assertModules($latif, 'Al Najd Village Restaurant', ['email_marketing']);

        // Adding a workspace for someone already on the team needs no new invitation.
        $this->as($this->owner)->patchJson('/seller-team/'.$this->rowFor($latif, 'Riyadh Airport')->id, ['workspaces' => $keep + [$this->id('Al Nasar Club') => $this->member(['inbox'])]])
            ->assertOk()->assertJsonPath('invitation_url', null);
        $this->assertModules($latif, 'Al Nasar Club', ['inbox']);

        // Removing the person removes every workspace and frees the seat.
        $this->as($this->owner)->deleteJson('/seller-team/'.$this->rowFor($latif, 'Riyadh Airport')->id)->assertNoContent();
        $this->assertSame([], $this->visibleWorkspaces($latif));
        $this->assertSame(1, app(SellerTeamService::class)->seats($this->owner));
    }

    public function test_a_workspace_admin_manages_members_of_their_own_workspace_only(): void
    {
        $abdullah = $this->inviteAndJoin('Abdullah', 'abdullah@hub.test', [$this->id('Riyadh Airport') => $this->admin()]);
        $latif = $this->inviteAndJoin('Latif', 'latif@hub.test', [$this->id('Riyadh Airport') => $this->member(['email_marketing']), $this->id('Al Nasar Club') => $this->member(['email_marketing'])]);
        $this->as($abdullah)->post('/workspaces/'.$this->rowFor($abdullah, 'Riyadh Airport')->id.'/enter');

        $page = $this->getJson('/seller-team')->assertOk();
        $this->assertSame(['Riyadh Airport'], collect($page->json('workspaces'))->pluck('name')->all());
        $this->assertSame([$this->id('Riyadh Airport')], collect($page->json('members'))->pluck('owner_id')->unique()->values()->all());
        // Inside Riyadh Airport: yes. Another workspace, or the Admin role: no.
        $row = $this->rowFor($latif, 'Riyadh Airport');
        $this->patchJson('/seller-team/'.$row->id, ['workspaces' => [$this->id('Riyadh Airport') => $this->member(['email_marketing', 'inbox'])]])->assertOk();
        $this->assertSame('write', $row->fresh()->permission('inbox'));
        $this->patchJson('/seller-team/'.$row->id, ['workspaces' => [$this->id('Dr Suliman Al Habib Hospital') => $this->member(['inbox'])]])->assertForbidden();
        $this->patchJson('/seller-team/'.$row->id, ['workspaces' => [$this->id('Riyadh Airport') => $this->admin()]])->assertForbidden();
        $this->postJson('/seller-team', ['email' => 'new@hub.test', 'workspaces' => [$this->id('Riyadh Airport') => $this->member(['inbox'])]])->assertCreated();
        // Removing Latif here leaves their Al Nasar Club access alone.
        $this->deleteJson('/seller-team/'.$row->id)->assertNoContent();
        $this->assertTrue(SellerTeamMember::where('user_id', $latif->id)->where('owner_id', $this->id('Al Nasar Club'))->exists());
        $this->assertFalse(SellerTeamMember::where('user_id', $latif->id)->where('owner_id', $this->id('Riyadh Airport'))->exists());
    }

    public function test_a_new_teammate_joins_from_the_link_without_any_login_details(): void
    {
        $url = $this->as($this->owner)->postJson('/seller-team', ['name' => 'Waseem', 'email' => 'waseem@hub.test', 'workspaces' => [
            $this->id('Al Najd Village Restaurant') => $this->member(['advertising', 'inbox']), $this->id('Riyadh Airport') => $this->admin(),
        ]])->assertCreated()->json('invitation_url');
        $path = parse_url($url, PHP_URL_PATH);
        $this->flushSession();
        auth()->logout();

        // Signed out, no account: the link shows the workspaces and a set-password form (no redirect to login).
        $this->get($path)->assertOk()->assertSee('Al Najd Village Restaurant')->assertSee('Riyadh Airport')
            ->assertSee('waseem@hub.test')->assertSee('Create account and join')->assertDontSee('Sign in to accept');
        // Weak or mismatched passwords are refused; the email can't be changed (it comes from the invitation).
        $this->post($path.'/join', ['name' => 'Waseem', 'password' => 'short', 'password_confirmation' => 'short', 'terms' => '1'])->assertSessionHasErrors('password');
        $this->assertGuest();

        $this->post($path.'/join', ['name' => 'Waseem Ahmed', 'email' => 'attacker@evil.test', 'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'terms' => '1'])
            ->assertRedirect();
        $user = User::where('email', 'waseem@hub.test')->firstOrFail();
        $this->assertFalse(User::where('email', 'attacker@evil.test')->exists());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Waseem Ahmed', $user->name);
        // Only a login: no organization, workspace, plan or seller role of their own.
        $this->assertFalse($user->hasRole('seller'));
        $this->assertSame(0, Workspace::where('owner_id', $user->id)->count());
        $this->assertSame(0, Subscription::where('user_id', $user->id)->count());
        // Both workspaces joined, and they're working inside the first one.
        $this->assertSame(2, SellerTeamMember::where('user_id', $user->id)->whereNotNull('accepted_at')->count());
        $this->assertSame($this->rowFor($user, 'Al Najd Village Restaurant')->id, session('seller_workspace_member'));
        $this->getJson('/_probe/ads')->assertOk()->assertJsonPath('account', $this->id('Al Najd Village Restaurant'));
        $this->getJson('/_probe/posts')->assertForbidden();

        // Their workspace list shows the agency's two workspaces only, and opening it never creates one of their own.
        $this->as($user)->get('/workspaces')->assertOk();
        $this->assertSame(['Al Najd Village Restaurant', 'Riyadh Airport'], $this->visibleWorkspaces($user));
        $this->assertSame(0, Workspace::where('owner_id', $user->id)->count());
        // Outside a workspace there is nothing of their own: pages lead back to the workspace list.
        $this->get('/dashboard')->assertRedirect(route('seller-workspaces.index'));

        // The link is single use.
        auth()->logout();
        $this->flushSession();
        $this->get($path)->assertNotFound()->assertSee('no longer valid');
        $this->post($path.'/join', ['name' => 'X', 'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'terms' => '1'])->assertNotFound();
    }

    public function test_someone_with_an_account_signs_in_and_comes_back_to_accept(): void
    {
        $existing = User::factory()->create(['email' => 'latif@hub.test', 'password' => bcrypt('Latif-Pass-123!')]);
        $existing->assignRole('seller');
        $url = $this->as($this->owner)->postJson('/seller-team', ['email' => 'latif@hub.test', 'workspaces' => [$this->id('Al Nasar Club') => $this->member(['email_marketing'])]])->json('invitation_url');
        $path = parse_url($url, PHP_URL_PATH);
        $this->flushSession();
        auth()->logout();

        $this->get($path)->assertOk()->assertSee('Sign in to accept')->assertDontSee('Create account and join');
        // No account can be created over an existing one.
        $this->post($path.'/join', ['name' => 'X', 'password' => 'Strong-Pass-123!', 'password_confirmation' => 'Strong-Pass-123!', 'terms' => '1'])->assertRedirect($path);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Latif-Pass-123!', $existing->fresh()->password));
        // Signing in returns to the invitation, then accepting opens the workspace.
        $this->post('/login', ['email' => 'latif@hub.test', 'password' => 'Latif-Pass-123!'])->assertRedirect(url($path));
        $this->get($path)->assertOk()->assertSee(__('seller_team.accept'));
        $this->post($path)->assertRedirect();
        $this->assertSame($this->rowFor($existing, 'Al Nasar Club')->id, session('seller_workspace_member'));

        // Signed in as someone else: told so, not joined.
        $other = $this->as($this->owner)->postJson('/seller-team', ['email' => 'zahid@hub.test', 'workspaces' => [$this->id('Riyadh Airport') => $this->admin()]])->json('invitation_url');
        $this->as($existing)->get(parse_url($other, PHP_URL_PATH))->assertOk()->assertSee('this invitation is for zahid@hub.test', false);
        $this->post(parse_url($other, PHP_URL_PATH))->assertForbidden();
    }

    public function test_teammates_see_their_modules_with_plan_locks_and_support(): void
    {
        // The agency's plan has no Ads Manager or Inbox.
        $bundle = Subscription::where('user_id', $this->owner->id)->first()->bundle;
        $bundle->update(['access_settings' => array_replace($bundle->access_settings, ['advertising' => ['mode' => 'disabled'], 'inbox' => ['mode' => 'disabled']])]);
        Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])
            ->get('/_probe/page', fn () => \Illuminate\Support\Facades\Blade::render("@extends('layouts.app') @section('content') probe @endsection"))->name('admin.posts.probe-page');
        foreach (['admin.help-center.probe' => '/_probe/help', 'admin.tickets.probe' => '/_probe/tickets'] as $name => $uri) {
            Route::middleware(['web', 'auth'])->get($uri, fn () => response()->json(['account' => auth()->id()]))->name($name);
        }
        $ahsan = $this->inviteAndJoin('Ahsan', 'ahsan@hub.test', [$this->id('Riyadh Airport') => $this->admin()]);
        $latif = $this->inviteAndJoin('Latif', 'latif@hub.test', [$this->id('Riyadh Airport') => $this->member(['publishing'])]);

        // Admin: every module is listed; the two the plan lacks carry the "ask the owner" lock, and using them is refused.
        $this->as($ahsan)->post('/workspaces/'.$this->rowFor($ahsan, 'Riyadh Airport')->id.'/enter');
        $page = $this->get('/_probe/page')->assertOk();
        foreach (['ads/dashboard', 'chats/dashboard', 'comments/dashboard', 'email/dashboard', 'help-center', 'support/tickets', 'connections', 'seller-team'] as $link) {
            $page->assertSee($link, false);
        }
        $page->assertSee(__('package_access.sidebar_locked_member'), false)->assertDontSee('subscription/select', false);
        $this->get('/_probe/ads')->assertForbidden()->assertSee(__('package_access.member_upgrade'))->assertDontSee('subscription/select', false);
        $this->getJson('/_probe/help')->assertOk()->assertJsonPath('account', $this->id('Riyadh Airport'));
        $this->getJson('/_probe/tickets')->assertOk()->assertJsonPath('account', $this->id('Riyadh Airport'));

        // Member: only what they were given, plus Help Center; no tickets, connections or team.
        $this->as($latif)->post('/workspaces/'.$this->rowFor($latif, 'Riyadh Airport')->id.'/enter');
        $page = $this->get('/_probe/page')->assertOk()->assertSee('posts/dashboard', false)->assertSee('help-center', false);
        foreach (['ads/dashboard', 'chats/dashboard', 'support/tickets', 'seller-team'] as $link) {
            $page->assertDontSee($link, false);
        }
        $this->getJson('/_probe/help')->assertOk();
        $this->getJson('/_probe/tickets')->assertForbidden();
    }

    public function test_the_invite_form_with_checkboxes_creates_one_invitation_for_several_workspaces(): void
    {
        $restaurant = $this->id('Al Najd Village Restaurant');
        $airport = $this->id('Riyadh Airport');
        // As the browser sends it: hidden enabled=0 then the checkbox, module checkboxes, level selects.
        $form = ['_form' => 'invite', 'name' => 'Waseem', 'email' => 'waseem@hub.test', 'workspaces' => [
            $this->id('Al Nasar Club') => ['enabled' => '0', 'role' => 'member', 'levels' => ['inbox' => 'write']],
            $restaurant => ['enabled' => '1', 'role' => 'member', 'modules' => ['advertising' => '1', 'inbox' => '1'], 'levels' => ['advertising' => 'write', 'inbox' => 'read', 'publishing' => 'write']],
            $airport => ['enabled' => '1', 'role' => 'admin', 'levels' => ['inbox' => 'write']],
        ]];
        $this->as($this->owner)->get('/seller-team')->assertOk()->assertSee('Riyadh Airport')->assertSee('Select all');
        $this->post('/seller-team', $form)->assertRedirect()->assertSessionHas('invitation_url');

        $rows = SellerTeamMember::where('email', 'waseem@hub.test')->get()->keyBy('owner_id');
        $this->assertSame([$restaurant, $airport], $rows->keys()->sort()->values()->all());
        $this->assertSame(['advertising' => 'write', 'inbox' => 'read'], array_filter($rows[$restaurant]->permissions, fn ($v) => $v !== 'none'));
        $this->assertTrue($rows[$airport]->isAdmin());
        $this->assertSame('Waseem', $rows[$restaurant]->name);
        // One link: the page lists both workspaces, and accepting joins both.
        $token = collect(explode('/', session('invitation_url')))->last();
        $user = User::factory()->create(['email' => 'waseem@hub.test']);
        $this->as($user)->get('/workspaces/invitations/'.$token)->assertOk()->assertSee('Al Najd Village Restaurant')->assertSee('Riyadh Airport');
        $this->post('/workspaces/invitations/'.$token)->assertRedirect();
        $this->assertSame(2, SellerTeamMember::where('user_id', $user->id)->whereNotNull('accepted_at')->count());
        // The team page lists the person once, with both workspaces.
        $this->as($this->owner)->get('/seller-team')->assertOk()->assertSee('waseem@hub.test')->assertSee('2 workspaces');
        // A Member with no module, or no workspace at all, is refused.
        $this->postJson('/seller-team', ['email' => 'empty@hub.test', 'workspaces' => [$airport => ['role' => 'member', 'permissions' => []]]])->assertUnprocessable()->assertJsonValidationErrors('workspaces');
        $this->postJson('/seller-team', ['email' => 'empty@hub.test', 'workspaces' => [$airport => ['enabled' => '0']]])->assertUnprocessable()->assertJsonValidationErrors('workspaces');
    }
}
