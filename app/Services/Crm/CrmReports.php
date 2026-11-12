<?php

namespace App\Services\Crm;

use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Pipeline;
use App\Services\AdServices\AdsPerformanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * R-01 Sales performance and R-02 Lead sources & ROI. Everything comes from
 * CRM records; ad spend comes from the Ads dashboard's live platform data,
 * never estimated (a platform without reporting shows no spend).
 */
class CrmReports
{
    public function __construct(private CrmContext $context) {}

    public function sales(int $months, ?int $pipelineId = null): array
    {
        $from = now()->startOfMonth()->subMonthsNoOverflow($months - 1);
        $prevFrom = $from->copy()->subMonthsNoOverflow($months);
        $won = fn (Carbon $a, ?Carbon $b = null) => Deal::where('status', 'won')->where('won_at', '>=', $a)->when($b, fn ($q) => $q->where('won_at', '<', $b));

        $current = $won($from)->get(['id', 'value', 'won_at', 'created_at', 'owner_id']);
        $previous = $won($prevFrom, $from)->get(['id', 'value', 'won_at', 'created_at']);
        $cycle = fn ($deals) => $deals->count() ? round($deals->avg(fn ($d) => $d->created_at->diffInDays($d->won_at))) : null;
        $trend = fn ($now, $before) => $before ? round(($now - $before) / $before * 100, 1) : null;

        $revenue = (float) $current->sum('value');
        $avg = $current->count() ? $revenue / $current->count() : 0;
        $prevAvg = $previous->count() ? $previous->sum('value') / $previous->count() : 0;

        $byMonth = collect(range($months - 1, 0))->map(function ($i) use ($current) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($i);
            $end = $start->copy()->addMonthNoOverflow();

            return ['label' => $start->translatedFormat('M'), 'won' => (float) $current->filter(fn ($d) => $d->won_at >= $start && $d->won_at < $end)->sum('value')];
        });

        // Pipeline by stage: open deals now, and how many deals that entered the period reached each stage.
        $pipeline = $pipelineId ? Pipeline::with('stages')->find($pipelineId) : app(PipelineService::class)->default()->load('stages');
        $periodDeals = Deal::where('pipeline_id', $pipeline->id)->where('created_at', '>=', $from)->with('stage:id,sort_order,kind')->get(['id', 'stage_id', 'status']);
        $order = $pipeline->stages->pluck('sort_order', 'id');
        $reached = fn ($stage) => $periodDeals->filter(fn ($d) => $d->status === 'won' || ($d->status === 'open' && ($order[$d->stage_id] ?? -1) >= $stage->sort_order))->count();
        $openStages = $pipeline->stages->where('kind', 'open')->values();
        $open = Deal::where('pipeline_id', $pipeline->id)->where('status', 'open')->selectRaw('stage_id, count(*) n, sum(value) v')->groupBy('stage_id')->get()->keyBy('stage_id');
        $stages = $openStages->map(function ($stage, $i) use ($open, $reached, $openStages, $periodDeals) {
            $here = $reached($stage);
            $next = $openStages->get($i + 1);
            $onward = $next ? $reached($next) : $periodDeals->where('status', 'won')->count();

            return ['id' => $stage->id, 'name' => $stage->name, 'color' => $stage->color, 'deals' => (int) ($open[$stage->id]->n ?? 0),
                'value' => (float) ($open[$stage->id]->v ?? 0), 'conversion' => $here ? round($onward / $here * 100) : null];
        })->values();

        $closed = Deal::whereIn('status', ['won', 'lost'])->where(fn ($q) => $q->where('won_at', '>=', $from)->orWhere('lost_at', '>=', $from))->get(['owner_id', 'status', 'value', 'created_at', 'won_at']);
        $owners = collect($this->context->members())->map(function ($m) use ($closed) {
            $mine = $closed->where('owner_id', $m['id']);
            $wonDeals = $mine->where('status', 'won');

            return ['member' => $m, 'won' => $wonDeals->count(), 'revenue' => (float) $wonDeals->sum('value'),
                'win_rate' => $mine->count() ? round($wonDeals->count() / $mine->count() * 100) : null,
                'cycle' => $wonDeals->count() ? round($wonDeals->avg(fn ($d) => $d->created_at->diffInDays($d->won_at))) : null];
        })->filter(fn ($r) => $r['won'] || $r['win_rate'] !== null)->sortByDesc('revenue')->values();

        $lost = Deal::where('status', 'lost')->where('lost_at', '>=', $from)
            ->selectRaw("COALESCE(NULLIF(lost_reason, ''), '-') reason, count(*) n")->groupBy('reason')->orderByDesc('n')->limit(6)->pluck('n', 'reason');

        return [
            'kpis' => [
                'revenue' => ['value' => $revenue, 'trend' => $trend($revenue, (float) $previous->sum('value'))],
                'won' => ['value' => $current->count(), 'trend' => $trend($current->count(), $previous->count())],
                'avg_deal' => ['value' => round($avg, 2), 'trend' => $trend($avg, $prevAvg)],
                'cycle' => ['value' => $cycle($current), 'previous' => $cycle($previous)],
            ],
            'by_month' => $byMonth, 'pipeline' => ['id' => $pipeline->id, 'name' => $pipeline->name], 'stages' => $stages,
            'owners' => $owners, 'lost_reasons' => $lost, 'lost_total' => (int) $lost->sum(),
        ];
    }

    public function sources(int $days): array
    {
        $since = now()->subDays($days);
        $leads = Lead::with(['deal:id,status,value', 'contact:id,lifecycle'])->where('created_at', '>=', $since)
            ->get(['id', 'source', 'campaign', 'status', 'deal_id', 'contact_id']);

        $summarise = fn ($group) => [
            'leads' => $group->count(),
            'qualified' => $group->count() ? round($group->whereIn('status', ['qualified', 'converted'])->count() / $group->count() * 100) : 0,
            'won' => $group->filter(fn ($l) => $l->deal?->status === 'won')->count(),
            'revenue' => (float) $group->filter(fn ($l) => $l->deal?->status === 'won')->sum(fn ($l) => $l->deal->value),
        ];

        return [
            'by_source' => $leads->groupBy(fn ($l) => $l->source ?: 'other')->map($summarise)->sortByDesc('leads'),
            'by_campaign' => $leads->filter(fn ($l) => $l->campaign)->groupBy('campaign')->map(fn ($g, $name) => ['campaign' => $name, 'source' => $g->first()->source] + $summarise($g))->sortByDesc('revenue')->values(),
            'totals' => $summarise($leads),
        ];
    }

    /** Real spend per ad platform for the period (Ads dashboard data), joined to CRM revenue by lead source. */
    public function spend(int $days, array $bySource): array
    {
        $platforms = (new AdsPerformanceService((int) Auth::id()))->build($days)['platforms'] ?? [];
        // Lead sources that belong to each ad platform.
        $map = ['google' => ['google_ads', 'youtube'], 'tiktok' => ['tiktok'], 'snapchat' => ['snapchat'], 'x' => ['x'], 'openai' => ['chatgpt']];
        $rows = [];
        foreach ($map as $platform => $sources) {
            $state = $platforms[$platform] ?? ['status' => 'not_connected'];
            $spend = ($state['status'] ?? null) === 'ok' ? (float) ($state['totals']['current']['spend'] ?? 0) : null;
            $revenue = collect($sources)->sum(fn ($s) => $bySource[$s]['revenue'] ?? 0);
            $leads = collect($sources)->sum(fn ($s) => $bySource[$s]['leads'] ?? 0);
            if ($spend === null && ! $leads) {
                continue;
            }
            $rows[] = ['platform' => $platform, 'status' => $state['status'] ?? 'not_connected', 'spend' => $spend, 'leads' => $leads, 'revenue' => $revenue,
                'cost_per_lead' => $spend !== null && $leads ? round($spend / $leads, 2) : null, 'roas' => $spend ? round($revenue / $spend, 1) : null];
        }

        return $rows;
    }
}
