<?php

namespace App\Services\GoogleSuite;

use App\Support\Google\GoogleApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Website performance through the public PageSpeed Insights API. Needs no
 * Google sign-in: Google loads the page itself, so only public URLs can be
 * tested. Field data (real Chrome users, CrUX) and lab data (one Lighthouse
 * run) are returned separately and never mixed.
 */
class PageSpeedService
{
    public const API = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    public const STRATEGIES = ['mobile', 'desktop'];

    /** A Lighthouse run is slow and Google rate-limits it; results stay valid for a while. */
    private const TTL_HOURS = 12;

    private const LAB = [
        'largest-contentful-paint' => 'lcp', 'total-blocking-time' => 'tbt', 'cumulative-layout-shift' => 'cls',
        'first-contentful-paint' => 'fcp', 'speed-index' => 'si',
    ];

    private const FIELD = [
        'LARGEST_CONTENTFUL_PAINT_MS' => 'lcp', 'INTERACTION_TO_NEXT_PAINT' => 'inp', 'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'cls',
        'FIRST_CONTENTFUL_PAINT_MS' => 'fcp', 'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'ttfb',
    ];

    /** @return array{data?: array, error?: array, fetched_at?: string} */
    public function run(int $userId, string $url, string $strategy, string $locale, bool $fresh = false): array
    {
        $key = $this->key($userId, $url, $strategy);

        if (! $fresh && ($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $result = ['data' => $this->analyse($url, $strategy, $locale), 'fetched_at' => now()->toIso8601String()];
        } catch (GoogleApiException $e) {
            return ['error' => $e->toArray()];
        }

        Cache::put($key, $result, now()->addHours(self::TTL_HOURS));

        return $result;
    }

    /** The last run for this URL, if one is still cached - never starts a new one. */
    public function cached(int $userId, string $url, string $strategy): ?array
    {
        return Cache::get($this->key($userId, $url, $strategy));
    }

    public function analyse(string $url, string $strategy, string $locale): array
    {
        $query = array_filter([
            'url' => $url,
            'strategy' => strtoupper($strategy),
            'category' => 'PERFORMANCE',
            'locale' => $locale === 'ar' ? 'ar' : 'en',
            'key' => adminSetting('google.pagespeed.api_key') ?: config('services.google_pagespeed.key'),
        ]);

        // One Lighthouse run commonly takes 15-40 seconds.
        @set_time_limit(120);

        try {
            $response = Http::acceptJson()->timeout(90)->get(self::API, $query);
        } catch (ConnectionException $e) {
            throw new GoogleApiException(GoogleApiException::PROVIDER_OUTAGE, 'PageSpeed connection failed');
        }

        if (! $response->successful()) {
            $message = (string) $response->json('error.message', '');

            throw new GoogleApiException(match (true) {
                $response->status() === 429 => GoogleApiException::QUOTA_EXCEEDED,
                $response->status() >= 500 && ! str_contains($message, 'Lighthouse') => GoogleApiException::PROVIDER_OUTAGE,
                // FAILED_DOCUMENT_REQUEST, ERRORED_DOCUMENT_REQUEST, NO_FCP, DNS_FAILURE...: Google couldn't load the page.
                str_contains($message, 'Lighthouse returned error') || str_contains($message, 'Unable to process request') => GoogleApiException::PAGE_UNREACHABLE,
                default => GoogleApiException::BAD_REQUEST,
            }, $message, $response->status());
        }

        return $this->map($response->json() ?? [], $strategy);
    }

    public function map(array $body, string $strategy): array
    {
        $lighthouse = $body['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $score = $lighthouse['categories']['performance']['score'] ?? null;

        $lab = [];
        foreach (self::LAB as $id => $key) {
            if (isset($audits[$id]['numericValue'])) {
                $lab[$key] = ['value' => (float) $audits[$id]['numericValue'], 'display' => $audits[$id]['displayValue'] ?? null, 'score' => $audits[$id]['score'] ?? null];
            }
        }

        return [
            'url' => $body['id'] ?? null,
            'final_url' => $lighthouse['finalDisplayedUrl'] ?? $lighthouse['finalUrl'] ?? null,
            'strategy' => $strategy,
            'score' => $score === null ? null : (int) round($score * 100),
            'lab' => $lab,
            'field' => $this->field($body['loadingExperience'] ?? [], $body['originLoadingExperience'] ?? []),
            'opportunities' => $this->opportunities($lighthouse),
            'passed' => collect($lighthouse['categories']['performance']['auditRefs'] ?? [])
                ->filter(fn ($ref) => ! in_array($ref['group'] ?? null, ['metrics', 'hidden'], true))
                ->filter(fn ($ref) => ($audits[$ref['id']]['score'] ?? null) !== null && $audits[$ref['id']]['score'] >= 0.9)
                ->count(),
            'warnings' => array_values(array_slice($lighthouse['runWarnings'] ?? [], 0, 3)),
            'analysed_at' => $body['analysisUTCTimestamp'] ?? $lighthouse['fetchTime'] ?? null,
            'lighthouse_version' => $lighthouse['lighthouseVersion'] ?? null,
        ];
    }

    /** Chrome UX Report data for the page, or its whole site when the page has too few visits. */
    private function field(array $page, array $origin): array
    {
        $source = ! empty($page['metrics']) && empty($page['origin_fallback']) ? ['page', $page] : (! empty($origin['metrics']) ? ['origin', $origin] : [null, []]);
        [$scope, $data] = $source;

        $metrics = [];
        foreach (self::FIELD as $id => $key) {
            if (isset($data['metrics'][$id]['percentile'])) {
                $percentile = (float) $data['metrics'][$id]['percentile'];
                // CrUX reports CLS multiplied by 100.
                $metrics[$key] = ['value' => $key === 'cls' ? $percentile / 100 : $percentile, 'category' => $data['metrics'][$id]['category'] ?? null];
            }
        }

        return ['scope' => $scope, 'overall' => $data['overall_category'] ?? null, 'metrics' => $metrics];
    }

    /** Failing performance audits, biggest estimated saving first. */
    private function opportunities(array $lighthouse): array
    {
        $audits = $lighthouse['audits'] ?? [];

        return collect($lighthouse['categories']['performance']['auditRefs'] ?? [])
            ->reject(fn ($ref) => in_array($ref['group'] ?? null, ['metrics', 'hidden'], true))
            ->map(fn ($ref) => $audits[$ref['id']] ?? null)
            ->filter(fn ($audit) => $audit
                && ($audit['score'] ?? null) !== null
                && $audit['score'] < 0.9
                && in_array($audit['scoreDisplayMode'] ?? '', ['numeric', 'binary', 'metricSavings'], true))
            ->map(function (array $audit) {
                [$text, $link] = $this->plain((string) ($audit['description'] ?? ''));

                return [
                    'id' => $audit['id'] ?? null,
                    'title' => $audit['title'] ?? '',
                    'description' => $text,
                    'learn_url' => $link,
                    'display' => $audit['displayValue'] ?? null,
                    'savings_ms' => (int) round($audit['details']['overallSavingsMs'] ?? ($audit['metricSavings']['LCP'] ?? 0)),
                    'savings_bytes' => (int) ($audit['details']['overallSavingsBytes'] ?? 0),
                    'score' => (float) $audit['score'],
                ];
            })
            ->sortBy([['savings_ms', 'desc'], ['score', 'asc']])
            ->take(8)
            ->values()
            ->all();
    }

    /** Lighthouse descriptions are Markdown: keep the text, pull out the "Learn more" link. */
    private function plain(string $markdown): array
    {
        $link = null;
        if (preg_match('/\[[^\]]*\]\((https:\/\/[^)\s]+)\)/', $markdown, $m)) {
            $link = $m[1];
        }

        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $markdown);
        $text = trim(str_replace('`', '', (string) $text));

        return [$text, $link];
    }

    private function key(int $userId, string $url, string $strategy): string
    {
        return 'google_suite:pagespeed:' . $userId . ':' . sha1(strtolower(rtrim($url, '/')) . '|' . $strategy);
    }
}
