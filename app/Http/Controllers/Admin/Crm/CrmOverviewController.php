<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Services\Crm\CrmContext;
use Illuminate\Http\Request;

/** D-01 dashboard data and the CRM ⌘K search. All counts are this workspace's. */
class CrmOverviewController extends Controller
{
    use Presents;

    public function __construct(private CrmContext $context) {}

    public function overview(Request $request)
    {
        $days = in_array($request->integer('days'), [7, 30, 90, 365], true) ? $request->integer('days') : 30;
        $since = now()->subDays($days);
        $prev = now()->subDays($days * 2);
        $mine = $request->query('scope') === 'mine' ? $this->context->actorId() : null;
        $own = fn ($q) => $mine ? $q->where('owner_id', $mine) : $q;

        $count = fn ($model, $from, $to = null) => $own($model::query())->where('created_at', '>=', $from)->when($to, fn ($q) => $q->where('created_at', '<', $to))->count();
        $trend = fn ($now, $before) => $before ? round(($now - $before) / $before * 100, 1) : null;

        $contactsNow = $count(Contact::class, $since);
        $leadsNow = $count(Lead::class, $since);
        $openDeals = $own(Deal::query())->where('status', 'open');
        $wonMonth = $own(Deal::query())->where('status', 'won')->where('won_at', '>=', now()->startOfMonth())->sum('value');

        // Funnel over leads created in the period.
        $leads = $own(Lead::query())->where('created_at', '>=', $since);
        $funnelLeads = (clone $leads)->count();
        $qualified = (clone $leads)->whereIn('status', ['qualified', 'converted'])->count();
        $withDeal = (clone $leads)->whereNotNull('deal_id')->count();
        $won = $own(Deal::query())->where('status', 'won')->whereNotNull('lead_id')->whereHas('lead', fn ($l) => $l->where('created_at', '>=', $since))->count();

        // Won revenue by month (last 6), as [start, end) ranges: portable and index-friendly.
        $months = collect(range(5, 0))->map(function ($i) use ($own) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($i);

            return [
                'label' => $start->translatedFormat('M'),
                'won' => (float) $own(Deal::query())->where('status', 'won')->where('won_at', '>=', $start)->where('won_at', '<', $start->copy()->addMonthNoOverflow())->sum('value'),
                'added' => (float) $own(Deal::query())->where('created_at', '>=', $start)->where('created_at', '<', $start->copy()->addMonthNoOverflow())->sum('value'),
            ];
        });

        $tasks = Activity::with(['contact:id,first_name,last_name', 'deal:id,name'])->where('type', 'task')->whereNull('completed_at')
            ->where('owner_id', $this->context->actorId())->orderByRaw('due_at is null')->orderBy('due_at')->limit(5)->get();

        return response()->json([
            'kpis' => [
                'contacts' => ['total' => $own(Contact::query())->count(), 'new' => $contactsNow, 'trend' => $trend($contactsNow, $count(Contact::class, $prev, $since))],
                'leads' => ['new' => $leadsNow, 'trend' => $trend($leadsNow, $count(Lead::class, $prev, $since))],
                'deals' => ['open' => (clone $openDeals)->count(), 'value' => (float) (clone $openDeals)->sum('value')],
                'won' => ['value' => (float) $wonMonth, 'conversion' => $funnelLeads ? round($won / $funnelLeads * 100, 1) : null],
            ],
            'performance' => $months,
            'funnel' => ['leads' => $funnelLeads, 'qualified' => $qualified, 'deals' => $withDeal, 'won' => $won],
            'sources' => $own(Lead::query())->where('created_at', '>=', $since)->selectRaw("COALESCE(source, 'other') source, count(*) total")
                ->groupBy('source')->orderByDesc('total')->limit(6)->pluck('total', 'source'),
            'tasks' => $tasks->map(fn ($a) => $this->activityRow($a)),
            'recent' => Activity::with(['author:id,name', 'contact:id,first_name,last_name', 'deal:id,name'])->latest()->limit(8)->get()->map(fn ($a) => $this->activityRow($a)),
            'attention' => [
                'uncontacted_leads' => $own(Lead::query())->where('status', 'new')->where('created_at', '<', now()->subDay())->count(),
                'idle_deals' => $own(Deal::query())->where('status', 'open')->where('stage_changed_at', '<', now()->subDays(7))->count(),
                'overdue_tasks' => Activity::where('type', 'task')->whereNull('completed_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['contacts' => [], 'companies' => [], 'deals' => []]);
        }
        $digits = preg_replace('/\D+/', '', $q);

        return response()->json([
            'contacts' => Contact::where(fn ($w) => $w->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('arabic_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
                ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('phone_key', 'like', "%{$digits}%")))
                ->limit(6)->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name, 'sub' => $c->phone ?: $c->email, 'url' => route('admin.crm.contacts.show', $c)]),
            'companies' => Company::where('name', 'like', "%{$q}%")->limit(4)->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'url' => route('admin.crm.companies.show', $c)]),
            'deals' => Deal::where('name', 'like', "%{$q}%")->limit(4)->get()->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'sub' => $d->currency.' '.number_format((float) $d->value)]),
        ]);
    }
}
