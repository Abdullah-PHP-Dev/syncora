<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Support\Google\GoogleApi;
use App\Support\Google\ReportRange;

/**
 * GA4 reporting through the Google Analytics Data API (v1beta) with the
 * analytics.readonly consent. Metric and dimension names are the API's own
 * (developers.google.com/analytics/devguides/reporting/data/v1/api-schema);
 * nothing is derived that GA4 doesn't report, except the per-user average
 * engagement time, which GA4 itself defines as userEngagementDuration / activeUsers.
 */
class Ga4Service
{
    private const DATA_API = 'https://analyticsdata.googleapis.com/v1beta/';
    private const ADMIN_API = 'https://analyticsadmin.googleapis.com/v1beta/';

    /** Reports page catalog: dimension + metrics (+ column types for formatting). */
    public const REPORTS = [
        'daily' => ['dimension' => 'date', 'metrics' => ['activeUsers', 'sessions', 'screenPageViews', 'engagementRate', 'keyEvents']],
        'channels' => ['dimension' => 'sessionDefaultChannelGroup', 'metrics' => ['sessions', 'engagedSessions', 'engagementRate', 'keyEvents', 'totalRevenue']],
        'sources' => ['dimension' => 'sessionSourceMedium', 'metrics' => ['sessions', 'activeUsers', 'engagementRate', 'keyEvents']],
        'landing_pages' => ['dimension' => 'landingPage', 'metrics' => ['sessions', 'activeUsers', 'engagementRate', 'keyEvents']],
        'pages' => ['dimension' => 'pagePath', 'metrics' => ['screenPageViews', 'activeUsers', 'userEngagementDuration', 'keyEvents']],
        'countries' => ['dimension' => 'country', 'metrics' => ['activeUsers', 'sessions', 'engagementRate']],
        'devices' => ['dimension' => 'deviceCategory', 'metrics' => ['activeUsers', 'sessions', 'engagementRate']],
        'events' => ['dimension' => 'eventName', 'metrics' => ['eventCount', 'keyEvents', 'totalUsers']],
    ];

    public const TYPES = [
        'date' => 'date', 'engagementRate' => 'percent', 'userEngagementDuration' => 'seconds', 'totalRevenue' => 'money',
    ];

    /** Time zone and currency shown next to the property; fetched on selection. */
    public function propertyDetails(GoogleApi $api, GoogleProperty $property): array
    {
        $details = $api->get(self::ADMIN_API . $property->external_id);

        return array_filter([
            'time_zone' => $details['timeZone'] ?? null,
            'currency_code' => $details['currencyCode'] ?? null,
            'industry' => $details['industryCategory'] ?? null,
        ]);
    }

    public function dashboard(GoogleApi $api, GoogleProperty $property, ReportRange $range): array
    {
        $both = $this->dateRanges($range, true);
        $current = $this->dateRanges($range, false);

        [$totals, $trend, $channels, $landing, $pages] = $this->batch($api, $property, [
            ['dateRanges' => $both, 'metrics' => $this->metrics(['activeUsers', 'totalUsers', 'newUsers', 'sessions', 'engagedSessions', 'screenPageViews', 'engagementRate', 'userEngagementDuration', 'keyEvents', 'totalRevenue'])],
            ['dateRanges' => $both, 'dimensions' => [['name' => 'date']], 'metrics' => $this->metrics(['activeUsers', 'sessions', 'keyEvents']), 'limit' => 1000],
            $this->top($current, 'sessionDefaultChannelGroup', ['sessions', 'engagedSessions', 'keyEvents', 'totalRevenue'], 'sessions', 10),
            $this->top($current, 'landingPage', ['sessions', 'activeUsers', 'engagementRate', 'keyEvents'], 'sessions', 10),
            $this->top($current, 'pagePath', ['screenPageViews', 'activeUsers', 'userEngagementDuration', 'keyEvents'], 'screenPageViews', 10),
        ]);

        [$devices, $countries, $events, $sources] = $this->batch($api, $property, [
            $this->top($current, 'deviceCategory', ['activeUsers', 'sessions'], 'activeUsers', 5),
            $this->top($current, 'country', ['activeUsers', 'sessions'], 'activeUsers', 10),
            $this->top($current, 'eventName', ['keyEvents'], 'keyEvents', 10) + ['metricFilter' => [
                'filter' => ['fieldName' => 'keyEvents', 'numericFilter' => ['operation' => 'GREATER_THAN', 'value' => ['int64Value' => '0']]],
            ]],
            $this->top($current, 'sessionSourceMedium', ['sessions', 'activeUsers'], 'sessions', 10),
        ]);

        $byRange = collect($totals)->keyBy('dateRange');
        $summary = fn (string $name) => $this->summary($byRange[$name] ?? []);

        return [
            'totals' => ['current' => $summary('current'), 'previous' => $summary('previous')],
            'trend' => $this->trend($trend, $range, ['activeUsers' => 'active_users', 'sessions' => 'sessions', 'keyEvents' => 'key_events']),
            'channels' => array_map(fn ($r) => [
                'channel' => $r['sessionDefaultChannelGroup'], 'sessions' => (int) $r['sessions'], 'engaged' => (int) $r['engagedSessions'],
                'key_events' => (float) $r['keyEvents'], 'revenue' => (float) $r['totalRevenue'],
            ], $channels),
            'sources' => array_map(fn ($r) => ['source' => $r['sessionSourceMedium'], 'sessions' => (int) $r['sessions'], 'users' => (int) $r['activeUsers']], $sources),
            'landing_pages' => array_map(fn ($r) => [
                'page' => $r['landingPage'], 'sessions' => (int) $r['sessions'], 'users' => (int) $r['activeUsers'],
                'engagement_rate' => (float) $r['engagementRate'], 'key_events' => (float) $r['keyEvents'],
            ], $landing),
            'pages' => array_map(fn ($r) => [
                'page' => $r['pagePath'], 'views' => (int) $r['screenPageViews'], 'users' => (int) $r['activeUsers'],
                'avg_engagement' => (int) $r['activeUsers'] > 0 ? (float) $r['userEngagementDuration'] / (int) $r['activeUsers'] : 0,
                'key_events' => (float) $r['keyEvents'],
            ], $pages),
            'devices' => array_map(fn ($r) => ['device' => $r['deviceCategory'], 'users' => (int) $r['activeUsers'], 'sessions' => (int) $r['sessions']], $devices),
            'countries' => array_map(fn ($r) => ['country' => $r['country'], 'users' => (int) $r['activeUsers'], 'sessions' => (int) $r['sessions']], $countries),
            'key_events' => array_map(fn ($r) => ['event' => $r['eventName'], 'count' => (float) $r['keyEvents']], $events),
            'currency' => $property->meta['currency_code'] ?? null,
            'time_zone' => $property->meta['time_zone'] ?? null,
        ];
    }

    /** Last 30 minutes (Realtime API): total, per-minute, top countries. */
    public function realtime(GoogleApi $api, GoogleProperty $property): array
    {
        $url = self::DATA_API . 'properties/' . $property->ga4PropertyId() . ':runRealtimeReport';
        $metrics = $this->metrics(['activeUsers']);

        $total = $this->parse($api->post($url, ['metrics' => $metrics]));
        $minutes = $this->parse($api->post($url, ['dimensions' => [['name' => 'minutesAgo']], 'metrics' => $metrics, 'limit' => 30]));
        $countries = $this->parse($api->post($url, ['dimensions' => [['name' => 'country']], 'metrics' => $metrics, 'limit' => 5,
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]]));

        $perMinute = array_fill(0, 30, 0);
        foreach ($minutes as $row) {
            $ago = (int) $row['minutesAgo'];
            if ($ago >= 0 && $ago < 30) {
                $perMinute[29 - $ago] = (int) $row['activeUsers']; // oldest first
            }
        }

        return [
            'active_users' => (int) ($total[0]['activeUsers'] ?? 0),
            'per_minute' => $perMinute,
            'countries' => array_map(fn ($r) => ['country' => $r['country'], 'users' => (int) $r['activeUsers']], $countries),
        ];
    }

    /** One catalog report for the Reports page / CSV export. */
    public function report(GoogleApi $api, GoogleProperty $property, string $type, ReportRange $range, int $limit): array
    {
        $definition = self::REPORTS[$type];
        $request = $this->top($this->dateRanges($range, false), $definition['dimension'], $definition['metrics'], $definition['metrics'][0], $limit);

        if ($type === 'daily') {
            $request['orderBys'] = [['dimension' => ['dimensionName' => 'date']]];
        }

        $rows = $this->parse($api->post(self::DATA_API . 'properties/' . $property->ga4PropertyId() . ':runReport', $request));

        return [
            'columns' => array_map(fn ($name) => ['key' => $name, 'type' => self::TYPES[$name] ?? ($name === $definition['dimension'] ? 'text' : 'number')], [$definition['dimension'], ...$definition['metrics']]),
            'rows' => array_map(function ($row) use ($definition) {
                if ($definition['dimension'] === 'date') {
                    $row['date'] = substr($row['date'], 0, 4) . '-' . substr($row['date'], 4, 2) . '-' . substr($row['date'], 6, 2);
                }

                return $row;
            }, $rows),
        ];
    }

    private function batch(GoogleApi $api, GoogleProperty $property, array $requests): array
    {
        // batchRunReports takes at most 5 requests.
        $response = $api->post(self::DATA_API . 'properties/' . $property->ga4PropertyId() . ':batchRunReports', ['requests' => $requests]);

        return array_map(fn ($report) => $this->parse($report), array_pad($response['reports'] ?? [], count($requests), []));
    }

    private function top(array $dateRanges, string $dimension, array $metrics, string $orderBy, int $limit): array
    {
        return [
            'dateRanges' => $dateRanges,
            'dimensions' => [['name' => $dimension]],
            'metrics' => $this->metrics($metrics),
            'orderBys' => [['metric' => ['metricName' => $orderBy], 'desc' => true]],
            'limit' => $limit,
        ];
    }

    private function dateRanges(ReportRange $range, bool $withPrevious): array
    {
        [$start, $end] = $range->current();
        $ranges = [['startDate' => $start, 'endDate' => $end, 'name' => 'current']];

        if ($withPrevious) {
            [$start, $end] = $range->previous();
            $ranges[] = ['startDate' => $start, 'endDate' => $end, 'name' => 'previous'];
        }

        return $ranges;
    }

    private function metrics(array $names): array
    {
        return array_map(fn ($name) => ['name' => $name], $names);
    }

    /** Rows as [header => value]; a multi-range request adds the dateRange dimension. */
    private function parse(array $report): array
    {
        $headers = array_merge(
            array_column($report['dimensionHeaders'] ?? [], 'name'),
            array_column($report['metricHeaders'] ?? [], 'name'),
        );

        return array_map(function ($row) use ($headers) {
            $values = array_merge(
                array_column($row['dimensionValues'] ?? [], 'value'),
                array_column($row['metricValues'] ?? [], 'value'),
            );

            return array_combine($headers, array_pad($values, count($headers), null));
        }, $report['rows'] ?? []);
    }

    private function summary(array $row): array
    {
        $users = (int) ($row['activeUsers'] ?? 0);

        return [
            'active_users' => $users,
            'total_users' => (int) ($row['totalUsers'] ?? 0),
            'new_users' => (int) ($row['newUsers'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'engaged_sessions' => (int) ($row['engagedSessions'] ?? 0),
            'views' => (int) ($row['screenPageViews'] ?? 0),
            'engagement_rate' => (float) ($row['engagementRate'] ?? 0),
            'avg_engagement' => $users > 0 ? (float) ($row['userEngagementDuration'] ?? 0) / $users : 0,
            'key_events' => (float) ($row['keyEvents'] ?? 0),
            'revenue' => (float) ($row['totalRevenue'] ?? 0),
        ];
    }

    /** Day-by-day series for both windows, aligned by day index; missing days are 0. */
    private function trend(array $rows, ReportRange $range, array $metrics): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['dateRange'] ?? 'current'][$row['date']] = $row;
        }

        $series = ['dates' => []];
        foreach ($metrics as $key) {
            $series[$key] = ['current' => [], 'previous' => []];
        }

        for ($i = 0; $i < $range->days(); $i++) {
            $day = $range->start->copy()->addDays($i);
            $previousDay = $range->previousStart->copy()->addDays($i);
            $series['dates'][] = $day->toDateString();

            foreach ($metrics as $metric => $key) {
                $series[$key]['current'][] = (float) ($indexed['current'][$day->format('Ymd')][$metric] ?? 0);
                $series[$key]['previous'][] = (float) ($indexed['previous'][$previousDay->format('Ymd')][$metric] ?? 0);
            }
        }

        return $series;
    }
}
