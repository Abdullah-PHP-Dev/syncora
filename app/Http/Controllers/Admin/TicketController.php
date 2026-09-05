<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Support tickets shared by both journeys in the BRD: a seller raising a
 * platform issue with Socialeaz, and (once an 'admin'-role user exists
 * day-to-day) Socialeaz support working that ticket. One controller,
 * branching by role, rather than two separate portals - see this app's
 * real role setup (just 'admin'/'seller', no separate workspace/tenant
 * table) discovered while grounding this feature before building it.
 *
 * Lives outside the subscription-gated route group for the same reason
 * as FaqController: EnsureActiveSubscription aborts non-sellers outright,
 * and a seller whose subscription lapsed should still be able to ask
 * Socialeaz support why.
 *
 * Vue-in-page shape: index()/show() render the Blade wrapper with first-
 * paint props on a plain browser GET, JSON on axios's X-Requested-With
 * GET; store/storeMessage/updateStatus are JSON-only, called by
 * TicketsList.vue / TicketCreateForm.vue / TicketThread.vue.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->hasRole('admin')
            ? Ticket::with(['user', 'assignee'])
            : Ticket::with('assignee')->where('user_id', $user->id);

        $tickets = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->latest('last_activity_at')
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'tickets' => $tickets]);
        }

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'isAdmin' => $user->hasRole('admin'),
        ]);
    }

    public function create()
    {
        return view('admin.tickets.create');
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
        abort_unless($user->hasRole('admin') || $ticket->user_id === $user->id, 403);

        $ticket->load(['user', 'assignee']);
        $messages = $user->hasRole('admin')
            ? $ticket->messages()->with('user')->oldest()->get()
            : $ticket->visibleMessages()->with('user')->oldest()->get();

        // hasRole() isn't serializable info Vue needs per-message from the
        // model itself (it'd need to load each message's user + roles) -
        // flatten it into a plain 'is_agent' boolean per message here.
        $messages = $messages->map(function ($message) {
            $message->is_agent = (bool) $message->user?->hasRole('admin');

            return $message;
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'ticket' => $ticket, 'messages' => $messages, 'isAdmin' => $user->hasRole('admin')]);
        }

        return view('admin.tickets.show', [
            'ticket'   => $ticket,
            'messages' => $messages,
            'isAdmin'  => $user->hasRole('admin'),
        ]);
    }

    public function storeMessage(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        abort_unless($user->hasRole('admin') || $ticket->user_id === $user->id, 403);

        $validated = $request->validate([
            'body'             => ['required', 'string'],
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        // Only an admin-role agent can leave an internal note; a seller's
        // own reply on their own ticket is never treated as one, even if
        // the field were somehow tampered with client-side.
        $isInternal = $user->hasRole('admin') && $request->boolean('is_internal_note');

        $message = TicketMessage::create([
            'ticket_id'        => $ticket->id,
            'user_id'          => $user->id,
            'body'             => $validated['body'],
            'is_internal_note' => $isInternal,
        ])->load('user');
        $message->is_agent = (bool) $user->hasRole('admin');

        $ticket->update([
            'last_activity_at' => now(),
            // A seller replying to their own ticket pulls it out of
            // "waiting for customer" back into the agent's queue,
            // mirroring the BRD's ticket lifecycle (SLA clock resumes).
            'status' => $user->hasRole('admin')
                ? ($ticket->status === 'open' ? 'in_progress' : $ticket->status)
                : ($ticket->status === 'waiting_customer' ? 'in_progress' : $ticket->status),
        ]);

        return response()->json([
            'success' => true,
            'message_row' => $message,
            'ticket'  => $ticket->fresh(),
            'message' => $isInternal ? 'Internal note added.' : 'Reply sent.',
        ]);
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        abort_unless(Auth::user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'status'      => ['required', 'in:open,in_progress,waiting_customer,resolved,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $ticket->update([
            'status'           => $validated['status'],
            'assigned_to'      => $validated['assigned_to'] ?? $ticket->assigned_to,
            'last_activity_at' => now(),
            'resolved_at'      => $validated['status'] === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at'        => $validated['status'] === 'closed' ? now() : $ticket->closed_at,
        ]);

        return response()->json(['success' => true, 'ticket' => $ticket->fresh('assignee'), 'message' => 'Ticket status updated.']);
    }
}
