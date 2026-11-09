<?php

namespace Tests\Feature\Auth;

use App\Models\BusinessProfile;
use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\User;
use App\Services\PackageAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Two-step sign-up: organization -> account -> free plan -> Connections. */
class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        // RefreshDatabase cannot run here (MySQL-only migrations); build just what sign-up touches.
        foreach ([
            '0001_01_01_000000_create_users_table', '2026_06_13_213124_create_permission_tables',
            '2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table',
            '2026_08_18_000800_create_subscription_cycles_table', '2026_09_26_100002_create_business_profiles_table',
            '2026_10_17_120000_create_package_entitlements', '2026_10_18_120000_add_seller_package_access',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        }
        Role::findOrCreate('seller', 'web');
    }

    private function freePlan(array $overrides = []): Bundle
    {
        return Bundle::create($overrides + [
            'slug' => 'free-'.uniqid(), 'name_en' => 'Starter Free', 'name_ar' => 'مجاني', 'price' => 0, 'currency' => 'SAR',
            'is_free' => true, 'is_active' => true, 'sort_order' => 1, 'meta' => ['trial_days' => 21],
            'entitlement_settings' => ['social_accounts' => 3, 'publishing_accounts' => 2],
            'access_settings' => ['publishing' => ['mode' => 'included', 'tier' => 'standard']],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'organization_name' => '  Riyadh Coffee Co.  ', 'email' => 'Owner@Example.com',
            'password' => 'Password-123!', 'password_confirmation' => 'Password-123!', 'terms' => '1',
        ];
    }

    public function test_registration_screen_renders_the_wizard(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('id="register-wizard"', false)
            ->assertSee('name="organization_name"', false);
    }

    public function test_registration_creates_the_organization_workspace_and_free_plan(): void
    {
        $this->freePlan(['is_active' => false, 'slug' => 'retired-free']); // ignored: inactive
        $plan = $this->freePlan();

        $response = $this->post('/register', $this->payload());

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $response->assertRedirect(route('admin.connections.index'))->assertSessionHas('success');
        $this->assertAuthenticatedAs($user);

        // Organization = the seller account; its name is the workspace name.
        $this->assertSame('Riyadh Coffee Co.', $user->name);
        $this->assertSame('Riyadh Coffee Co.', BusinessProfile::where('user_id', $user->id)->value('business_name'));
        $this->assertTrue($user->hasRole('seller'));

        // Existing subscription mechanism, plan's own trial length, one cycle (listener skipped).
        $subscription = Subscription::where('user_id', $user->id)->sole();
        $this->assertSame($plan->id, $subscription->bundle_id);
        $this->assertSame('trial', $subscription->status);
        $this->assertSame(now()->addDays(21)->toDateString(), $subscription->end_date->toDateString());
        $this->assertSame(1, SubscriptionCycle::where('user_id', $user->id)->count());

        // Entitlements come from the plan record, not the registration code.
        $access = app(PackageAccessService::class);
        $this->assertTrue($access->allows($user, 'publishing', true));
        $this->assertFalse($access->allows($user, 'advertising'));
    }

    public function test_missing_free_plan_rolls_everything_back(): void
    {
        $this->freePlan(['is_active' => false]);

        $this->from('/register')->post('/register', $this->payload())
            ->assertRedirect('/register')->assertSessionHasErrors('registration')
            ->assertSessionHasInput('organization_name')->assertSessionMissing('_old_input.password');

        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertSame(0, BusinessProfile::count());
        $this->assertSame(0, Subscription::count());
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $this->freePlan();
        User::create(['name' => 'Existing', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);

        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->freePlan();

        $this->post('/register', $this->payload([
            'organization_name' => '<b>', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other', 'terms' => '',
        ]))->assertSessionHasErrors(['organization_name', 'email', 'password', 'terms']);

        $this->post('/register', $this->payload(['organization_name' => '']))->assertSessionHasErrors('organization_name');
        $this->assertSame(0, User::count());
    }

    public function test_authenticated_users_cannot_register_again(): void
    {
        $this->freePlan();
        $this->actingAs(User::create(['name' => 'S', 'email' => 's@example.com', 'password' => bcrypt('x')]));

        // The guest middleware blocks the request before the controller runs.
        $this->assertFalse($this->post('/register', $this->payload())->isSuccessful());
        $this->assertSame(1, User::count());
        $this->assertSame(0, Subscription::count());
    }
}
