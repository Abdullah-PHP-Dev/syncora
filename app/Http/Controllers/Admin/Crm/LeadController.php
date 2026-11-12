<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Services\Crm\CrmContext;
use App\Services\Crm\LeadService;
use App\Services\Crm\Timeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context, private LeadService $leads, private Timeline $timeline) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');
        $leads = Lead::with(['contact.owner:id,name', 'contact.company:id,name', 'owner:id,name'])
            ->when(in_array($status, Lead::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when(! $status, fn ($query) => $query->where('status', '!=', 'converted'))
            ->when($request->query('owner_id'), fn ($query, $v) => $v === 'none' ? $query->whereNull('owner_id') : $query->where('owner_id', $v))
            ->when($request->query('source'), fn ($query, $v) => $query->where('source', $v))
            ->when($request->query('campaign'), fn ($query, $v) => $query->where('campaign', $v))
            ->when($request->query('band'), function ($query, $band) {
                $bands = \App\Services\Crm\LeadScorer::config($this->context->workspaceId())['bands'];
                match ($band) { 'hot' => $query->where('score', '>=', $bands['hot']), 'warm' => $query->whereBetween('score', [$bands['warm'], $bands['hot'] - 1]), default => $query->where('score', '<', $bands['warm']) };
            })
            ->when($q !== '', fn ($query) => $query->whereHas('contact', fn ($c) => $c->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                // A query without enough digits must not match every phone ('%%').
                ->when(\App\Services\Crm\ContactMatcher::searchDigits($q), fn ($x, $digits) => $x->orWhere('phone_key', 'like', "%{$digits}%"))))
            ->latest()->paginate(25);

        $counts = Lead::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $firstResponse = self::firstResponses($leads->getCollection());
        $recent = Lead::where('created_at', '>=', now()->subDays(30))->get(['id', 'contact_id', 'status', 'created_at']);
        $todayResponse = self::firstResponses(Lead::where('created_at', '>=', now()->subDays(7))->latest()->limit(200)->get(['id', 'contact_id', 'created_at']))->filter(fn ($v) => $v !== null);

        return response()->json([
            'data' => $leads->getCollection()->map(fn ($l) => $this->leadRow($l) + ['first_response_minutes' => $firstResponse[$l->id] ?? null]),
            'kpis' => [
                'today' => Lead::where('created_at', '>=', now()->startOfDay())->count(),
                'yesterday' => Lead::whereBetween('created_at', [now()->subDay()->startOfDay(), now()->startOfDay()])->count(),
                'avg_first_response' => $todayResponse->count() ? (int) round($todayResponse->avg()) : null,
                'unassigned' => Lead::whereNull('owner_id')->whereNotIn('status', ['converted', 'unqualified'])->count(),
                'qualified_rate' => $recent->count() ? (int) round($recent->whereIn('status', ['qualified', 'converted'])->count() / $recent->count() * 100) : null,
            ],
            'meta' => ['total' => $leads->total(), 'page' => $leads->currentPage(), 'last_page' => $leads->lastPage()],
            'counts' => ['all' => $counts->except('converted')->sum()] + collect(Lead::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('workspace_id', $this->context->workspaceId())->whereNull('deleted_at')],
            'name' => ['required_without:contact_id', 'nullable', 'string', 'max:160'],
            'phone' => ['required_without_all:contact_id,email', 'nullable', 'string', 'max:40', 'regex:/^[+\d\s().-]{7,}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'source' => ['nullable', 'string', 'max:40'], 'campaign' => ['nullable', 'string', 'max:160'],
            'interest' => ['nullable', 'string', 'max:2000'], 'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'follow_up' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['data' => $this->leadRow($this->leads->create($data)->load('contact', 'owner'))], 201);
    }

    /** L-02 lead profile. */
    public function show(Lead $lead)
    {
        $lead->load(['contact.owner:id,name', 'contact.company:id,name', 'owner:id,name', 'deal.stage']);
        $contact = $lead->contact;
        $conversation = \App\Models\Messaging\Conversation::where('crm_contact_id', $contact->id)
            ->when($lead->conversation_id, fn ($q) => $q->orderByRaw('id = ? desc', [$lead->conversation_id]))->latest('last_message_at')->first();
        $config = \App\Services\Crm\LeadScorer::config($this->context->workspaceId());
        $settings = \App\Services\Crm\CrmSettings::for($this->context->workspaceId());

        return response()->json([
            'lead' => $this->leadRow($lead) + [
                'qualification' => array_replace(array_fill_keys(self::CRITERIA, ['met' => false, 'note' => null, 'by' => null]), $lead->qualification ?? []),
                'score_breakdown' => $lead->score_breakdown ?? [], 'band' => \App\Services\Crm\LeadScorer::band((int) $lead->score, $config),
                'meta' => $lead->meta ?? [], 'external_lead_id' => $lead->external_lead_id, 'converted_at' => $lead->converted_at?->toIso8601String(),
                'first_response_minutes' => self::firstResponses(collect([$lead]))[$lead->id] ?? null,
                'deal' => $lead->deal ? $this->dealRow($lead->deal) : null,
            ],
            'statuses' => array_values(array_merge(['new', 'contacted'], $settings['extra_statuses'], ['qualified', 'converted'])),
            'conversation' => $conversation ? [
                'id' => $conversation->id, 'platform' => $conversation->platform, 'url' => route('admin.chats.dashboard', ['conversation' => $conversation->id]),
                'messages' => \App\Models\Messaging\Message::where('conversation_id', $conversation->id)->latest('id')->limit(20)->get(['id', 'direction', 'body', 'created_at'])->reverse()->values(),
            ] : null,
            'tasks' => \App\Models\Crm\Activity::with('owner:id,name')->where('type', 'task')->whereNull('completed_at')
                ->where(fn ($q) => $q->where('lead_id', $lead->id)->orWhere('contact_id', $contact->id))->orderBy('due_at')->limit(10)->get()->map(fn ($a) => $this->activityRow($a)),
            'timeline' => $lead->activities()->with(['author:id,name', 'owner:id,name'])->latest()->limit(50)->get()->map(fn ($a) => $this->activityRow($a)),
        ]);
    }

    public const CRITERIA = ['need', 'budget', 'timing', 'decision_maker', 'delivery_area'];

    /** Minutes from lead creation to the team's first reply (Inbox outbound message or a logged call/email/meeting). */
    public static function firstResponses(\Illuminate\Support\Collection $leads): \Illuminate\Support\Collection
    {
        if ($leads->isEmpty()) {
            return collect();
        }
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')->whereIn('crm_contact_id', $leads->pluck('contact_id')->unique())->get(['id', 'crm_contact_id']);
        $outbound = $conversations->isEmpty() ? collect() : \App\Models\Messaging\Message::whereIn('conversation_id', $conversations->pluck('id'))->where('direction', 'outbound')
            ->where('created_at', '>=', $leads->min('created_at'))->get(['conversation_id', 'created_at']);
        $activities = \App\Models\Crm\Activity::withoutGlobalScope('visibility')->whereIn('contact_id', $leads->pluck('contact_id'))->whereIn('type', ['call', 'email', 'meeting'])
            ->where('created_at', '>=', $leads->min('created_at'))->get(['contact_id', 'created_at']);

        return $leads->mapWithKeys(function ($lead) use ($conversations, $outbound, $activities) {
            $convIds = $conversations->where('crm_contact_id', $lead->contact_id)->pluck('id');
            $first = collect([
                $outbound->whereIn('conversation_id', $convIds)->where('created_at', '>=', $lead->created_at)->min('created_at'),
                $activities->where('contact_id', $lead->contact_id)->where('created_at', '>=', $lead->created_at)->min('created_at'),
            ])->filter()->min();

            return [$lead->id => $first ? (int) $lead->created_at->diffInMinutes(\Illuminate\Support\Carbon::parse($first)) : null];
        });
    }

    public function update(Request $request, Lead $lead)
    {
        $statuses = array_merge(['new', 'contacted', 'qualified'], \App\Services\Crm\CrmSettings::for($this->context->workspaceId())['extra_statuses']);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in($statuses)],
            'qualification' => ['sometimes', 'array'],
            'qualification.*.met' => ['boolean'], 'qualification.*.note' => ['nullable', 'string', 'max:300'],
            'campaign' => ['nullable', 'string', 'max:160'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'interest' => ['nullable', 'string', 'max:2000'], 'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'], 'source' => ['nullable', 'string', 'max:40'],
        ]);
        if (isset($data['qualification'])) {
            // Manual edits are marked as such; AI-filled answers keep their "ai" marker until someone changes them.
            $data['qualification'] = collect(array_intersect_key($data['qualification'], array_flip(self::CRITERIA)))
                ->map(fn ($v, $k) => ['met' => (bool) ($v['met'] ?? false), 'note' => $v['note'] ?? null, 'by' => ($lead->qualification[$k]['note'] ?? null) === ($v['note'] ?? null) && ($lead->qualification[$k]['met'] ?? null) === (bool) ($v['met'] ?? false) ? ($lead->qualification[$k]['by'] ?? 'manual') : 'manual'])
                ->all() + ($lead->qualification ?? []);
        }
        $old = $lead->status;
        $lead->update($data);
        if ($lead->wasChanged('status')) {
            $this->timeline->event('lead_status_changed', ['contact_id' => $lead->contact_id, 'lead_id' => $lead->id], ['from' => $old, 'to' => $lead->status]);
        }

        return response()->json(['data' => $this->leadRow($lead->load('contact', 'owner'))]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'owner_id' => ['required', Rule::in($this->context->memberIds())]]);

        return response()->json(['updated' => $this->leads->assign($data['ids'], (int) $data['owner_id'])]);
    }

    public function convert(Request $request, Lead $lead)
    {
        $pipelineIds = Pipeline::pluck('id')->all();
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:160'],
            'create_deal' => ['sometimes', 'boolean'],
            'deal_name' => ['required_if:create_deal,true', 'nullable', 'string', 'max:160'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'], 'currency' => ['nullable', 'string', 'size:3'],
            'pipeline_id' => ['nullable', Rule::in($pipelineIds)], 'stage_id' => ['required_if:create_deal,true', 'nullable', 'integer'],
            'expected_close_date' => ['nullable', 'date'], 'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'task_subject' => ['nullable', 'string', 'max:200'], 'task_due_at' => ['nullable', 'date'],
        ]);

        return response()->json(['data' => $this->leadRow($this->leads->convert($lead, $data)->load('owner'))]);
    }

    public function disqualify(Request $request, Lead $lead)
    {
        $data = $request->validate(['reason' => ['required', Rule::in(\App\Services\Crm\CrmSettings::for($this->context->workspaceId())['disqualify_reasons'])], 'note' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $this->leadRow($this->leads->disqualify($lead, $data['reason'], $data['note'] ?? null)->load('contact', 'owner'))]);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(['ok' => true]);
    }
}
