<?php

namespace App\Services\Crm;

use App\Models\Crm\Pipeline;
use App\Models\Crm\Stage;
use Illuminate\Support\Facades\DB;

class PipelineService
{
    /** Stages every workspace starts with (names are translated on display, editable in Settings). */
    public const DEFAULT_STAGES = [
        ['name' => 'New', 'kind' => 'open', 'probability' => 10, 'color' => '#94a3b8'],
        ['name' => 'Qualified', 'kind' => 'open', 'probability' => 30, 'color' => '#2563eb'],
        ['name' => 'Proposal sent', 'kind' => 'open', 'probability' => 60, 'color' => '#7c3aed'],
        ['name' => 'Negotiation', 'kind' => 'open', 'probability' => 80, 'color' => '#d97706'],
        ['name' => 'Won', 'kind' => 'won', 'probability' => 100, 'color' => '#16a34a'],
        ['name' => 'Lost', 'kind' => 'lost', 'probability' => 0, 'color' => '#dc2626'],
    ];

    /** The workspace's default pipeline, created with its stages on first use. */
    public function default(): Pipeline
    {
        return DB::transaction(function () {
            $pipeline = Pipeline::where('is_default', true)->lockForUpdate()->first() ?? Pipeline::orderBy('sort_order')->first();
            if ($pipeline) {
                return $pipeline;
            }
            $pipeline = Pipeline::create(['name' => 'Sales', 'is_default' => true]);
            foreach (self::DEFAULT_STAGES as $i => $stage) {
                Stage::create($stage + ['pipeline_id' => $pipeline->id, 'sort_order' => $i]);
            }

            return $pipeline;
        });
    }

    public function all()
    {
        $this->default();

        return Pipeline::with('stages')->orderByDesc('is_default')->orderBy('sort_order')->get();
    }
}
