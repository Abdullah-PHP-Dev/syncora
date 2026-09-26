<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Support tickets shared by both journeys in the BRD: a seller raising a
 * platform issue with Socialeaz, and Socialeaz support working that
 * ticket. One controller, branching by role, rather than two separate
 * portals.
 *
 * Every "is this a staff member" branch below uses
 * User::isTeamMember() (hasRole('admin') || hasRole('customer_support')),
 * not a bare hasRole('admin') check - this controller predates the
 * 'customer_support' role (see git history: written when this app's role
 * setup was just 'admin'/'seller') and was never updated when that role
 * was introduced alongside Team\TicketController. Route middleware
 * (routes/web.php) already allows admin|customer_support|seller through
 * to this controller; leaving these checks as bare hasRole('admin') would
 * let a customer_support agent reach every action here but see zero
 * tickets (index()'s "mine only" branch), be unable to view most tickets
 * (show()'s 403), and be blocked from updateStatus() entirely -
 * authenticated as staff but functionally unable to do the job.
 *
 * Lives outside the subscription-gated route group for the same reason
 * as FaqController: EnsureActiveSubscription aborts non-sellers outright,
 * and a seller whose subscription lapsed should still be able to ask
 * Socialeaz support why.
 *
 * Vue-in-page shape: index()/show() render the Blade wrapper with first-
 * paint props on a plain browser GET, JSON on axios's X-Requested-With
 * GET; store/storeMessage/updateStatus are JSON-only, called by
 * TicketsList.vue / TicketCreateForm.vue / TicketThread.vue. The 'isAdmin'
 * prop/JSON key name is unchanged (Vue only branches on its truthiness)
 * even though it now means "is staff", to avoid a matching change across
 * every Vue component for a rename with no behavioral upside.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->isTeamMember()
            ? Ticket::with(['user', 'assignee'])
            : Ticket::with('assignee')->where('user_id', $user->id);

        $tickets = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('search'), fn ($q) => $q->where('subject', 'like', '%' . $request->string('search') . '%'))
            // Staff-only filters - a seller's own query is already scoped to
            // their own tickets above, "assigned to me"/"unassigned" only
            // makes sense from a staff member's perspective. Ported from
            // Team\TicketController, which this route replaces (see
            // routes/web.php).
            ->when($user->isTeamMember() && $request->input('assignment') === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($user->isTeamMember() && $request->input('assignment') === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->latest('last_activity_at')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'tickets' => $tickets]);
        }

        return view('admin.tickets.index', [
            'tickets'          => $tickets,
            'isAdmin'          => $user->isTeamMember(),
            'initialSearch'    => $request->string('search', '')->toString(),
            'initialAssignment'=> $request->string('assignment', '')->toString(),
        ]);
    }

    public function create(Request $request)
    {
        // Optional prefill from the Help Center's "Ask AI" low-confidence
        // escalation (HelpCenterBrowser.vue) - a plain query string, not a
        // session/flash value, so this route stays a normal shareable GET.
        return view('admin.tickets.create', [
            'initialSubject' => $request->query('subject', ''),
            'initialBody'    => $request->query('body', ''),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'  => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'body'     => ['required', 'string'],
        ]);

        $ticket = Ticket::create([
            'ticket_number'    => Ticket::generateTicketNumber(),
            'user_id'          => Auth::id(),
            'subject'          => $validated['subject'],
            'category'         => $validated['category'] ?? null,
            'priority'         => $validated['priority'],
            'status'           => 'open',
            'last_activity_at' => now(),
        ]);

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'body'    => $validated['body'],
        ]);

        return response()->json([
            'success'     => true,
            'ticket'      => $ticket,
            'message'     => 'Ticket ' . $ticket->ticket_number . ' created.',
            'redirect_url'=> route('admin.tickets.show', $ticket),
        ]);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        abort_unless($user->isTeamMember() || $ticket->user_id === $user->id, 403);

        $ticket->load(['user', 'assignee']);
        $messages = $user->isTeamMember()
            ? $ticket->messages()->with('user')->oldest()->get()
            : $ticket->visibleMessages()->with('user')->oldest()->get();

        // isTeamMember() isn't serializable info Vue needs per-message from
        // the model itself (it'd need to load each message's user + roles) -
        // flatten it into a plain 'is_agent' boolean per message here.
        $messages = $messages->map(function ($message) {
            $message->is_agent = (bool) $message->user?->isTeamMember();

            return $message;
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'ticket' => $ticket, 'messages' => $messages, 'isAdmin' => $user->isTeamMember()]);
        }

        return view('admin.tickets.show', [
            'ticket'   => $ticket,
            'messages' => $messages,
            'isAdmin'  => $user->isTeamMember(),
        ]);
    }

    public function storeMessage(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        abort_unless($user->isTeamMember() || $ticket->user_id === $user->id, 403);

        $validated = $request->validate([
            'body'             => ['required', 'string'],
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        // Only a staff agent can leave an internal note; a seller's own
        // reply on their own ticket is never treated as one, even if the
        // field were somehow tampered with client-side.
        $isInternal = $user->isTeamMember() && $request->boolean('is_internal_note');

        $message = TicketMessage::create([
            'ticket_id'        => $ticket->id,
            'user_id'          => $user->id,
            'body'             => $validated['body'],
            'is_internal_note' => $isInternal,
        ])->load('user');
        $message->is_agent = (bool) $user->isTeamMember();

        // A seller replying to their own ticket pulls it out of "waiting
        // for customer" back into the agent's queue, mirroring the BRD's
        // ticket lifecycle (SLA clock resumes) - and, ported from
        // Team\TicketController during the ticket-system consolidation, a
        // customer reply on an already resolved/closed ticket reopens it
        // too rather than silently attaching a reply nobody's queue will
        // ever surface again.
        $reopensOnCustomerReply = !$user->isTeamMember()
            && in_array($ticket->status, ['waiting_customer', 'resolved', 'closed'], true);

        $updates = [
            'last_activity_at' => now(),
            'status' => $user->isTeamMember()
                ? ($ticket->status === 'open' ? 'in_progress' : $ticket->status)
                : ($reopensOnCustomerReply ? 'in_progress' : $ticket->status),
        ];

        if ($reopensOnCustomerReply) {
            $updates['resolved_at'] = null;
            $updates['closed_at'] = null;
        }

        $ticket->update($updates);

        return response()->json([
            'success' => true,
            'message_row' => $message,
            'ticket'  => $ticket->fresh(),
            'message' => $isInternal ? 'Internal note added.' : 'Reply sent.',
        ]);
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::user()->isTeamMember(), 403);

        $validated = $request->validate([
            'status'      => ['required', 'in:open,in_progress,waiting_customer,resolved,closed'],
            'priority'    => ['nullable', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        // QA finding (ported from Team\TicketController, which validated
        // this correctly): 'exists:users,id' alone let a ticket be assigned
        // to ANY user id - a seller, even the ticket's own customer - not
        // just an active staff member.
        if (!empty($validated['assigned_to'])) {
            abort_unless(
                User::role(['admin', 'customer_support'])->where('is_active', true)->whereKey($validated['assigned_to'])->exists(),
                422,
                'Choose an active team member.'
            );
        }

        $ticket->update([
            'status'           => $validated['status'],
            // Staff-editable post-creation - Team\TicketController's
            // equivalent update() allowed this too; not part of this
            // action's validated fields until the ticket-system
            // consolidation surfaced the gap.
            'priority'         => $validated['priority'] ?? $ticket->priority,
            'assigned_to'      => $validated['assigned_to'] ?? $ticket->assigned_to,
            'last_activity_at' => now(),
            'resolved_at'      => $validated['status'] === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at'        => $validated['status'] === 'closed' ? now() : $ticket->closed_at,
        ]);

        return response()->json(['success' => true, 'ticket' => $ticket->fresh('assignee'), 'message' => 'Ticket status updated.']);
    }
}
