<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use Illuminate\Support\Carbon;

/**
 * AI-01 insights: numbers about the current workspace (runs inside the
 * workspace scope) and plain-language findings built from them without AI.
 * The same facts are what "Ask CRM AI" sends to the model, so answers are
 * grounded in this data only.
 */
class CrmInsights
{
    public function facts(int $workspaceId): array
    {
        $since = now()->subDays(30);
        $settings = CrmSettings::for($workspaceId);
        $bands = LeadScorer::config($workspaceId)['bands'];
        $leads = Lead::where('created_at', '>=', $since)->get(['id', 'contact_id', 'source', 'status', 'score', 'owner_id', 'created_at', 'deal_id']);
        $firsts = \App\Http\Controllers\Admin\Crm\LeadController::firstResponses($leads);
        $bySource = $leads->groupBy(fn ($l) => $l->source ?: 'unknown')->map(fn ($g) => [
            'leads' => $g->count(), 'converted' => $g->where('status', 'converted')->count(),
            'conversion_rate' => round($g->where('status', 'converted')->count() / max(1, $g->count()) * 100),
        ])->sortByDesc('leads')->take(8);
        $open = Deal::with('stage:id,name,sort_order')->where('status', 'open')->get(['id', 'name', 'value', 'currency', 'stage_id', 'stage_changed_at', 'created_at', 'owner_id']);
        $idle = $open->filter(fn ($d) => ($d->stage_changed_at ?? $d->created_at)->lt(now()->subDays($settings['idle_days'])));
        $closed = Deal::whereIn('status', ['won', 'lost'])->where(fn ($q) => $q->where('won_at', '>=', $since)->orWhere('lost_at', '>=', $since))->get(['status', 'value', 'lost_reason', 'created_at', 'won_at']);
        $won = $closed->where('status', 'won');

        return [
            'period' => 'last 30 days', 'currency' => $open->first()?->currency ?? 'SAR',
            'leads' => [
                'total' => $leads->count(), 'unassigned_open' => Lead::whereNull('owner_id')->whereNotIn('status', ['converted', 'unqualified'])->count(),
                'hot_open' => Lead::where('score', '>=', $bands['hot'])->whereNotIn('status', ['converted', 'unqualified'])->count(),
                'hot_without_reply' => $leads->filter(fn ($l) => $l->score >= $bands['hot'] && ! in_array($l->status, ['converted', 'unqualified'], true) && $firsts[$l->id] === null)->count(),
                'avg_first_response_minutes' => ($f = $firsts->filter(fn ($v) => $v !== null))->count() ? (int) round($f->avg()) : null,
                'by_source' => $bySource->all(),
            ],
            'pipeline' => [
                'open_deals' => $open->count(), 'open_value' => round($open->sum('value')),
                'by_stage' => $open->groupBy(fn ($d) => $d->stage?->name ?? '-')->map(fn ($g) => ['deals' => $g->count(), 'value' => round($g->sum('value'))])->all(),
                'idle_deals' => $idle->count(), 'idle_value' => round($idle->sum('value')), 'idle_days_rule' => $settings['idle_days'],
                'top_idle' => $idle->sortByDesc('value')->take(5)->map(fn ($d) => ['name' => $d->name, 'value' => round($d->value), 'days' => (int) ($d->stage_changed_at ?? $d->created_at)->diffInDays(now())])->values()->all(),
            ],
            'results' => [
                'won' => $won->count(), 'won_value' => round($won->sum('value')), 'lost' => $closed->where('status', 'lost')->count(),
                'win_rate' => $closed->count() ? round($won->count() / $closed->count() * 100) : null,
                'avg_cycle_days' => $won->count() ? (int) round($won->avg(fn ($d) => Carbon::parse($d->created_at)->diffInDays($d->won_at))) : null,
                'top_lost_reasons' => $closed->where('status', 'lost')->countBy(fn ($d) => $d->lost_reason ?: 'not given')->sortDesc()->take(5)->all(),
            ],
            'tasks' => [
                'overdue' => Activity::where('type', 'task')->whereNull('completed_at')->where('due_at', '<', now())->count(),
                'due_today' => Activity::where('type', 'task')->whereNull('completed_at')->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            ],
        ];
    }

    /** Findings worth acting on, most urgent first: [{tone, key, params, url}]. */
    public function cards(array $f): array
    {
        $cards = [];
        if ($f['leads']['hot_without_reply'] > 0) {
            $cards[] = ['tone' => 'danger', 'key' => 'hot_no_reply', 'params' => ['n' => $f['leads']['hot_without_reply']], 'url' => route('admin.crm.leads.index')];
        }
        if ($f['leads']['unassigned_open'] > 0) {
            $cards[] = ['tone' => 'warn', 'key' => 'unassigned', 'params' => ['n' => $f['leads']['unassigned_open']], 'url' => route('admin.crm.leads.index')];
        }
        if ($f['pipeline']['idle_deals'] > 0) {
            $cards[] = ['tone' => 'warn', 'key' => 'idle_deals', 'params' => ['n' => $f['pipeline']['idle_deals'], 'value' => $f['currency'].' '.number_format($f['pipeline']['idle_value']), 'days' => $f['pipeline']['idle_days_rule']], 'url' => route('admin.crm.deals.index')];
        }
        if ($f['tasks']['overdue'] > 0) {
            $cards[] = ['tone' => 'danger', 'key' => 'overdue', 'params' => ['n' => $f['tasks']['overdue']], 'url' => route('admin.crm.activities.index')];
        }
        if (($m = $f['leads']['avg_first_response_minutes']) !== null && $m > 60) {
            $cards[] = ['tone' => 'warn', 'key' => 'slow_reply', 'params' => ['n' => $m], 'url' => route('admin.crm.leads.index')];
        }
        $sources = collect($f['leads']['by_source'])->filter(fn ($s) => $s['leads'] >= 5);
        if ($sources->count() >= 2) {
            $best = $sources->sortByDesc('conversion_rate')->keys()->first();
            $cards[] = ['tone' => 'success', 'key' => 'best_source', 'params' => ['source' => $best, 'rate' => $sources[$best]['conversion_rate']], 'url' => route('admin.crm.reports.index')];
        }
        if (($reason = array_key_first($f['results']['top_lost_reasons'])) && $f['results']['lost'] >= 3) {
            $cards[] = ['tone' => 'info', 'key' => 'lost_reason', 'params' => ['reason' => $reason, 'n' => $f['results']['top_lost_reasons'][$reason]], 'url' => route('admin.crm.reports.index')];
        }

        return $cards;
    }
}
