<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Deal;
use App\Models\Crm\Pipeline;
use App\Models\Crm\Stage;
use App\Services\Crm\CrmContext;
use App\Services\Crm\DealService;
use App\Services\Crm\PipelineService;
use App\Services\Crm\Timeline;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context, private DealService $deals, private PipelineService $pipelines, private Timeline $timeline) {}

    /** Board and list data for one pipeline, with open / weighted / won-this-month totals. */
    public function index(Request $request)
    {
        $pipeline = $request->query('pipeline_id') ? Pipeline::findOrFail($request->query('pipeline_id')) : $this->pipelines->default();
        $deals = Deal::with(['contact:id,first_name,last_name', 'company:id,name', 'owner:id,name', 'stage:id,name'])
            ->where('pipeline_id', $pipeline->id)
            ->when($request->query('owner_id'), fn ($q, $v) => $q->where('owner_id', $v))
            ->when(trim((string) $request->query('q')) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($request->query('q')).'%'))
            ->orderBy('position')->orderByDesc('id')->limit(1000)->get();

        $open = $deals->where('status', 'open');

        return response()->json([
            'pipeline' => $pipeline->load('stages'),
            'deals' => $deals->map(fn ($d) => $this->dealRow($d))->values(),
            'totals' => [
                'open' => (float) $open->sum('value'),
                'weighted' => round($open->sum(fn ($d) => $d->value * $d->probability / 100), 2),
                'won_month' => (float) $deals->where('status', 'won')->filter(fn ($d) => $d->won_at?->isCurrentMonth())->sum('value'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $stage = Stage::where('pipeline_id', $data['pipeline_id'])->findOrFail($data['stage_id']);
        $deal = Deal::create($data + [
            'probability' => $stage->probability, 'status' => $stage->kind, 'stage_changed_at' => now(),
            'owner_id' => $data['owner_id'] ?? $this->context->actorId(), 'created_by' => $this->context->actorId(),
            'currency' => $data['currency'] ?? 'SAR',
        ]);
        $this->timeline->event('deal_created', ['deal_id' => $deal->id, 'contact_id' => $deal->contact_id, 'company_id' => $deal->company_id], ['name' => $deal->name, 'value' => (float) $deal->value]);

        return response()->json(['data' => $this->dealRow($deal->load('contact', 'company', 'owner', 'stage'))], 201);
    }

    public function show(Deal $deal)
    {
        $deal->load(['contact', 'company', 'owner', 'stage', 'pipeline.stages']);

        return response()->json([
            'deal' => $this->dealRow($deal),
            'timeline' => $deal->activities()->with(['author:id,name', 'owner:id,name'])->latest()->limit(50)->get()->map(fn ($a) => $this->activityRow($a)),
        ]);
    }

    public function update(Request $request, Deal $deal)
    {
        $deal->update(collect($this->validated($request, $deal))->except(['pipeline_id', 'stage_id'])->all());

        return response()->json(['data' => $this->dealRow($deal->load('contact', 'company', 'owner', 'stage'))]);
    }

    public function move(Request $request, Deal $deal)
    {
        $data = $request->validate(['stage_id' => ['required', 'integer'], 'position' => ['nullable', 'integer', 'min:0'], 'lost_reason' => ['nullable', 'string', 'max:160']]);
        $stage = Stage::where('pipeline_id', $deal->pipeline_id)->findOrFail($data['stage_id']);
        abort_if($stage->kind === 'lost' && empty($data['lost_reason']) && \App\Services\Crm\CrmSettings::for($this->context->workspaceId())['require_lost_reason'], 422, __('crm.deals.lost_reason_required'));

        return response()->json(['data' => $this->dealRow($this->deals->move($deal, $stage, $data['position'] ?? null, $data['lost_reason'] ?? null))]);
    }

    public function destroy(Deal $deal)
    {
        $deal->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Deal $deal = null): array
    {
        $ws = $this->context->workspaceId();
        $req = $deal ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:160'], 'value' => [$req, 'numeric', 'min:0', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pipeline_id' => [$req, Rule::exists('crm_pipelines', 'id')->where('workspace_id', $ws)],
            'stage_id' => [$req, 'integer'],
            'contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('workspace_id', $ws)->whereNull('deleted_at')],
            'company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('workspace_id', $ws)->whereNull('deleted_at')],
            'owner_id' => ['nullable', Rule::in($this->context->memberIds())],
            'expected_close_date' => ['nullable', 'date'], 'source' => ['nullable', 'string', 'max:40'],
        ]);
    }
}
