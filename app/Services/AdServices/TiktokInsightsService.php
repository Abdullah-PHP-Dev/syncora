<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Services\AdServices\Concerns\BuildsCampaignInsights;
use App\Support\Ads\TiktokAdsApi;
use App\Support\Ads\TiktokApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reporting and campaign import for the TikTok campaigns page, read from
 * the seller's selected TikTok advertiser through the synchronous report
 * endpoint (GET report/integrated/get/, official business-api-sdk) and
 * campaign/get/. The result has the same shape as
 * FacebookAdService::insights(), so MetaCampaigns.vue renders both.
 *
 * Only what TikTok reports is shown: results follow the campaign's
 * objective (reach, clicks, video views or conversions) and are left empty
 * where TikTok has no matching metric here. Each breakdown is its own
 * request and fails on its own (null), so one rejected field never hides
 * the rest.
 */
class TiktokInsightsService
{
    use BuildsCampaignInsights;

    /** TikTok groups a day-level report over at most this many days per request. */
    private const DAY_SPAN = 30;

    private const CORE = ['spend', 'impressions', 'clicks', 'reach', 'conversion'];

    /** Asked for alongside the core; dropped (and the call retried) if TikTok rejects them. */
    private const EXTRA = ['complete_payment_roas', 'video_play_actions'];

    public function __construct(private TiktokAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = TiktokAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    /** Result per objective: [result_type, metric] - TikTok's own metric for that goal. */
    private const RESULTS = [
        'REACH' => ['reach', 'reach'],
        'TRAFFIC' => ['link_click', 'clicks'],
        'VIDEO_VIEWS' => ['video_view', 'video_play_actions'],
        'LEAD_GENERATION' => ['conversion', 'conversion'],
        'WEB_CONVERSIONS' => ['conversion', 'conversion'],
        'APP_PROMOTION' => ['conversion', 'conversion'],
        'PRODUCT_SALES' => ['conversion', 'conversion'],
    ];

    public function insights(int $days = 30, bool $fresh = false): array
    {
        $account = $this->api->account;
        $key = 'tt_insights:v1:' . $account->id . ':' . $days . ':' . now()->toDateString();
        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(15), function () use ($days, $account) {
                $until = now()->subDay()->startOfDay();
                $since = $until->copy()->subDays($days - 1);
                $current = $this->campaignRows($since, $until);
                $previous = $this->campaignRows($since->copy()->subDays($days), $since->copy()->subDay());
                $totals = $this->insightTotals($current);

                $account->forceFill(['metadata' => array_merge($account->metadata ?? [], ['insights_synced_at' => now()->toIso8601String()])])->save();

                return [
                    'success' => true,
                    'days' => $days,
                    'since' => $since->toDateString(),
                    'until' => $until->toDateString(),
                    'currency' => $account->adDetails->currency ?? ($account->metadata['currency'] ?? null),
                    'platform' => 'tiktok',
                    'all_placements_spend' => $totals['spend'],
                    'totals' => ['current' => $totals, 'previous' => $this->insightTotals($previous)],
                    'campaigns' => $current,
                    'details' => $this->details($since, $until, $totals['result_type']),
                    'synced_at' => now()->toIso8601String(),
                ];
            });
        } catch (TiktokApiException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'reconnect' => $e->isAuthError()];
        }
    }

    /**
     * Imports the advertiser's campaigns (campaign/get/) into ad_campaigns,
     * for this seller and this ad account only. Ad groups and ads keep
     * coming from the builder.
     */
    public function syncCampaigns(): array
    {
        $account = $this->api->account;

        try {
            $synced = 0;
            for ($page = 1; $page <= 20; $page++) {
                $data = $this->api->get('campaign/get/', [
                    'page' => $page,
                    'page_size' => 100,
                    'fields' => ['campaign_id', 'campaign_name', 'objective_type', 'budget', 'budget_mode', 'operation_status'],
                ]);
                foreach ($data['list'] ?? [] as $c) {
                    if (empty($c['campaign_id'])) {
                        continue;
                    }
                    AdCampaign::updateOrCreate(
                        ['user_id' => $account->user_id, 'platform' => 'tiktok', 'ad_campaign_id' => (string) $c['campaign_id']],
                        [
                            'social_account_id' => $account->id,
                            'name' => $c['campaign_name'] ?? (string) $c['campaign_id'],
                            'objective' => $c['objective_type'] ?? null,
                            'budget_mode' => $c['budget_mode'] ?? null,
                            'budget' => isset($c['budget']) && ($c['budget_mode'] ?? '') !== 'BUDGET_MODE_INFINITE' ? (float) $c['budget'] : null,
                            'status' => ($c['operation_status'] ?? '') === 'ENABLE',
                        ]
                    );
                    $synced++;
                }
                if ($page >= (int) ($data['page_info']['total_page'] ?? 1)) {
                    break;
                }
            }
        } catch (TiktokApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $account->syncAdDetails(['last_synced_at' => now()]);

        return ['success' => true, 'data' => ['synced' => $synced]];
    }

    /** @return array<int|string, array> rows keyed by local campaign id (TikTok id when not imported) */
    private function campaignRows(Carbon $since, Carbon $until): array
    {
        [$rows, $extra] = $this->report($since, $until, 'AUCTION_CAMPAIGN', ['campaign_id']);
        $local = AdCampaign::where('user_id', $this->api->account->user_id)->where('platform', 'tiktok')->whereNotNull('ad_campaign_id')
            ->get(['id', 'ad_campaign_id', 'objective'])->keyBy('ad_campaign_id');

        $out = [];
        foreach ($rows as $r) {
            $remote = (string) ($r['dimensions']['campaign_id'] ?? '');
            $campaign = $local[$remote] ?? null;
            $m = $this->metrics($r);
            [$type, $metric] = self::RESULTS[strtoupper((string) $campaign?->objective)] ?? [null, null];
            // Video views are only known when TikTok accepted the extra metrics.
            if ($metric === 'video_play_actions' && ! $extra) {
                [$type, $metric] = [null, null];
            }
            $results = $metric ? $m[$metric] : null;

            $out[$campaign?->id ?? $remote] = $this->insightRow(['conversions' => $m['conversion']] + $m, $type, $results);
        }

        return $out;
    }

    /** Daily series, account totals (reach de-duplicated), audience breakdowns and today's spend. */
    private function details(Carbon $since, Carbon $until, ?string $resultType): array
    {
        $part = function (callable $build) {
            try {
                return $build();
            } catch (TiktokApiException $e) {
                if ($e->isAuthError()) {
                    throw $e;
                }
                Log::info('TikTok insight breakdown unavailable', ['account' => $this->api->account->id, 'error' => $e->getMessage()]);

                return null;
            }
        };
        $resultOf = fn (array $m) => match ($resultType) {
            'link_click' => (float) $m['clicks'],
            'conversion' => (float) $m['conversion'],
            'video_view' => $m['video_play_actions'],
            default => null, // reach can't be added up per day; mixed results aren't comparable
        };

        $daily = $part(function () use ($since, $until, $resultOf) {
            $byDay = [];
            for ($from = $since->copy(); $from->lte($until); $from->addDays(self::DAY_SPAN)) {
                $to = $from->copy()->addDays(self::DAY_SPAN - 1)->min($until);
                foreach ($this->report($from, $to, 'AUCTION_ADVERTISER', ['stat_time_day'])[0] as $r) {
                    $byDay[substr((string) ($r['dimensions']['stat_time_day'] ?? ''), 0, 10)] = $this->metrics($r);
                }
            }
            $series = [];
            for ($d = $since->copy(); $d->lte($until); $d->addDay()) {
                $m = $byDay[$d->toDateString()] ?? $this->metrics([]);
                $series[] = [
                    'date' => $d->toDateString(),
                    'spend' => $m['spend'],
                    'results' => $resultOf($m),
                    'impressions' => $m['impressions'],
                    'link_clicks' => $m['clicks'],
                    'purchase_value' => $m['purchase_value'],
                    'conversions' => $m['conversion'],
                ];
            }

            return $series;
        });

        $account = $part(function () use ($since, $until) {
            $m = $this->metrics($this->report($since, $until, 'AUCTION_ADVERTISER', ['advertiser_id'])[0][0] ?? []);

            return ['spend' => $m['spend'], 'impressions' => $m['impressions'], 'reach' => $m['reach'], 'link_clicks' => $m['clicks'],
                'purchase_value' => $m['purchase_value'], 'conversions' => $m['conversion']];
        });

        $audience = fn (string $dimension, callable $label) => $part(fn () => collect($this->report($since, $until, 'AUCTION_ADVERTISER', ['advertiser_id', $dimension], 'AUDIENCE')[0])
            ->map(function ($r) use ($dimension, $label, $resultOf) {
                $m = $this->metrics($r);

                return ['key' => $label((string) ($r['dimensions'][$dimension] ?? '')), 'spend' => $m['spend'], 'impressions' => $m['impressions'],
                    'results' => $resultOf($m), 'purchase_value' => $m['purchase_value']];
            })->filter(fn ($r) => $r['key'] !== '')->sortByDesc('spend')->values()->all());

        $today = $part(fn () => $this->metrics($this->report(today(), today(), 'AUCTION_ADVERTISER', ['advertiser_id'], 'BASIC', ['spend'])[0][0] ?? [])['spend']);

        return [
            'result_type' => $resultType,
            'daily' => $daily,
            'funnel' => null,
            'placements' => $audience('placement', fn ($v) => $v),
            'regions' => $audience('country_code', fn ($v) => $v),
            'ages' => $audience('age', fn ($v) => self::ageLabel($v)),
            'account' => $account,
            'today_spend' => $today,
        ];
    }

    /** AGE_18_24 → 18-24, AGE_55_100 → 55+. */
    public static function ageLabel(string $value): string
    {
        if (! preg_match('/^AGE_(\d+)_(\d+)$/', $value, $m)) {
            return $value === 'NONE' ? '' : $value;
        }

        return (int) $m[2] >= 100 ? $m[1] . '+' : $m[1] . '-' . $m[2];
    }

    /**
     * Every page of one report. Returns [rows, whether the extra metrics were accepted].
     *
     * @return array{0: array<int, array>, 1: bool}
     */
    private function report(Carbon $since, Carbon $until, string $level, array $dimensions, string $type = 'BASIC', ?array $metrics = null): array
    {
        $run = function (array $metrics) use ($since, $until, $level, $dimensions, $type) {
            $rows = [];
            for ($page = 1; $page <= 10; $page++) {
                $data = $this->api->get('report/integrated/get/', [
                    'report_type' => $type,
                    'service_type' => 'AUCTION',
                    'data_level' => $level,
                    'dimensions' => $dimensions,
                    'metrics' => $metrics,
                    'start_date' => $since->toDateString(),
                    'end_date' => $until->toDateString(),
                    'page' => $page,
                    'page_size' => 1000,
                ]);
                $rows = array_merge($rows, $data['list'] ?? []);
                if ($page >= (int) ($data['page_info']['total_page'] ?? 1)) {
                    break;
                }
            }

            return $rows;
        };

        if ($metrics !== null) {
            return [$run($metrics), false];
        }
        // Audience reports don't carry reach.
        $core = $type === 'AUDIENCE' ? array_values(array_diff(self::CORE, ['reach'])) : self::CORE;

        try {
            return [$run(array_merge($core, self::EXTRA)), true];
        } catch (TiktokApiException $e) {
            if ($e->isAuthError() || $e->isRateLimited()) {
                throw $e;
            }

            return [$run($core), false];
        }
    }

    /** One report row's metrics as numbers (TikTok sends them as strings). */
    private function metrics(array $row): array
    {
        $m = $row['metrics'] ?? [];
        $num = fn (string $k) => isset($m[$k]) && is_numeric($m[$k]) ? (float) $m[$k] : 0.0;
        $spend = round($num('spend'), 2);

        return [
            'spend' => $spend,
            'impressions' => (int) $num('impressions'),
            'clicks' => (int) $num('clicks'),
            'reach' => (int) $num('reach'),
            'conversion' => (int) $num('conversion'),
            'video_play_actions' => isset($m['video_play_actions']) ? (float) $num('video_play_actions') : null,
            // TikTok reports purchase ROAS, not the value; the value is ROAS × spend.
            'purchase_value' => round($num('complete_payment_roas') * $spend, 2),
        ];
    }
}
