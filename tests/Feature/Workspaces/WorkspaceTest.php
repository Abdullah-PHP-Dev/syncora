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
        (require database_path('migrations/2026_11_10_140000_add_logo_to_workspaces.php'))->up();

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

    public function test_finish_setup_opens_the_workspace_on_connections(): void
    {
        $default = app(WorkspaceService::class)->ensureDefault($this->owner);

        $this->actingAs($this->owner)->post(route('seller-workspaces.open', $default), ['to' => 'setup'])
            ->assertRedirect(route('admin.connections.index'));
        $this->assertSame($default->id, session(WorkspaceService::SESSION_KEY));

        // Plain "Open" keeps going to the dashboard.
        $this->post(route('seller-workspaces.open', $default))->assertRedirect(route('dashboard'));
    }

    public function test_owners_can_edit_a_workspace(): void
    {
        $this->allow(2);
        $this->create()->assertCreated();
        $workspace = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole();

        $this->patchJson(route('seller-workspaces.update', $workspace), [
            'name' => 'Riyadh Roast & Co', 'kind' => 'own', 'industry' => 'retail', 'country' => 'AE',
            'timezone' => 'Asia/Dubai', 'content_language' => 'en', 'goals' => ['google_reviews'], 'currency' => 'USD',
        ])->assertOk()->assertJsonPath('workspace.name', 'Riyadh Roast & Co');

        $workspace->refresh();
        $this->assertSame(['own', 'retail', 'AE', 'AED', 'Asia/Dubai', 'en', ['google_reviews']],
            [$workspace->kind, $workspace->industry, $workspace->country, $workspace->currency, $workspace->timezone, $workspace->content_language, $workspace->goals]);
        // The brand's own account follows the new name (team members see it).
        $this->assertSame('Riyadh Roast & Co', $workspace->account->name);
        $this->assertSame('Riyadh Roast & Co', $workspace->account->businessProfile->business_name);
    }

    public function test_editing_the_default_workspace_keeps_the_persons_name(): void
    {
        $default = app(WorkspaceService::class)->ensureDefault($this->owner);

        $this->actingAs($this->owner)->patchJson(route('seller-workspaces.update', $default), ['name' => 'Agency HQ', 'kind' => 'own'])->assertOk();

        $this->assertSame('Agency HQ', $default->fresh()->name);
        $this->assertSame('Agency', $this->owner->fresh()->name);
    }

    public function test_nobody_else_can_edit_a_workspace_and_input_is_validated(): void
    {
        $default = app(WorkspaceService::class)->ensureDefault($this->owner);
        $intruder = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('x')]);

        $this->actingAs($intruder)->patchJson(route('seller-workspaces.update', $default), ['name' => 'Mine now', 'kind' => 'own'])->assertNotFound();
        $this->assertSame('Agency', $default->fresh()->name);

        $this->actingAs($this->owner)->patchJson(route('seller-workspaces.update', $default), ['name' => '<b>', 'kind' => 'agency', 'country' => 'ZZ'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'kind', 'country']);
    }

    public function test_a_logo_can_be_added_replaced_and_removed(): void
    {
        (require database_path('migrations/2026_11_10_140000_add_logo_to_workspaces.php'))->up();
        $disk = \Illuminate\Support\Facades\Storage::fake(config('workspaces.logo_disk'));
        $this->allow(2);

        $this->create('Logo Co', ['logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200)])->assertCreated();
        $workspace = Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->sole();
        $first = $workspace->logo_path;
        $this->assertStringStartsWith('uploads/workspace-logos/'.$this->owner->id.'/', $first);
        $disk->assertExists($first);

        // Replace: the old file goes, the new one is served.
        $this->post(route('seller-workspaces.update', $workspace), ['_method' => 'PATCH', 'name' => 'Logo Co', 'kind' => 'client',
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('new.webp', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('workspace.logo_url', fn ($url) => is_string($url) && str_contains($url, 'workspace-logos'));
        $second = $workspace->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        $disk->assertMissing($first);
        $disk->assertExists($second);

        // Editing other details keeps the logo; remove_logo takes it off.
        $this->patchJson(route('seller-workspaces.update', $workspace), ['name' => 'Logo Co 2', 'kind' => 'client'])->assertOk();
        $this->assertSame($second, $workspace->fresh()->logo_path);
        $this->patchJson(route('seller-workspaces.update', $workspace), ['name' => 'Logo Co 2', 'kind' => 'client', 'remove_logo' => true])
            ->assertOk()->assertJsonPath('workspace.logo_url', null);
        $disk->assertMissing($second);
    }

    public function test_only_safe_images_are_accepted_as_logos(): void
    {
        (require database_path('migrations/2026_11_10_140000_add_logo_to_workspaces.php'))->up();
        \Illuminate\Support\Facades\Storage::fake(config('workspaces.logo_disk'));
        $this->allow(5);
        $upload = \Illuminate\Http\UploadedFile::class;

        foreach ([
            $upload::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'),
            $upload::fake()->create('logo.pdf', 10, 'application/pdf'),
            $upload::fake()->image('tiny.png', 20, 20),
            $upload::fake()->image('huge.png', 200, 200)->size(3000),
        ] as $file) {
            $this->create('Bad logo', ['logo' => $file])->assertStatus(422)->assertJsonValidationErrors('logo');
        }
        $this->assertSame(0, Workspace::where('owner_id', $this->owner->id)->where('kind', 'client')->count());
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
