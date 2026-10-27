<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Support\Google\GoogleApi;
use App\Support\Google\ReportRange;
use Illuminate\Validation\ValidationException;

/**
 * Search Console through the Search Analytics, Sitemaps and URL Inspection
 * APIs (webmasters.readonly). Search Analytics is performance data only -
 * it has no page-indexing totals, which the Search Console UI alone shows,
 * so indexing here is limited to per-URL inspection.
 */
class SearchConsoleService
{
    private const API = 'https://www.googleapis.com/webmasters/v3/';
    private const INSPECT_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public const SEARCH_TYPES = ['web', 'image', 'video', 'news', 'discover', 'googleNews'];

    /** Discover and Google News report no query dimension. */
    private const NO_QUERIES = ['discover', 'googleNews'];

    public const REPORTS = ['daily' => 'date', 'queries' => 'query', 'pages' => 'page', 'countries' => 'country', 'devices' => 'device', 'appearance' => 'searchAppearance'];

    public function dashboard(GoogleApi $api, GoogleProperty $property, ReportRange $range, array $filters): array
    {
        $type = $filters['type'] ?? 'web';
        $groups = $this->filterGroups($filters);
        $query = fn (array $window, array $dimensions, int $limit = 1000) => $this->query($api, $property, $window, $dimensions, $type, $groups, $limit);

        $totals = fn (array $window) => $this->metrics($query($window, [], 1)[0] ?? []);
        $daily = $query($range->current(), ['date']);
        $previousDaily = $query($range->previous(), ['date']);

        $withTrend = function (string $dimension, int $limit) use ($query, $range) {
            $previous = collect($query($range->previous(), [$dimension], 500))->keyBy(fn ($r) => $r['keys'][0]);

            return array_map(fn ($row) => ['key' => $row['keys'][0]] + $this->metrics($row) + [
                'previous_clicks' => isset($previous[$row['keys'][0]]) ? (int) $previous[$row['keys'][0]]['clicks'] : null,
            ], $query($range->current(), [$dimension], $limit));
        };

        $supportsQueries = ! in_array($type, self::NO_QUERIES, true);
        $dates = $this->dailySeries($daily, $previousDaily, $range);

        return [
            'totals' => ['current' => $totals($range->current()), 'previous' => $totals($range->previous())],
            'trend' => $dates,
            'queries' => $supportsQueries ? $withTrend('query', 25) : null,
            'pages' => $withTrend('page', 25),
            'countries' => array_map(fn ($r) => ['key' => $r['keys'][0]] + $this->metrics($r), $query($range->current(), ['country'], 15)),
            'devices' => array_map(fn ($r) => ['key' => $r['keys'][0]] + $this->metrics($r), $query($range->current(), ['device'], 5)),
            // searchAppearance can't be grouped with other dimensions (API docs).
            'appearance' => array_map(fn ($r) => ['key' => $r['keys'][0]] + $this->metrics($r), $query($range->current(), ['searchAppearance'], 20)),
            'sitemaps' => $this->sitemaps($api, $property),
            'data_through' => collect($daily)->filter(fn ($r) => $r['impressions'] > 0)->max(fn ($r) => $r['keys'][0]),
            'search_type' => $type,
            'supports_queries' => $supportsQueries,
        ];
    }

    public function report(GoogleApi $api, GoogleProperty $property, string $type, ReportRange $range, int $limit, array $filters = []): array
    {
        $dimension = self::REPORTS[$type];
        $rows = $this->query($api, $property, $range->current(), [$dimension], $filters['type'] ?? 'web', $this->filterGroups($filters), $limit);

        return [
            'columns' => [
                ['key' => $dimension, 'type' => $dimension === 'date' ? 'date' : 'text'],
                ['key' => 'clicks', 'type' => 'number'],
                ['key' => 'impressions', 'type' => 'number'],
                ['key' => 'ctr', 'type' => 'percent'],
                ['key' => 'position', 'type' => 'decimal'],
            ],
            'rows' => array_map(fn ($r) => [$dimension => $r['keys'][0]] + $this->metrics($r), $rows),
        ];
    }

    public function sitemaps(GoogleApi $api, GoogleProperty $property): array
    {
        $list = $api->get(self::API . 'sites/' . rawurlencode($property->external_id) . '/sitemaps')['sitemap'] ?? [];

        return array_map(fn ($s) => [
            'path' => $s['path'],
            'last_downloaded' => $s['lastDownloaded'] ?? null,
            'last_submitted' => $s['lastSubmitted'] ?? null,
            'is_pending' => (bool) ($s['isPending'] ?? false),
            'errors' => (int) ($s['errors'] ?? 0),
            'warnings' => (int) ($s['warnings'] ?? 0),
            'submitted' => (int) collect($s['contents'] ?? [])->sum('submitted'),
        ], $list);
    }

    /** URL Inspection: Google's indexed view of one URL inside this property. */
    public function inspect(GoogleApi $api, GoogleProperty $property, string $url, string $locale): array
    {
        if (! $this->belongsToProperty($url, $property->external_id)) {
            throw ValidationException::withMessages(['url' => __('google_suite.validation.inspect_outside', ['property' => $property->display_name])]);
        }

        $result = $api->post(self::INSPECT_URL, [
            'inspectionUrl' => $url,
            'siteUrl' => $property->external_id,
            'languageCode' => $locale === 'ar' ? 'ar' : 'en-US',
        ])['inspectionResult'] ?? [];
        $index = $result['indexStatusResult'] ?? [];

        return [
            'url' => $url,
            'verdict' => $index['verdict'] ?? 'VERDICT_UNSPECIFIED',
            'coverage' => $index['coverageState'] ?? null,
            'indexing_state' => $index['indexingState'] ?? null,
            'robots' => $index['robotsTxtState'] ?? null,
            'fetch' => $index['pageFetchState'] ?? null,
            'last_crawl' => $index['lastCrawlTime'] ?? null,
            'crawled_as' => $index['crawledAs'] ?? null,
            'google_canonical' => $index['googleCanonical'] ?? null,
            'user_canonical' => $index['userCanonical'] ?? null,
            'link' => $result['inspectionResultLink'] ?? null,
        ];
    }

    public function belongsToProperty(string $url, string $siteUrl): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! $host || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return false;
        }

        if (str_starts_with($siteUrl, 'sc-domain:')) {
            $domain = strtolower(substr($siteUrl, 10));

            return $host === $domain || str_ends_with($host, '.' . $domain);
        }

        return str_starts_with($url, $siteUrl);
    }

    private function query(GoogleApi $api, GoogleProperty $property, array $window, array $dimensions, string $type, array $groups, int $limit): array
    {
        $body = array_filter([
            'startDate' => $window[0],
            'endDate' => $window[1],
            'dimensions' => $dimensions ?: null,
            'type' => $type,
            'dimensionFilterGroups' => $groups ?: null,
            'rowLimit' => min($limit, 25000),
            // Include fresh (not yet final) data, as the Search Console UI does.
            'dataState' => 'all',
        ], fn ($v) => $v !== null);

        return $api->post(self::API . 'sites/' . rawurlencode($property->external_id) . '/searchAnalytics/query', $body)['rows'] ?? [];
    }

    private function filterGroups(array $filters): array
    {
        $conditions = [];

        foreach (['query' => 'query', 'page' => 'page'] as $input => $dimension) {
            if (filled($filters[$input] ?? null)) {
                $conditions[] = ['dimension' => $dimension, 'operator' => 'contains', 'expression' => mb_substr((string) $filters[$input], 0, 200)];
            }
        }

        return $conditions ? [['groupType' => 'and', 'filters' => $conditions]] : [];
    }

    private function metrics(array $row): array
    {
        return [
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    private function dailySeries(array $current, array $previous, ReportRange $range): array
    {
        $byDate = fn (array $rows) => collect($rows)->keyBy(fn ($r) => $r['keys'][0]);
        $current = $byDate($current);
        $previous = $byDate($previous);
        $series = ['dates' => []];

        foreach (['clicks', 'impressions', 'ctr', 'position'] as $metric) {
            $series[$metric] = ['current' => [], 'previous' => []];
        }

        for ($i = 0; $i < $range->days(); $i++) {
            $day = $range->start->copy()->addDays($i)->toDateString();
            $previousDay = $range->previousStart->copy()->addDays($i)->toDateString();
            $series['dates'][] = $day;

            foreach (['clicks', 'impressions', 'ctr', 'position'] as $metric) {
                $series[$metric]['current'][] = isset($current[$day]) ? (float) $current[$day][$metric] : ($metric === 'position' ? null : 0);
                $series[$metric]['previous'][] = isset($previous[$previousDay]) ? (float) $previous[$previousDay][$metric] : ($metric === 'position' ? null : 0);
            }
        }

        return $series;
    }
}
