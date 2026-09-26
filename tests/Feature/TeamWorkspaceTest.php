<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TeamDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        // Build only this workspace's schema; the legacy migration history has duplicate tables.
        // tickets/ticket_messages added when TeamDashboardService/the ticket
        // pages were repointed from SupportTicket (Team\TicketController,
        // removed) onto Ticket (Admin\TicketController) - see routes/web.php.
        // Ticket uses Spatie's LogsActivity trait, hence activity_log too -
        // those three predate this app's anonymous-migration-class
        // convention, guarded by class name (same technique as
        // CreatesSupportModuleTables) in case another test class in the
        // same PHPUnit process already declared them.
        foreach (['0001_01_01_000000_create_users_table', '2026_06_13_213124_create_permission_tables', '2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table', '2026_09_17_000001_create_team_support_workspace', '2026_09_05_143253_create_tickets_table', '2026_09_05_143254_create_ticket_messages_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        foreach ([
            ['2026_06_13_214010_create_activity_log_table', 'CreateActivityLogTable'],
            ['2026_06_13_214011_add_event_column_to_activity_log_table', 'AddEventColumnToActivityLogTable'],
            ['2026_06_13_214012_add_batch_uuid_column_to_activity_log_table', 'AddBatchUuidColumnToActivityLogTable'],
        ] as [$migrationFile, $migrationClass]) {
            if (!class_exists($migrationClass)) {
                require database_path('migrations/'.$migrationFile.'.php');
            }
            (new $migrationClass())->up();
        }
        foreach (['ads', 'posts'] as $table) {
            Schema::create($table, function (Blueprint $table) { $table->id(); $table->timestamps(); });
        }
        foreach (['admin', 'customer_support', 'seller'] as $role) { Role::findOrCreate($role, 'web'); }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->fresh();
    }
    private function planData(): array
    {
        return ['name_en' => 'Growth', 'name_ar' => 'النمو', 'slug' => 'growth', 'price' => '49.50', 'yearly_price' => '499.00', 'currency' => 'SAR', 'description_en' => 'Grow your team', 'description_ar' => 'نمّ فريقك', 'features_en' => "Unlimited drafts\nPriority support", 'features_ar' => "مسودات غير محدودة\nدعم مميز", 'sort_order' => 2, 'is_active' => 1, 'is_free' => 0, 'is_popular' => 1, 'trial_days' => 15];
    }
    private function ticket(User $seller): Ticket
    {
        return Ticket::create([
            'ticket_number'    => Ticket::generateTicketNumber(),
            'user_id'          => $seller->id,
            'subject'          => 'Help with my account',
            'priority'         => 'medium',
            'status'           => 'open',
            'last_activity_at' => now(),
        ]);
    }

    public function test_admin_and_support_have_shared_layout_but_distinct_navigation(): void
    {
        $this->actingAs($this->user('admin'))->get('/dashboard')->assertOk()->assertSee('layout-wrapper')->assertSee('employees')->assertSee('plans')->assertSee('chart-subscribers')->assertDontSee('admin-subscription-status');
        $this->actingAs($this->user('customer_support'))->get('/dashboard')->assertOk()->assertViewIs('team.support-dashboard')->assertSee('Support tickets')->assertDontSee('Create employee')->assertDontSee('Subscription plans');
    }
    public function test_management_forms_and_ticket_pages_render(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin);
        // /tickets and /tickets/create are the old Team\TicketController
        // paths - now a plain redirect onto the consolidated
        // Admin\TicketController pages (see routes/web.php).
        $this->get('/tickets')->assertRedirect('/support/tickets');
        $this->get('/tickets/create')->assertRedirect('/support/tickets/create');
        foreach (['/employees/create', '/employees/'.$admin->id.'/edit', '/plans/create', '/support/tickets', '/support/tickets/create', '/subscribers'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/plans', $this->planData());
        $this->get('/plans/'.Bundle::firstOrFail()->id.'/edit')->assertOk();
    }

    public function test_checkout_uses_saved_yearly_price_and_customer_with_mocked_gateway(): void
    {
        config(['services.payment.tap.secret_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['api.tap.company/*' => \Illuminate\Support\Facades\Http::response(['id' => 'charge-test', 'transaction' => ['url' => 'https://payments.example.test/checkout']], 200)]);
        $this->actingAs($this->user('admin'))->post('/plans', $this->planData());
        $seller = $this->user('seller');
        $plan = Bundle::firstOrFail();
        $this->actingAs($seller)->postJson('/subscription/checkout', ['bundle_id' => $plan->id, 'cycle' => 'yearly', 'payment_method' => 'card', 'amount' => 1])
            ->assertOk()->assertJsonPath('checkout_url', 'https://payments.example.test/checkout');
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => (float) $request['amount'] === 499.0 && $request['currency'] === 'SAR' && $request['customer']['email'] === $seller->email);
    }

    public function test_staff_permissions_are_enforced_on_server(): void
    {
        $support = $this->user('customer_support');
        foreach (['customer_support', 'seller'] as $role) {
            $this->actingAs($role === 'customer_support' ? $support : $this->user($role));
            foreach (['/employees', '/employees/create', '/plans', '/plans/create'] as $path) { $this->get($path)->assertForbidden(); }
            $this->post('/employees', [])->assertForbidden();
            $this->post('/plans', [])->assertForbidden();
        }
        $this->actingAs($support)->get('/subscribers')->assertOk();
        $support->assignRole('seller');
        $this->get('/subscription/select')->assertForbidden();
    }
    public function test_employee_creation_and_disabling(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/employees', ['name' => 'Support Agent', 'email' => 'support@example.test', 'role' => 'customer_support', 'is_active' => 1, 'password' => 'Support-test!12345', 'password_confirmation' => 'Support-test!12345'])->assertRedirect(route('employees.index'));
        $employee = User::where('email', 'support@example.test')->firstOrFail();
        $this->assertTrue($employee->hasRole('customer_support'));
        $this->assertFalse($employee->subscription()->exists());
        $this->assertTrue(Hash::check('Support-test!12345', $employee->password));
        $this->put('/employees/'.$employee->id, ['name' => $employee->name, 'email' => $employee->email, 'role' => 'customer_support', 'is_active' => 0])->assertRedirect();
        $this->actingAs($employee->fresh())->get('/dashboard')->assertForbidden();
        $this->actingAs($admin)->put('/employees/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'customer_support', 'is_active' => 1])->assertStatus(422);
    }
    public function test_admin_cannot_convert_a_seller_through_employee_editor(): void
    {
        $seller = $this->user('seller');
        $this->actingAs($this->user('admin'))->get('/employees/'.$seller->id.'/edit')->assertNotFound();
        $this->put('/employees/'.$seller->id, [])->assertNotFound();
    }
    public function test_plan_changes_reach_pricing_and_seller_checkout(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/plans', $this->planData())->assertRedirect(route('plans.index'));
        $plan = Bundle::firstOrFail();
        $this->get('/pricing')->assertOk()->assertSee('Growth')->assertSee('49.50')->assertSee('Unlimited drafts');
        $this->actingAs($this->user('seller'))->get('/subscription/plans')->assertOk()->assertJsonPath('data.packages.0.monthly.price', 49.5)->assertJsonPath('data.packages.0.yearly.price', 499)->assertJsonPath('data.packages.0.display_features.1', 'Priority support');
        $this->get('/subscription/checkout-data?plan_id='.$plan->id)->assertOk()->assertJsonPath('data.name', 'Growth')->assertJsonPath('data.yearly.price', 499);
        $this->actingAs($admin)->put('/plans/'.$plan->id, array_replace($this->planData(), ['price' => 79, 'is_active' => 0]))->assertRedirect();
        $this->actingAs($this->user('seller'))->get('/subscription/plans')->assertJsonCount(0, 'data.packages');
        $this->get('/subscription/checkout-data?plan_id='.$plan->id)->assertNotFound();
        $this->get('/pricing')->assertDontSee('Unlimited drafts');
    }
    public function test_subscriber_graph_counts_first_subscriptions_and_zero_fills_months(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 17)->startOfDay());
        $admin = $this->user('admin');
        $seller = $this->user('seller');
        $plan = Bundle::create(['name_en' => 'Plan', 'name_ar' => 'خطة', 'price' => 1]);
        foreach (['2026-07-10', '2026-08-10'] as $date) {
            $subscription = Subscription::create(['user_id' => $seller->id, 'bundle_id' => $plan->id, 'bundle_name' => 'Plan', 'status' => 'active', 'is_active' => true, 'end_date' => '2026-10-01']);
            $subscription->forceFill(['created_at' => $date])->save();
        }
        DB::table('ads')->insert(['created_at' => '2026-08-01']);
        DB::table('posts')->insert(['created_at' => '2026-09-01']);
        $data = app(TeamDashboardService::class)->data($admin);
        $this->assertSame(['subscribers' => 1, 'active' => 1, 'ads' => 1, 'posts' => 1], $data['counts']);
        $this->assertCount(12, $data['chart']['labels']);
        $this->assertSame(1, array_sum($data['chart']['subscribers']));
        $this->assertSame(1, $data['chart']['subscribers'][9]);
        $this->assertSame(1, $data['chart']['ads'][10]);
        $this->assertSame(1, $data['chart']['posts'][11]);
    }
    /**
     * Was written against Team\TicketController (POST/PUT /tickets/*,
     * redirect-based, 404 on cross-tenant access, hard-forbidden on a
     * tampered internal-note flag). Rewritten against the consolidated
     * Admin\TicketController (JSON /support/tickets/* endpoints, 403 on
     * cross-tenant access, a tampered internal-note flag is silently
     * ignored rather than rejected - see storeMessage()'s own comment)
     * when the two duplicate ticket systems were merged - see
     * routes/web.php's removal comment. Same coverage intent: ownership,
     * internal-note visibility, staff-only assignment/status, and the
     * resolved-ticket-reopens-on-customer-reply behavior (ported from
     * Team\TicketController's equivalent, since Admin\TicketController's
     * own reopen logic originally only covered waiting_customer, not
     * resolved/closed - fixed alongside this consolidation).
     */
    public function test_ticket_ownership_notes_assignment_and_status(): void
    {
        $seller = $this->user('seller'); $other = $this->user('seller'); $support = $this->user('customer_support');
        $this->actingAs($seller)->postJson('/support/tickets', ['subject' => 'Billing question', 'body' => 'Please help', 'category' => 'billing', 'priority' => 'high'])->assertOk();
        $ticket = Ticket::firstOrFail();

        $this->actingAs($other)->getJson('/support/tickets/'.$ticket->id)->assertForbidden();
        $this->postJson('/support/tickets/'.$ticket->id.'/messages', ['body' => 'intrusion'])->assertForbidden();

        // A seller tampering with is_internal_note on their own ticket is
        // silently ignored (the note is saved as a normal, visible reply),
        // not rejected outright - see storeMessage()'s own comment.
        $this->actingAs($seller)->postJson('/support/tickets/'.$ticket->id.'/messages', ['body' => 'secret', 'is_internal_note' => 1])->assertOk();
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'secret', 'is_internal_note' => false]);

        $this->patchJson('/support/tickets/'.$ticket->id.'/status', ['status' => 'resolved', 'priority' => 'low'])->assertForbidden();

        $this->actingAs($support)->patchJson('/support/tickets/'.$ticket->id.'/status', ['status' => 'in_progress', 'priority' => 'urgent', 'assigned_to' => $support->id])->assertOk();
        $this->assertSame('urgent', $ticket->fresh()->priority);

        $this->postJson('/support/tickets/'.$ticket->id.'/messages', ['body' => 'Private staff note', 'is_internal_note' => 1])->assertOk();
        $this->get('/support/tickets/'.$ticket->id)->assertOk()->assertSee('Private staff note');
        $this->actingAs($seller)->get('/support/tickets/'.$ticket->id)->assertOk()->assertDontSee('Private staff note');

        $this->actingAs($support)->patchJson('/support/tickets/'.$ticket->id.'/status', ['status' => 'resolved'])->assertOk();
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->actingAs($seller)->postJson('/support/tickets/'.$ticket->id.'/messages', ['body' => 'Still need help'])->assertOk();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }
    public function test_disabled_accounts_cannot_log_in(): void
    {
        $user = $this->user('customer_support');
        $user->is_active = false; $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
