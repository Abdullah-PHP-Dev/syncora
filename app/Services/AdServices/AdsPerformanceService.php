<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Services\GoogleSuite\GoogleAdsReportService;
use App\Support\AdAccountSelection;
use App\Support\Google\ReportRange;

/**
 * Spend and results for the Ads Dashboard, read live from the platforms
 * that report them: Google Ads (GAQL - spend, clicks, impressions,
 * conversions, value, daily trend, previous period), TikTok and Snapchat
 * (report / measurement API - spend, clicks, impressions, conversions,
 * daily trend, previous period) and ChatGPT Ads (spend, clicks, impressions for the period). Every other platform is
 * returned as "no_reporting" - nothing is estimated or filled in.
 */
class AdsPerformanceService
{
    public const RANGES = [7, 30, 90];

    /** Platforms whose campaigns run on the Google Ads customer. */
    private const GOOGLE_PLATFORMS = ['google', 'youtube'];

    /** Platforms read through their insights service (TiktokInsightsService, SnapchatInsightsService). */
    private const INSIGHTS_PLATFORMS = ['tiktok', 'snapchat'];

    public function __construct(private int $userId)
    {
    }

    public function build(int $days): array
    {
        $range = ReportRange::make($days . 'd');
        $platforms = [];

        foreach (AdsDashboardService::PLATFORMS as $key => $meta) {
            $connected = (bool) AdAccountSelection::resolve($this->userId, $key === 'youtube' ? 'google' : $key);
            $platforms[$key] = ['status' => $connected ? 'no_reporting' : 'not_connected'];
        }

        if ($platforms['google']['status'] !== 'not_connected') {
            $platforms['google'] = $this->google($range);
        }
        if ($platforms['openai']['status'] !== 'not_connected') {
            $platforms['openai'] = $this->openai($days);
        }
        foreach (self::INSIGHTS_PLATFORMS as $key) {
            if (($platforms[$key]['status'] ?? 'not_connected') !== 'not_connected') {
                $platforms[$key] = $this->insights($key, $days);
            }
        }
        // YouTube campaigns are reported inside the Google Ads customer's totals.
        if ($platforms['youtube']['status'] !== 'not_connected') {
            $platforms['youtube'] = ['status' => 'in_google'];
        }

        return [
            'range' => $range->toArray(),
            'platforms' => $platforms,
            'campaign_metrics' => $this->campaignMetrics($platforms),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * One platform's numbers for its campaigns page, with campaigns keyed by
     * local id. YouTube has no account of its own, so it gets the sum of its
     * own campaigns - the Google Ads customer's totals and trend also include
     * Search, Display and Performance Max.
     */
    public function forPlatform(string $platform, int $days): array
    {
        $range = ReportRange::make($days . 'd');
        $google = in_array($platform, self::GOOGLE_PLATFORMS, true);

        if (! AdAccountSelection::resolve($this->userId, $google ? 'google' : $platform)) {
            return ['status' => 'not_connected'];
        }

        $result = match (true) {
            $google => $this->google($range),
            $platform === 'openai' => $this->openai($days),
            in_array($platform, self::INSIGHTS_PLATFORMS, true) => $this->insights($platform, $days),
            default => ['status' => 'no_reporting'],
        };
        if ($result['status'] !== 'ok') {
            return $result;
        }

        $own = AdCampaign::where('user_id', $this->userId)->where('platform', $platform)->pluck('id')->all();
        $campaigns = array_intersect_key($this->campaignMetrics([$google ? 'google' : $platform => $result]), array_flip($own));

        if ($platform === 'youtube') {
            $sum = fn (string $key) => array_sum(array_map(fn ($c) => (float) ($c[$key] ?? 0), $campaigns));
            $result['totals'] = ['current' => ['spend' => $sum('spend'), 'clicks' => $sum('clicks'), 'impressions' => $sum('impressions'), 'conversions' => $sum('conversions'), 'value' => $sum('value')], 'previous' => null];
            $result['trend'] = null;
        }
        unset($result['local_keys']);

        return array_merge($result, [
            'scope' => $platform === 'youtube' ? 'campaigns' : 'account',
            'days' => $days,
            'range' => $range->toArray(),
            'campaigns' => $campaigns,
        ]);
    }

    private function google(ReportRange $range): array
    {
        $result = app(GoogleAdsReportService::class)->dashboard($this->userId, $range);

        if (isset($result['error'])) {
            return ['status' => 'error', 'error' => $result['error']['message']];
        }

        $d = $result['data'];
        $totals = fn (array $t) => [
            'spend' => $t['cost'], 'clicks' => $t['clicks'], 'impressions' => $t['impressions'],
            'conversions' => $t['conversions'], 'value' => $t['conversions_value'],
        ];

        return [
            'status' => 'ok',
            'currency' => $d['currency'],
            'has_conversions' => true,
            'conversion_tracking' => $d['conversion_tracking'],
            'totals' => ['current' => $totals($d['totals']['current']), 'previous' => $totals($d['totals']['previous'])],
            'trend' => [
                'dates' => $d['trend']['dates'],
                'spend' => $d['trend']['cost'],
                'clicks' => $d['trend']['clicks'],
                'conversions' => $d['trend']['conversions'],
            ],
            'campaigns' => collect($d['campaigns'])->mapWithKeys(fn ($c) => [(string) $c['id'] => [
                'spend' => $c['cost'], 'clicks' => $c['clicks'], 'impressions' => $c['impressions'],
                'conversions' => $c['conversions'], 'value' => $c['conversions_value'],
            ]])->all(),
            'fetched_at' => $result['fetched_at'] ?? null,
        ];
    }

    private function openai(int $days): array
    {
        $result = app(OpenAiAdService::class)->insights($days);

        if (! ($result['success'] ?? false)) {
            return ['status' => 'error', 'error' => $result['error'] ?? 'Could not load ChatGPT Ads reporting.'];
        }

        $t = $result['totals'];

        return [
            'status' => 'ok',
            'currency' => $result['currency'],
            'has_conversions' => false,
            // The API returns one total for the period - no previous period, no daily series.
            'totals' => ['current' => ['spend' => $t['spend'], 'clicks' => $t['clicks'], 'impressions' => $t['impressions'], 'conversions' => null, 'value' => null], 'previous' => null],
            'trend' => null,
            // Already keyed by the local campaign id where the campaign is known here.
            'campaigns' => collect($result['campaigns'])->map(fn ($c) => [
                'spend' => $c['spend'], 'clicks' => $c['clicks'], 'impressions' => $c['impressions'], 'conversions' => null, 'value' => null,
            ])->all(),
            'local_keys' => true,
        ];
    }

    private function insights(string $platform, int $days): array
    {
        $service = $platform === 'tiktok' ? TiktokInsightsService::forUser($this->userId) : SnapchatInsightsService::forUser($this->userId);
        $label = AdsDashboardService::PLATFORMS[$platform]['label'];
        $result = $service ? $service->insights($days) : ['success' => false, 'error' => "Connect a {$label} ad account first."];

        if (! ($result['success'] ?? false)) {
            return ['status' => 'error', 'error' => $result['error'] ?? "Could not load {$label} reporting."];
        }

        $totals = fn (array $t) => ['spend' => $t['spend'], 'clicks' => $t['clicks'], 'impressions' => $t['impressions'], 'conversions' => $t['conversions'], 'value' => $t['purchase_value'] ?: null];
        $daily = $result['details']['daily'] ?? null;

        return [
            'status' => 'ok',
            'currency' => $result['currency'],
            'has_conversions' => true,
            'totals' => ['current' => $totals($result['totals']['current']), 'previous' => $totals($result['totals']['previous'])],
            'trend' => $daily ? [
                'dates' => array_column($daily, 'date'),
                'spend' => array_column($daily, 'spend'),
                'clicks' => array_column($daily, 'link_clicks'),
                'conversions' => array_column($daily, 'conversions'),
            ] : null,
            // Keyed by the local campaign id where the campaign is known here.
            'campaigns' => collect($result['campaigns'])->map(fn ($c) => [
                'spend' => $c['spend'], 'clicks' => $c['clicks'], 'impressions' => $c['impressions'], 'conversions' => $c['conversions'], 'value' => $c['purchase_value'] ?: null,
            ])->all(),
            'local_keys' => true,
        ];
    }

    /** Metrics keyed by local campaign id, so the dashboard table can show them per row. */
    private function campaignMetrics(array $platforms): array
    {
        $out = [];

        if (($platforms['google']['status'] ?? null) === 'ok') {
            $byRemote = $platforms['google']['campaigns'];
            AdCampaign::where('user_id', $this->userId)->whereIn('platform', self::GOOGLE_PLATFORMS)
                ->get(['id', 'ad_campaign_id'])
                ->each(function ($c) use ($byRemote, &$out) {
                    // Stored as customers/{cid}/campaigns/{id}; GAQL returns the bare id.
                    $remote = preg_match('/(\d+)$/', (string) $c->ad_campaign_id, $m) ? $m[1] : null;
                    if ($remote && isset($byRemote[$remote])) {
                        $out[$c->id] = $byRemote[$remote];
                    }
                });
        }

        foreach (['openai', ...self::INSIGHTS_PLATFORMS] as $key) {
            if (($platforms[$key]['status'] ?? null) !== 'ok') {
                continue;
            }
            $local = AdCampaign::where('user_id', $this->userId)->where('platform', $key)->pluck('id')->map(fn ($id) => (string) $id)->all();
            foreach ($platforms[$key]['campaigns'] as $id => $metrics) {
                if (in_array((string) $id, $local, true)) {
                    $out[(int) $id] = $metrics;
                }
            }
        }

        return $out;
    }
}
