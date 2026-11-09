<?php

namespace Tests\Feature\Workspaces;

use App\Models\Bundle;
use App\Models\SellerTeamMember;
use App\Models\SocialConnection;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PackageAccessService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Brand / client workspaces: each is its own tenant account, opened by its owner. */
class WorkspaceTest extends TestCase
{
    use CreatesConnectionTables;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        $this->createConnectionTables();
        (require database_path('migrations/2026_11_09_100000_create_workspaces.php'))->up();

        $this->owner = User::create(['name' => 'Agency', 'email' => 'owner@example.com', 'password' => Hash::make('Password-123!')]);

        // Who the request runs as: a module route and a billing route.
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/_probe/module', fn () => (string) Auth::id())->name('admin.probe.module');
            Route::get('/_probe/billing', fn () => (string) Auth::id())->name('admin.subscription.probe');
        });
    }

    private function allow(int $workspaces): void
    {
        $bundle = Subscription::where('user_id', $this->owner->id)->firstOrFail()->bundle;
        $bundle->update(['entitlement_settings' => ['workspaces' => $workspaces] + $bundle->entitlement_settings]);
    }

    private function create(string $name = 'Riyadh Roast Café', array $extra = [])
    {
        return $this->actingAs($this->owner)->postJson(route('seller-workspaces.store'), $extra + [
            'name' => $name, 'kind' => 'client', 'industry' => 'food_beverage', 'country' => 'SA',
            'timezone' => 'Asia/Riyadh', 'content_language' => 'both', 'goals' => ['leads_whatsapp', 'paid_ads'],
        ]);
    }

    public function test_creating_a_workspace_makes_an_isolated_account_and_opens_it(): void
    {
        $this->allow(3);

        $this->create()->assertCreated()->assertJson(['redirect' => route('admin.connections.index')]);

        $workspace = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole();
        $account = $workspace->account;
        $this->assertSame($this->owner->id, $account->workspace_owner_id);
        $this->assertTrue($account->hasRole('seller'));
        $this->assertSame('SAR', $workspace->currency); // from the country, never from input
        $this->assertSame(['leads_whatsapp', 'paid_ads'], $workspace->goals);
        $this->assertSame($workspace->id, session(WorkspaceService::SESSION_KEY));
        $this->assertSame(2, Workspace::where('owner_id', $this->owner->id)->count()); // + the default one

        // Module requests now run as the workspace's account; billing stays with the person.
        $this->get('/_probe/module')->assertSeeText((string) $account->id);
        $this->get('/_probe/billing')->assertSeeText((string) $this->owner->id);
    }

    public function test_the_plan_limit_is_enforced_by_the_backend(): void
    {
        $this->allow(2);
        $this->create('First client')->assertCreated();

        $this->create('Second client')->assertStatus(422)->assertJsonValidationErrors('workspace');
        $this->assertSame(2, Workspace::where('owner_id', $this->owner->id)->count());

        $this->allow(-1); // unlimited
        $this->create('Second client')->assertCreated();
    }

    public function test_plans_without_a_workspace_value_allow_only_the_default(): void
    {
        $bundle = Subscription::where('user_id', $this->owner->id)->firstOrFail()->bundle;
        $settings = $bundle->entitlement_settings;
        unset($settings['workspaces']);
        $bundle->update(['entitlement_settings' => $settings]);

        $this->create()->assertStatus(422)->assertJsonValidationErrors('workspace');
    }

    public function test_workspace_accounts_run_on_the_owners_subscription(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $account = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole()->account;

        $access = app(PackageAccessService::class);
        $this->assertSame(Subscription::where('user_id', $this->owner->id)->value('id'), $access->subscription($account)->id);

        // A deactivated owner takes their workspaces down with them.
        $this->owner->forceFill(['is_active' => false])->save();
        $this->assertNull($access->subscription($account->fresh()));
    }

    public function test_connections_are_isolated_per_workspace(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $account = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole()->account;
        $mine = SocialConnection::create(['user_id' => $this->owner->id, 'platform' => 'meta', 'step' => 'meta.login', 'provider_account_id' => 'fb-1', 'access_token' => 't', 'status' => 'active']);
        $theirs = SocialConnection::create(['user_id' => $account->id, 'platform' => 'meta', 'step' => 'meta.login', 'provider_account_id' => 'fb-2', 'access_token' => 't', 'status' => 'active']);

        $ids = fn () => collect($this->get(route('admin.connections.index'))->assertOk()->viewData('hub')['cards'])
            ->flatMap(fn ($card) => collect($card['connections'])->pluck('id'))->all();

        // The client workspace is open: only its connection.
        $this->assertSame([$theirs->id], $ids());

        // Back to the default workspace: only the owner's own.
        $default = Workspace::where('account_id', $this->owner->id)->sole();
        $this->post(route('seller-workspaces.open', $default))->assertRedirect();
        $this->assertSame([$mine->id], $ids());
    }

    public function test_nobody_can_open_or_tamper_into_someone_elses_workspace(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $workspace = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole();
        $intruder = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('x')]);

        $this->actingAs($intruder)->post(route('seller-workspaces.open', $workspace))->assertNotFound();

        // A forged session id is ignored and dropped; the request stays the intruder's own.
        $this->withSession([WorkspaceService::SESSION_KEY => $workspace->id])->get('/_probe/module')->assertSeeText((string) $intruder->id);
        $this->assertNull(session(WorkspaceService::SESSION_KEY));
    }

    public function test_workspace_accounts_can_never_sign_in(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $account = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole()->account;
        $account->forceFill(['password' => Hash::make('Known-123!')])->save();
        Auth::logout();

        $this->post(route('login'), ['email' => $account->email, 'password' => 'Known-123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_in_shows_the_picker_when_there_is_more_than_one_workspace(): void
    {
        $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'Password-123!'])->assertRedirect(route('dashboard', absolute: false));
        Auth::logout();

        $this->allow(2);
        $this->create()->assertCreated();
        Auth::logout();
        $this->flushSession();

        $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'Password-123!'])->assertRedirect(route('seller-workspaces.index'));
        $this->assertNull(session(WorkspaceService::SESSION_KEY)); // nothing is open until one is chosen
    }

    public function test_the_picker_lists_workspaces_and_invitations(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $inviter = User::create(['name' => 'Najd Real Estate', 'email' => 'najd@example.com', 'password' => Hash::make('x')]);
        SellerTeamMember::create(['owner_id' => $inviter->id, 'email' => 'owner@example.com', 'token_hash' => hash('sha256', 'tok'), 'expires_at' => now()->addDay(), 'permissions' => []]);

        $picker = $this->get(route('seller-workspaces.index'))->assertOk()->viewData('picker');

        $this->assertEqualsCanonicalizing(['Agency', 'Riyadh Roast Café'], collect($picker['workspaces'])->pluck('name')->all());
        $this->assertSame(['Najd Real Estate'], collect($picker['invitations'])->pluck('workspace')->all());
        $this->assertSame(2, $picker['limit']);
    }

    public function test_invitations_can_only_be_answered_by_the_invited_email(): void
    {
        $inviter = User::create(['name' => 'Najd', 'email' => 'najd@example.com', 'password' => Hash::make('x')]);
        $invite = SellerTeamMember::create(['owner_id' => $inviter->id, 'email' => 'someone@example.com', 'token_hash' => hash('sha256', 'tok'), 'expires_at' => now()->addDay(), 'permissions' => []]);

        $this->actingAs($this->owner)->post(route('seller-workspaces.respond', [$invite, 'accept']))->assertForbidden();
        $this->post(route('seller-workspaces.respond', [$invite, 'decline']))->assertNotFound();
        $this->assertNull($invite->fresh()->accepted_at);

        $invite->update(['email' => 'owner@example.com']);
        $this->post(route('seller-workspaces.respond', [$invite, 'decline']))->assertRedirect(route('seller-workspaces.index'));
        $this->assertTrue($invite->fresh()->expires_at->isPast());
    }

    public function test_the_migration_backfills_a_default_workspace_once(): void
    {
        Workspace::query()->delete();
        $migration = require database_path('migrations/2026_11_09_100000_create_workspaces.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, Workspace::where('account_id', $this->owner->id)->where('owner_id', $this->owner->id)->count());
    }
}
