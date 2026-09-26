<?php

namespace Tests\Feature\Support;

use App\Models\Ticket;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

/**
 * Pins the real bug found in this audit: admin.support.tickets.* used to
 * sit inside a route group gated solely by the 'seller' middleware
 * (EnsureSeller), unreachable by admin/customer_support - and even once
 * reachable, every "is staff" branch inside TicketController was a bare
 * hasRole('admin'), which a customer_support agent would fail (predates
 * that role - see TicketController's own docblock). Fixed in routes/web.php
 * (role:admin|customer_support|seller) and TicketController (hasRole('admin')
 * -> isTeamMember()). These tests exercise the real route + middleware +
 * controller + database chain, and specifically distinguish admin from
 * customer_support so the isTeamMember() fix (not just the routing fix) is
 * actually pinned.
 */
class TicketAuthorizationTest extends TestCase
{
    use CreatesSupportModuleTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportModuleTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function customerSupport(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('customer_support', 'web'));

        return $user;
    }

    private function seller(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('seller', 'web'));

        return $user;
    }

    private function ticketFor(User $owner, array $overrides = []): Ticket
    {
        $ticket = Ticket::create(array_merge([
            'ticket_number'    => Ticket::generateTicketNumber(),
            'user_id'          => $owner->id,
            'subject'          => 'Test subject',
            'priority'         => 'medium',
            'status'           => 'open',
            'last_activity_at' => now(),
        ], $overrides));

        $ticket->messages()->create(['user_id' => $owner->id, 'body' => 'Initial message']);

        return $ticket;
    }

    public function test_admin_sees_all_tickets(): void
    {
        $admin = $this->admin();
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->ticketFor($sellerA);
        $this->ticketFor($sellerB);

        $response = $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets');

        $response->assertOk();
        $this->assertCount(2, $response->json('tickets.data'));
    }

    public function test_customer_support_also_sees_all_tickets(): void
    {
        // Pins the isTeamMember() fix specifically: before it, this branch
        // was hasRole('admin'), so a customer_support agent would silently
        // fall into the "mine only" query and see zero tickets despite
        // being authenticated as staff.
        $agent = $this->customerSupport();
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->ticketFor($sellerA);
        $this->ticketFor($sellerB);

        $response = $this->actingAs($agent)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets');

        $response->assertOk();
        $this->assertCount(2, $response->json('tickets.data'));
    }

    public function test_seller_sees_only_their_own_tickets(): void
    {
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->ticketFor($sellerA);
        $this->ticketFor($sellerB);

        $response = $this->actingAs($sellerA)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets');

        $response->assertOk();
        $tickets = collect($response->json('tickets.data'));
        $this->assertCount(1, $tickets);
        $this->assertSame($sellerA->id, $tickets->first()['user_id']);
    }

    public function test_seller_cannot_view_another_sellers_ticket(): void
    {
        $owner = $this->seller();
        $intruder = $this->seller();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($intruder)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson("/support/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $owner = $this->seller();
        $admin = $this->admin();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson("/support/tickets/{$ticket->id}")
            ->assertOk();
    }

    public function test_customer_support_can_view_any_ticket(): void
    {
        $owner = $this->seller();
        $agent = $this->customerSupport();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($agent)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson("/support/tickets/{$ticket->id}")
            ->assertOk();
    }

    public function test_only_staff_can_change_ticket_status(): void
    {
        $owner = $this->seller();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($owner)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved']);

        $ticket->update(['status' => 'open']);
        $agent = $this->customerSupport();
        $this->actingAs($agent)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'closed']);
    }

    public function test_internal_note_flag_is_forced_off_for_a_seller_even_if_tampered(): void
    {
        $owner = $this->seller();
        $ticket = $this->ticketFor($owner);

        $response = $this->actingAs($owner)->postJson("/support/tickets/{$ticket->id}/messages", [
            'body'             => 'My reply',
            'is_internal_note' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id'        => $ticket->id,
            'user_id'          => $owner->id,
            'is_internal_note' => false,
        ]);
    }

    /**
     * QA finding fixed alongside the ticket-system consolidation
     * (routes/web.php's removal of the duplicate Team\TicketController):
     * a customer reply previously only reopened a ticket stuck in
     * 'waiting_customer' - a reply on an already resolved/closed ticket
     * left it silently marked resolved with a new message nobody's queue
     * would surface again.
     */
    public function test_a_customer_reply_reopens_a_resolved_ticket(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $ticket = $this->ticketFor($seller);
        $this->actingAs($admin)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk();
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->actingAs($seller)
            ->postJson("/support/tickets/{$ticket->id}/messages", ['body' => 'Actually still broken'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_seller_cannot_reply_to_another_sellers_ticket(): void
    {
        $owner = $this->seller();
        $intruder = $this->seller();
        $ticket = $this->ticketFor($owner);

        $this->actingAs($intruder)
            ->postJson("/support/tickets/{$ticket->id}/messages", ['body' => 'sneaky reply'])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_messages', ['ticket_id' => $ticket->id, 'user_id' => $intruder->id]);
    }

    /**
     * Ported from Team\TicketController::index() when the two duplicate
     * ticket systems were consolidated onto this one - see routes/web.php's
     * comment on that removal.
     */
    public function test_search_filters_tickets_by_subject(): void
    {
        $admin = $this->admin();
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->ticketFor($sellerA, ['subject' => 'Cannot connect Instagram']);
        $this->ticketFor($sellerB, ['subject' => 'Billing question']);

        $response = $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets?search=Instagram');

        $response->assertOk();
        $subjects = collect($response->json('tickets.data'))->pluck('subject');
        $this->assertTrue($subjects->contains('Cannot connect Instagram'));
        $this->assertFalse($subjects->contains('Billing question'));
    }

    public function test_assignment_filter_is_staff_only_and_scopes_correctly(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $seller = $this->seller();
        $mine = $this->ticketFor($seller, ['subject' => 'Assigned to me']);
        $mine->update(['assigned_to' => $admin->id]);
        $unassigned = $this->ticketFor($seller, ['subject' => 'Nobody has this']);
        $othersTicket = $this->ticketFor($seller, ['subject' => 'Assigned to someone else']);
        $othersTicket->update(['assigned_to' => $otherAdmin->id]);

        $mineResponse = $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets?assignment=mine');
        $mineSubjects = collect($mineResponse->json('tickets.data'))->pluck('subject');
        $this->assertTrue($mineSubjects->contains('Assigned to me'));
        $this->assertFalse($mineSubjects->contains('Nobody has this'));
        $this->assertFalse($mineSubjects->contains('Assigned to someone else'));

        $unassignedResponse = $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets?assignment=unassigned');
        $unassignedSubjects = collect($unassignedResponse->json('tickets.data'))->pluck('subject');
        $this->assertTrue($unassignedSubjects->contains('Nobody has this'));
        $this->assertFalse($unassignedSubjects->contains('Assigned to me'));

        // A seller passing assignment=mine must not affect their own
        // (already-scoped-to-themselves) results - the filter is silently
        // ignored for non-staff, per isTeamMember() gating in the query.
        $sellerResponse = $this->actingAs($seller)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/support/tickets?assignment=mine');
        $sellerSubjects = collect($sellerResponse->json('tickets.data'))->pluck('subject');
        $this->assertTrue($sellerSubjects->contains('Assigned to me'));
        $this->assertTrue($sellerSubjects->contains('Nobody has this'));
        $this->assertTrue($sellerSubjects->contains('Assigned to someone else'));
    }

    /**
     * QA finding fixed alongside the ticket-system consolidation: the
     * assigned_to validation previously only checked 'exists:users,id',
     * letting a ticket be assigned to any user at all - a seller, even the
     * ticket's own customer - not just an active staff member.
     */
    public function test_a_ticket_cannot_be_assigned_to_a_non_staff_user(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $ticket = $this->ticketFor($seller);

        $this->actingAs($admin)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'in_progress', 'assigned_to' => $seller->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => null]);
    }

    public function test_a_ticket_can_be_assigned_to_an_active_staff_member(): void
    {
        $admin = $this->admin();
        $agent = $this->customerSupport();
        $seller = $this->seller();
        $ticket = $this->ticketFor($seller);

        $this->actingAs($admin)
            ->patchJson("/support/tickets/{$ticket->id}/status", ['status' => 'in_progress', 'assigned_to' => $agent->id])
            ->assertOk();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => $agent->id]);
    }
}
