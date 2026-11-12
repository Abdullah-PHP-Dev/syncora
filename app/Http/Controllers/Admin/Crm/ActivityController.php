<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Services\Crm\CrmContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context) {}

    /** Tasks (open/overdue/today/upcoming/done), the activity log, a date range for the calendar, or team stats. */
    public function index(Request $request)
    {
        $view = $request->query('view', 'open');
        $query = Activity::with(['owner:id,name', 'author:id,name', 'contact:id,first_name,last_name', 'deal:id,name'])
            ->when($request->query('owner_id') === 'me', fn ($q) => $q->where('owner_id', $this->context->actorId()))
            ->when(is_numeric($request->query('owner_id')), fn ($q) => $q->where('owner_id', $request->query('owner_id')));

        match ($view) {
            'log' => $query->whereIn('type', ['note', 'call', 'meeting', 'email'])->latest(),
            'done' => $query->where('type', 'task')->whereNotNull('completed_at')->latest('completed_at'),
            'calendar' => $query->whereIn('type', ['task', 'meeting', 'call'])->whereBetween('due_at', [
                $request->date('from') ?? now()->startOfMonth(), $request->date('to') ?? now()->endOfMonth(),
            ])->orderBy('due_at'),
            'overdue' => $query->where('type', 'task')->whereNull('completed_at')->where('due_at', '<', now())->orderBy('due_at'),
            'today' => $query->where('type', 'task')->whereNull('completed_at')->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->orderBy('due_at'),
            default => $query->where('type', 'task')->whereNull('completed_at')->orderByRaw('due_at is null')->orderBy('due_at'),
        };

        $openTasks = Activity::where('type', 'task')->whereNull('completed_at');

        return response()->json([
            'data' => $query->limit(300)->get()->map(fn ($a) => $this->activityRow($a)),
            'counts' => [
                'open' => (clone $openTasks)->count(),
                'overdue' => (clone $openTasks)->where('due_at', '<', now())->count(),
                'today' => (clone $openTasks)->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            ],
        ]);
    }

    /** Manager view: activity per team member this week. */
    public function team()
    {
        $since = now()->startOfWeek();
        $rows = Activity::where('created_at', '>=', $since)->whereNotNull('created_by')
            ->selectRaw("created_by, SUM(CASE WHEN type = 'call' THEN 1 ELSE 0 END) calls, SUM(CASE WHEN type = 'meeting' THEN 1 ELSE 0 END) meetings,
                SUM(CASE WHEN type = 'note' THEN 1 ELSE 0 END) notes, SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) emails,
                SUM(CASE WHEN type = 'event' AND subject = 'deal_won' THEN 1 ELSE 0 END) won, MAX(created_at) last_active")
            ->groupBy('created_by')->get()->keyBy('created_by');

        $tasks = Activity::where('type', 'task')->where('due_at', '>=', $since)->where('due_at', '<=', now())
            ->selectRaw('owner_id, COUNT(*) total, SUM(CASE WHEN completed_at IS NOT NULL AND completed_at <= due_at THEN 1 ELSE 0 END) on_time')
            ->groupBy('owner_id')->get()->keyBy('owner_id');

        return response()->json(['data' => collect($this->context->members())->map(fn ($m) => [
            'member' => $m,
            'calls' => (int) ($rows[$m['id']]->calls ?? 0), 'meetings' => (int) ($rows[$m['id']]->meetings ?? 0),
            'notes' => (int) ($rows[$m['id']]->notes ?? 0), 'emails' => (int) ($rows[$m['id']]->emails ?? 0),
            'won' => (int) ($rows[$m['id']]->won ?? 0), 'last_active' => $rows[$m['id']]->last_active ?? null,
            'on_time' => ($tasks[$m['id']]->total ?? 0) ? (int) round($tasks[$m['id']]->on_time / $tasks[$m['id']]->total * 100) : null,
        ])]);
    }

    public function store(Request $request)
    {
        $ws = $this->context->workspaceId();
        $data = $request->validate([
            'type' => ['required', Rule::in(['task', 'call', 'meeting', 'note', 'email'])],
            'subject' => ['required', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('workspace_id', $ws)],
            'deal_id' => ['nullable', Rule::exists('crm_deals', 'id')->where('workspace_id', $ws)],
            'lead_id' => ['nullable', Rule::exists('crm_leads', 'id')->where('workspace_id', $ws)],
            'company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('workspace_id', $ws)],
        ]);
        $activity = Activity::create($data + [
            'owner_id' => $data['owner_id'] ?? $this->context->actorId(), 'created_by' => $this->context->actorId(),
            'completed_at' => $data['type'] === 'task' ? null : now(),
        ]);

        return response()->json(['data' => $this->activityRow($activity->load('owner', 'author', 'contact', 'deal'))], 201);
    }

    public function update(Request $request, Activity $activity)
    {
        abort_if($activity->type === 'event', 422);
        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'completed' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('completed', $data)) {
            $data['completed_at'] = $data['completed'] ? now() : null;
            unset($data['completed']);
        }
        $activity->update($data);
        if ($activity->contact_id) {
            $activity->contact()->update(['last_activity_at' => now()]);
        }

        return response()->json(['data' => $this->activityRow($activity->load('owner', 'author', 'contact', 'deal'))]);
    }

    public function destroy(Activity $activity)
    {
        abort_if($activity->type === 'event', 422); // History entries are the audit trail.
        $activity->delete();

        return response()->json(['ok' => true]);
    }
}
