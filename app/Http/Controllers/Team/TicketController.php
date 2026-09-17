<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'assignment' => ['nullable', Rule::in(['mine', 'unassigned'])],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $staff = $request->user()->isTeamMember();
        $tickets = SupportTicket::with(['customer', 'assignee'])
            ->when(!$staff, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($data['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($data['priority'] ?? null, fn ($q, $value) => $q->where('priority', $value))
            ->when(($data['assignment'] ?? null) === 'mine', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(($data['assignment'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($data['search'] ?? null, fn ($q, $value) => $q->where('subject', 'like', '%'.$value.'%'))
            ->latest('updated_at')->paginate(20)->withQueryString();
        return view('tickets.index', compact('tickets', 'staff'));
    }
    public function create() { return view('tickets.create'); }
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
        ]);
        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = SupportTicket::create(collect($data)->except('body')->all() + ['user_id' => $request->user()->id]);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
            return $ticket;
        });
        return redirect()->route('tickets.show', $ticket)->with('success', __('Ticket created.'));
    }
    public function show(Request $request, SupportTicket $ticket)
    {
        $this->checkAccess($request, $ticket);
        $staff = $request->user()->isTeamMember();
        $ticket->load(['customer.subscription.bundle', 'assignee']);
        $messages = $ticket->messages()->with('author')->when(!$staff, fn ($q) => $q->where('is_internal', false))->oldest()->paginate(30);
        $employees = $staff ? User::role(['admin', 'customer_support'])->where('is_active', true)->orderBy('name')->get() : collect();
        return view('tickets.show', compact('ticket', 'messages', 'staff', 'employees'));
    }
    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->checkAccess($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'is_internal' => ['sometimes', 'boolean']]);
        $internal = $request->boolean('is_internal');
        abort_if($internal && !$request->user()->isTeamMember(), 403);
        DB::transaction(function () use ($request, $ticket, $data, $internal) {
            $ticket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_internal' => $internal]);
            if (!$internal) {
                $ticket->status = $request->user()->isTeamMember() ? 'waiting_customer' : 'open';
                $ticket->resolved_at = null;
            }
            $ticket->touch();
        });
        return redirect()->route('tickets.show', $ticket)->with('success', __('Reply saved.'));
    }
    public function update(Request $request, SupportTicket $ticket)
    {
        abort_unless($request->user()->isTeamMember(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)], 'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (!empty($data['assigned_to'])) {
            abort_unless(User::role(['admin', 'customer_support'])->where('is_active', true)->whereKey($data['assigned_to'])->exists(), 422, __('Choose an active team member.'));
        }
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed']) ? ($ticket->resolved_at ?? now()) : null;
        $ticket->update($data);
        return back()->with('success', __('Ticket updated.'));
    }
    private function checkAccess(Request $request, SupportTicket $ticket): void
    {
        abort_unless($request->user()->isTeamMember() || $ticket->user_id === $request->user()->id, 404);
    }
}
