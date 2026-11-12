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
            ->when($q !== '', fn ($query) => $query->whereHas('contact', fn ($c) => $c->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")->orWhere('phone_key', 'like', '%'.preg_replace('/\D+/', '', $q).'%')))
            ->latest()->paginate(25);

        $counts = Lead::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'data' => $leads->getCollection()->map(fn ($l) => $this->leadRow($l)),
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

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['new', 'contacted', 'qualified'])],
            'interest' => ['nullable', 'string', 'max:2000'], 'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'], 'source' => ['nullable', 'string', 'max:40'],
        ]);
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
        $data = $request->validate(['reason' => ['required', Rule::in(Lead::DISQUALIFY_REASONS)], 'note' => ['nullable', 'string', 'max:2000']]);

        return response()->json(['data' => $this->leadRow($this->leads->disqualify($lead, $data['reason'], $data['note'] ?? null)->load('contact', 'owner'))]);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(['ok' => true]);
    }
}
