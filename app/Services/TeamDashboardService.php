<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamDashboardService
{
    public function data(User $user): array
    {
        // Was SupportTicket (Team\TicketController's separate, parallel
        // ticket system) - repointed to Ticket (Admin\TicketController)
        // when the two duplicate systems were consolidated onto the
        // latter. Field/relation names below (status, assigned_to,
        // 'assignee', last_activity_at-ordered) all match Ticket's actual
        // schema - see Ticket model.
        $tickets = Ticket::query();
        $data = [
            'ticketCounts' => [
                'open' => (clone $tickets)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->count(),
                'unassigned' => (clone $tickets)->whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'urgent' => (clone $tickets)->where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'mine' => (clone $tickets)->where('assigned_to', $user->id)->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
            'recentTickets' => (clone $tickets)->with(['user', 'assignee'])->whereNotIn('status', ['resolved', 'closed'])->latest('updated_at')->limit(8)->get(),
        ];
        if (!$user->hasRole('admin')) {
            return $data;
        }

        $subscriptions = Subscription::query()->whereHas('user', fn ($query) => $query->role('seller')->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'customer_support'])));
        $data['counts'] = [
            'subscribers' => (clone $subscriptions)->distinct()->count('user_id'),
            'active' => (clone $subscriptions)->where('is_active', true)->whereIn('status', ['active', 'trial'])
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', today()))->distinct()->count('user_id'),
            'ads' => DB::table('ads')->count(),
            'posts' => DB::table('posts')->count(),
        ];

        $start = now()->startOfMonth()->subMonths(11);
        // Group first subscriptions per seller, rather than renewals or billing cycles.
        $firstSubscriptions = DB::query()->fromSub(
            (clone $subscriptions)->selectRaw('user_id, MIN(created_at) as created_at')->groupBy('user_id')->toBase(),
            'first_subscriptions'
        );
        $queries = ['subscribers' => $firstSubscriptions, 'ads' => DB::table('ads'), 'posts' => DB::table('posts')];
        $data['chart'] = ['labels' => [], 'subscribers' => [], 'ads' => [], 'posts' => []];
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $totals = [];
        foreach ($queries as $key => $query) {
            $totals[$key] = $query->whereBetween('created_at', [$start, now()])
                ->selectRaw("$monthExpression as month, COUNT(*) as total")->groupByRaw($monthExpression)->pluck('total', 'month');
        }
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $data['chart']['labels'][] = $month->locale(app()->getLocale())->translatedFormat('M Y');
            foreach ($queries as $key => $_) {
                $data['chart'][$key][] = (int) ($totals[$key][$month->format('Y-m')] ?? 0);
            }
        }
        return $data;
    }
}
