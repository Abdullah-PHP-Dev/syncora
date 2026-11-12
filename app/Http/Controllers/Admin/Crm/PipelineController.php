<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Deal;
use App\Models\Crm\Pipeline;
use App\Models\Crm\Stage;
use App\Services\Crm\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Settings: pipelines and their stages (S-02). */
class PipelineController extends Controller
{
    public function __construct(private PipelineService $pipelines) {}

    public function index()
    {
        return response()->json(['data' => $this->pipelines->all()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $pipeline = DB::transaction(function () use ($data) {
            $pipeline = Pipeline::create(['name' => $data['name'], 'sort_order' => Pipeline::max('sort_order') + 1]);
            foreach (PipelineService::DEFAULT_STAGES as $i => $stage) {
                Stage::create($stage + ['pipeline_id' => $pipeline->id, 'sort_order' => $i]);
            }

            return $pipeline;
        });

        return response()->json(['data' => $pipeline->load('stages')], 201);
    }

    /** Save a pipeline's name and its full stage list (add, rename, reorder, remove). */
    public function update(Request $request, Pipeline $pipeline)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'is_default' => ['sometimes', 'boolean'],
            'stages' => ['required', 'array', 'min:2', 'max:20'],
            'stages.*.id' => ['nullable', 'integer'], 'stages.*.name' => ['required', 'string', 'max:80'],
            'stages.*.kind' => ['required', Rule::in(Stage::KINDS)], 'stages.*.probability' => ['required', 'integer', 'min:0', 'max:100'],
            'stages.*.color' => ['nullable', 'string', 'max:20'],
        ]);
        $kinds = collect($data['stages'])->pluck('kind');
        if (! $kinds->contains('open') || $kinds->filter(fn ($k) => $k === 'won')->count() !== 1 || $kinds->filter(fn ($k) => $k === 'lost')->count() !== 1) {
            throw ValidationException::withMessages(['stages' => __('crm.settings.stage_rules')]);
        }

        DB::transaction(function () use ($pipeline, $data) {
            $pipeline->update(['name' => $data['name']]);
            if (! empty($data['is_default'])) {
                Pipeline::whereKeyNot($pipeline->id)->update(['is_default' => false]);
                $pipeline->update(['is_default' => true]);
            }
            $keep = [];
            foreach ($data['stages'] as $i => $row) {
                $stage = isset($row['id']) ? $pipeline->stages()->whereKey($row['id'])->first() : null;
                $attributes = ['name' => $row['name'], 'kind' => $row['kind'], 'probability' => $row['probability'], 'color' => $row['color'] ?? null, 'sort_order' => $i];
                $stage ? $stage->update($attributes) : $stage = Stage::create($attributes + ['pipeline_id' => $pipeline->id]);
                $keep[] = $stage->id;
            }
            $removed = $pipeline->stages()->whereNotIn('id', $keep)->pluck('id');
            if ($removed->isNotEmpty() && Deal::whereIn('stage_id', $removed)->exists()) {
                throw ValidationException::withMessages(['stages' => __('crm.settings.stage_has_deals')]);
            }
            Stage::whereIn('id', $removed)->delete();
        });

        return response()->json(['data' => $pipeline->fresh('stages')]);
    }

    public function destroy(Pipeline $pipeline)
    {
        abort_if($pipeline->is_default, 422, __('crm.settings.default_pipeline'));
        abort_if($pipeline->deals()->exists(), 422, __('crm.settings.pipeline_has_deals'));
        $pipeline->delete();

        return response()->json(['ok' => true]);
    }
}
