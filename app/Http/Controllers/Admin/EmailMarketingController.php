<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailEvent;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class EmailMarketingController extends Controller
{
    public function __construct(protected EmailMarketingService $emailMarketing)
    {
    }

    public function dashboard()
    {
        $userId = Auth::id();
        $now = now();
        $weekAgo = $now->copy()->subDays(7);
        $twoWeeksAgo = $now->copy()->subDays(14);

        // Shared range control for the Campaign Performance chart, its
        // three Delivered/Opened/Clicked sub-cards, and the hero's date
        // range label - one real query param driving all three rather
        // than separate, uncoordinated widgets.
        $range = (int) request('range', 30);
        $range = in_array($range, [7, 30, 90], true) ? $range : 30;
        $rangeStart = $now->copy()->subDays($range - 1)->startOfDay();
        $previousRangeStart = $now->copy()->subDays(($range * 2) - 1)->startOfDay();

        $totalSubscribers = EmailSubscriber::where('user_id', $userId)->where('status', 'subscribed')->count();
        $subscribersTrend = $this->trend(
            EmailSubscriber::where('user_id', $userId)->where('created_at', '>=', $weekAgo)->count(),
            EmailSubscriber::where('user_id', $userId)->whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count()
        );

        $totalLists = EmailList::where('user_id', $userId)->count();
        $listsTrend = $this->trend(
            EmailList::where('user_id', $userId)->where('created_at', '>=', $weekAgo)->count(),
            EmailList::where('user_id', $userId)->whereBetween('created_at', [$twoWeeksAgo, $weekAgo])->count()
        );

        $sentCampaigns = EmailCampaign::where('user_id', $userId)->where('status', 'sent')->get();
        $avgOpenRate = $sentCampaigns->isNotEmpty() ? round($sentCampaigns->avg(fn ($c) => $c->openRate()), 1) : 0.0;

        $campaignsTrend = $this->trend(
            $sentCampaigns->where('sent_at', '>=', $weekAgo)->count(),
            $sentCampaigns->whereBetween('sent_at', [$twoWeeksAgo, $weekAgo])->count()
        );

        $openRateTrend = $this->trend(
            $sentCampaigns->where('sent_at', '>=', $weekAgo)->avg(fn ($c) => $c->openRate()) ?? 0,
            $sentCampaigns->whereBetween('sent_at', [$twoWeeksAgo, $weekAgo])->avg(fn ($c) => $c->openRate()) ?? 0
        );

        $recentCampaigns = EmailCampaign::where('user_id', $userId)->with('list')->latest()->take(6)->get();

        [$chartLabels, $chartDelivered, $chartOpened, $chartClicked] = $this->dailyEventSeries($userId, $range);

        $periodSent = (int) EmailCampaign::where('user_id', $userId)->where('status', 'sent')
            ->where('sent_at', '>=', $rangeStart)->sum('sent_count');
        $periodDelivered = array_sum($chartDelivered);
        $periodOpened = array_sum($chartOpened);
        $periodClicked = array_sum($chartClicked);

        $sparkNewContacts = $this->dailyCountSeries(
            EmailSubscriber::where('user_id', $userId), 'created_at', $range
        );
        $sparkActiveLists = $this->dailyCumulativeSeries(EmailList::where('user_id', $userId), $range);
        $sparkCampaignsSent = $this->dailyCountSeries(
            EmailCampaign::where('user_id', $userId)->where('status', 'sent'), 'sent_at', $range
        );
        $sparkOpenRate = array_map(
            fn ($delivered, $opened) => $delivered > 0 ? round(($opened / $delivered) * 100, 1) : 0,
            $chartDelivered,
            $chartOpened
        );

        $newContactsCount = EmailSubscriber::where('user_id', $userId)->where('created_at', '>=', $rangeStart)->count();
        $newContactsTrend = $this->trend(
            $newContactsCount,
            EmailSubscriber::where('user_id', $userId)->whereBetween('created_at', [$previousRangeStart, $rangeStart])->count()
        );

        $unsubBounceTypes = ['unsubscribe', 'group_unsubscribe', 'bounce', 'spamreport'];
        $unsubBounceCount = EmailEvent::where('user_id', $userId)->whereIn('event_type', $unsubBounceTypes)
            ->where('event_at', '>=', $rangeStart)->count();
        $unsubBounceTrend = $this->trend(
            $unsubBounceCount,
            EmailEvent::where('user_id', $userId)->whereIn('event_type', $unsubBounceTypes)
                ->whereBetween('event_at', [$previousRangeStart, $rangeStart])->count()
        );

        $sourceBreakdown = $this->sourceBreakdown($userId, $totalSubscribers);
        $recentActivity = $this->recentActivity($userId);

        return view('admin.email.dashboard', [
            'isReady'            => $this->emailMarketing->isReadyForUser($userId),
            'range'              => $range,
            'rangeStart'         => $rangeStart,
            'rangeEnd'           => $now,
            'totalSubscribers'   => $totalSubscribers,
            'subscribersTrend'   => $subscribersTrend,
            'totalLists'         => $totalLists,
            'listsTrend'         => $listsTrend,
            'totalSent'          => $sentCampaigns->count(),
            'campaignsTrend'     => $campaignsTrend,
            'avgOpenRate'        => $avgOpenRate,
            'openRateTrend'      => $openRateTrend,
            'recentCampaigns'    => $recentCampaigns,
            'chartLabels'        => $chartLabels,
            'chartDelivered'     => $chartDelivered,
            'chartOpened'        => $chartOpened,
            'chartClicked'       => $chartClicked,
            'periodSent'         => $periodSent,
            'periodDelivered'    => $periodDelivered,
            'periodOpened'       => $periodOpened,
            'periodClicked'      => $periodClicked,
            'sparkNewContacts'   => $sparkNewContacts,
            'sparkActiveLists'   => $sparkActiveLists,
            'sparkCampaignsSent' => $sparkCampaignsSent,
            'sparkOpenRate'      => $sparkOpenRate,
            'newContactsCount'   => $newContactsCount,
            'newContactsTrend'   => $newContactsTrend,
            'unsubBounceCount'   => $unsubBounceCount,
            'unsubBounceTrend'   => $unsubBounceTrend,
            'sourceBreakdown'    => $sourceBreakdown,
            'recentActivity'     => $recentActivity,
        ]);
    }

    /**
     * Real period-over-period comparison (this week vs the week before),
     * not a decorative number - null means "no prior-period activity to
     * compare against" (the view shows a "New" badge instead of a
     * meaningless +Infinity%/0% for that case).
     */
    private function trend(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Daily delivered/opened/clicked counts for the selected range,
     * sourced from the real EmailEvent rows the SendGrid webhook writes -
     * powers the Campaign Performance chart. Empty days simply show 0,
     * not fabricated data.
     */
    private function dailyEventSeries(int $userId, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = EmailEvent::where('user_id', $userId)
            ->where('event_at', '>=', $start)
            ->selectRaw('DATE(event_at) as day, event_type, COUNT(*) as c')
            ->groupBy('day', 'event_type')
            ->get()
            ->groupBy('day');

        $labels = [];
        $delivered = [];
        $opened = [];
        $clicked = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M j');
            $dayRows = $rows->get($date, collect());
            $delivered[] = (int) ($dayRows->firstWhere('event_type', 'delivered')->c ?? 0);
            $opened[] = (int) ($dayRows->firstWhere('event_type', 'open')->c ?? 0);
            $clicked[] = (int) ($dayRows->firstWhere('event_type', 'click')->c ?? 0);
        }

        return [$labels, $delivered, $opened, $clicked];
    }

    /**
     * Daily row-creation counts for a given query/date column over the
     * selected range - a small, generic helper reused for the Total
     * Contacts and Campaigns Sent stat-card sparklines instead of writing
     * the same groupBy-and-fill-gaps loop twice.
     */
    private function dailyCountSeries($query, string $column, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = (clone $query)->where($column, '>=', $start)
            ->selectRaw("DATE({$column}) as day, COUNT(*) as c")
            ->groupBy('day')
            ->pluck('c', 'day');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $series[] = (int) ($rows->get($date, 0));
        }

        return $series;
    }

    /**
     * Running total of rows that existed as of each day in the range
     * (not just that day's new rows) - used for the Active Lists
     * sparkline since lists are created rarely, and a "new lists per day"
     * series would just be flat zeros nearly every day.
     */
    private function dailyCumulativeSeries($query, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $baseline = (clone $query)->where('created_at', '<', $start)->count();

        $rows = (clone $query)->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        $series = [];
        $running = $baseline;
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $running += (int) $rows->get($date, 0);
            $series[] = $running;
        }

        return $series;
    }

    /**
     * Groups all-time subscribers by how they actually entered the
     * system (email_subscribers.source, set at creation time in
     * EmailSubscriberController::store()/import()) - rows created before
     * that column existed have a null source and are grouped as
     * "Unknown" rather than guessed at.
     */
    private function sourceBreakdown(int $userId, int $total): Collection
    {
        $labels = ['manual' => 'Manually Added', 'import' => 'CSV Import'];

        $counts = EmailSubscriber::where('user_id', $userId)
            ->selectRaw('COALESCE(source, "unknown") as src, COUNT(*) as c')
            ->groupBy('src')
            ->pluck('c', 'src');

        return $counts->map(function ($count, $key) use ($labels, $total) {
            return [
                'label'      => $labels[$key] ?? 'Unknown',
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        })->sortByDesc('count')->values();
    }

    /**
     * Merges the four real, timestamped things that happen in this
     * module into one recency-sorted feed - no separate "activity log"
     * table exists, so this reads directly from the rows that already
     * record each event (subscribers, campaigns, and SendGrid webhook
     * events) rather than fabricating a synthetic activity stream.
     */
    private function recentActivity(int $userId): Collection
    {
        $newContacts = EmailSubscriber::where('user_id', $userId)
            ->whereNotNull('subscribed_at')
            ->latest('subscribed_at')->take(5)->get()
            ->map(fn ($s) => ['type' => 'subscribed', 'title' => 'New contact subscribed', 'subtitle' => $s->email, 'at' => $s->subscribed_at]);

        $sentCampaigns = EmailCampaign::where('user_id', $userId)->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->latest('sent_at')->take(5)->get()
            ->map(fn ($c) => ['type' => 'sent', 'title' => 'Campaign sent', 'subtitle' => $c->name, 'at' => $c->sent_at]);

        $unsubscribed = EmailEvent::where('user_id', $userId)
            ->whereIn('event_type', ['unsubscribe', 'group_unsubscribe'])
            ->latest('event_at')->take(5)->get()
            ->map(fn ($e) => ['type' => 'unsubscribed', 'title' => 'Contact unsubscribed', 'subtitle' => $e->recipient_email, 'at' => $e->event_at]);

        $opened = EmailEvent::where('user_id', $userId)->where('event_type', 'open')
            ->with('campaign:id,name')
            ->latest('event_at')->take(5)->get()
            ->map(fn ($e) => ['type' => 'opened', 'title' => 'Campaign opened', 'subtitle' => $e->campaign->name ?? $e->recipient_email, 'at' => $e->event_at]);

        return $newContacts->concat($sentCampaigns)->concat($unsubscribed)->concat($opened)
            ->filter(fn ($item) => $item['at'] !== null)
            ->sortByDesc('at')
            ->take(8)
            ->values();
    }
}
