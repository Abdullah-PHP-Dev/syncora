<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Services\AdServices\Concerns\BuildsCampaignInsights;
use App\Support\Ads\XAdsApi;
use App\Support\Ads\XApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reporting and campaign import for the X campaigns page, from the
 * synchronous analytics endpoint (GET stats/accounts/:account_id, Ads API
 * v12 - docs.x.com/x-ads-api/analytics) and GET accounts/:id/campaigns. The
 * result has the same shape as FacebookAdService::insights(), so
 * MetaCampaigns.vue renders it.
 *
 * X rules followed here: at most 7 days and 20 entities per request, DAY
 * ranges start and end on the account's midnight, money is local micro.
 * Reach, ages and locations exist only in X's asynchronous reports, and
 * placements one request each, so they are left empty (null) rather than
 * estimated. Purchase value isn't read: its sub-field units aren't
 * documented on the analytics page.
 */
class XInsightsService
{
    use BuildsCampaignInsights;

    private const SPAN_DAYS = 7;

    private const BATCH = 20;

    private const CORE = ['ENGAGEMENT', 'BILLING', 'VIDEO'];

    /** Asked for alongside the core; dropped (and the call retried) if X rejects it. */
    private const EXTRA = ['WEB_CONVERSION'];

    /** Result per line item objective (XCampaignBuilder::OBJECTIVES): [result_type, metric]. */
    private const RESULTS = [
        'WEBSITE_CLICKS' => ['link_click', 'clicks'],
        'ENGAGEMENTS' => ['post_engagement', 'engagements'],
        'VIDEO_VIEWS' => ['video_view', 'video_views'],
    ];

    public function __construct(private XAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = XAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function insights(int $days = 30, bool $fresh = false): array
    {
        $account = $this->api->account;
        $key = 'x_insights:v1:' . $account->id . ':' . $days . ':' . now()->toDateString();
        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(15), function () use ($days, $account) {
                $until = now($this->timezone())->subDay()->startOfDay();
                $since = $until->copy()->subDays($days - 1);
                $local = AdCampaign::where('user_id', $account->user_id)->where('platform', 'x')->whereNotNull('ad_campaign_id')
                    ->where(fn ($q) => $q->where('social_account_id', $account->id)->orWhereNull('social_account_id'))
                    ->get(['id', 'ad_campaign_id', 'objective'])->keyBy('ad_campaign_id');

                [$byCampaign] = $this->series('CAMPAIGN', $local->keys()->map(fn ($id) => (string) $id)->all(), $since, $until);
                $current = $this->campaignRows($byCampaign, $local);
                $previous = $this->campaignRows($this->series('CAMPAIGN', $local->keys()->map(fn ($id) => (string) $id)->all(), $since->copy()->subDays($days), $since->copy()->subDay())[0], $local);
                $totals = $this->insightTotals($current);

                $account->forceFill(['metadata' => array_merge($account->metadata ?? [], ['insights_synced_at' => now()->toIso8601String()])])->save();

                return [
                    'success' => true,
                    'days' => $days,
                    'since' => $since->toDateString(),
                    'until' => $until->toDateString(),
                    'currency' => $account->adDetails->currency ?? ($account->metadata['currency'] ?? null),
                    'platform' => 'x',
                    'all_placements_spend' => $totals['spend'],
                    'totals' => ['current' => $totals, 'previous' => $this->insightTotals($previous)],
                    'campaigns' => $current,
                    'details' => $this->details($since, $until, $totals['result_type']),
                    'synced_at' => now()->toIso8601String(),
                ];
            });
        } catch (XApiException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'reconnect' => $e->isAuthError()];
        }
    }

    /** Imports the ad account's campaigns (with their line item objective), for this seller and this ad account only. */
    public function syncCampaigns(): array
    {
        $account = $this->api->account;

        try {
            $lineItems = collect($this->api->all($this->api->path('line_items'), ['count' => 1000], 5));
            $campaigns = $this->api->all($this->api->path('campaigns'), ['count' => 1000], 5);
        } catch (XApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
        $objectives = $lineItems->groupBy('campaign_id')->map(fn ($items) => $items->first()['objective'] ?? null);

        $synced = 0;
        foreach ($campaigns as $c) {
            if (empty($c['id']) || ($c['deleted'] ?? false)) {
                continue;
            }
            $daily = isset($c['daily_budget_amount_local_micro']) ? $c['daily_budget_amount_local_micro'] / 1e6 : null;
            $total = isset($c['total_budget_amount_local_micro']) ? $c['total_budget_amount_local_micro'] / 1e6 : null;
            AdCampaign::updateOrCreate(
                ['user_id' => $account->user_id, 'platform' => 'x', 'ad_campaign_id' => (string) $c['id']],
                [
                    'social_account_id' => $account->id,
                    'name' => $c['name'] ?? (string) $c['id'],
                    'objective' => $objectives[$c['id']] ?? null,
                    'funding_instrument_id' => $c['funding_instrument_id'] ?? null,
                    'daily_budget' => $daily,
                    'budget' => $total,
                    // A total budget is the campaign's lifetime budget; a daily one alone is its daily budget.
                    'budget_mode' => $total !== null ? 'total' : ($daily !== null ? 'daily' : null),
                    'status' => ($c['entity_status'] ?? '') === 'ACTIVE',
                ]
            );
            $synced++;
        }

        $account->syncAdDetails(['last_synced_at' => now()]);

        return ['success' => true, 'data' => ['synced' => $synced]];
    }

    private function timezone(): string
    {
        $tz = $this->api->account->adDetails->timezone ?? null;

        return $tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    /** Rows keyed by local campaign id, summed over the period. */
    private function campaignRows(array $byCampaign, $local): array
    {
        $out = [];
        foreach ($byCampaign as $remote => ['metrics' => $m, 'extra' => $extra]) {
            $campaign = $local[$remote] ?? null;
            [$type, $metric] = self::RESULTS[strtoupper((string) $campaign?->objective)] ?? [null, null];

            $out[$campaign?->id ?? $remote] = $this->insightRow($m, $type, $metric ? (float) $m[$metric] : null);
        }

        return $out;
    }

    /** Daily account series and today's spend; reach, ages, locations and placements aren't in X's synchronous stats. */
    private function details(Carbon $since, Carbon $until, ?string $resultType): array
    {
        $part = function (callable $build) {
            try {
                return $build();
            } catch (XApiException $e) {
                if ($e->isAuthError()) {
                    throw $e;
                }
                Log::info('X insight part unavailable', ['account' => $this->api->account->id, 'error' => $e->getMessage()]);

                return null;
            }
        };

        $accountSeries = $part(fn () => $this->series('ACCOUNT', [$this->api->accountId()], $since, $until));
        $daily = null;
        $account = null;
        if ($accountSeries) {
            [$byEntity, $days] = $accountSeries;
            $daily = collect($days)->map(fn ($m, $date) => [
                'date' => $date,
                'spend' => $m['spend'],
                'results' => match ($resultType) {
                    'link_click' => (float) $m['clicks'],
                    'post_engagement' => (float) $m['engagements'],
                    'video_view' => (float) $m['video_views'],
                    default => null,
                },
                'impressions' => $m['impressions'],
                'link_clicks' => $m['clicks'],
                'purchase_value' => 0.0,
                'conversions' => $m['conversions'],
            ])->values()->all();
            $m = $byEntity[$this->api->accountId()]['metrics'] ?? $this->metrics([], 0);
            $account = ['spend' => $m['spend'], 'impressions' => $m['impressions'], 'reach' => null, 'link_clicks' => $m['clicks'],
                'purchase_value' => 0.0, 'conversions' => $m['conversions']];
        }

        $today = $part(function () {
            $start = now($this->timezone())->startOfDay();
            $end = now($this->timezone())->startOfHour();
            if ($end->lte($start)) {
                return 0.0;
            }
            $data = $this->stats('ACCOUNT', [$this->api->accountId()], $start, $end, 'TOTAL', ['BILLING']);

            return $this->metrics($data[0]['id_data'][0]['metrics'] ?? [], 0)['spend'];
        });

        return [
            'result_type' => $resultType,
            'daily' => $daily,
            'funnel' => null,
            'placements' => null,
            'regions' => null,
            'ages' => null,
            'account' => $account,
            'today_spend' => $today,
        ];
    }

    /**
     * DAY stats for entities over [since, until], in 7-day requests of up to 20 entities.
     * Returns [per-entity {metrics, extra} summed over the period, per-day metrics summed over the entities].
     */
    private function series(string $entity, array $ids, Carbon $since, Carbon $until): array
    {
        $empty = $this->metrics([], 0);
        $byEntity = [];
        $byDay = [];
        for ($d = $since->copy(); $d->lte($until); $d->addDay()) {
            $byDay[$d->toDateString()] = $empty;
        }
        if (! $ids) {
            return [$byEntity, $byDay];
        }

        for ($from = $since->copy(); $from->lte($until); $from->addDays(self::SPAN_DAYS)) {
            $to = $from->copy()->addDays(self::SPAN_DAYS - 1)->min($until);
            $length = (int) $from->diffInDays($to) + 1;
            foreach (array_chunk($ids, self::BATCH) as $batch) {
                [$data, $extra] = $this->statsWithExtras($entity, $batch, $from, $to->copy()->addDay());
                foreach ($data as $row) {
                    $metrics = $row['id_data'][0]['metrics'] ?? [];
                    $id = (string) ($row['id'] ?? '');
                    $total = $this->metrics($metrics, null);
                    $byEntity[$id] = [
                        'metrics' => isset($byEntity[$id]) ? $this->add($byEntity[$id]['metrics'], $total) : $total,
                        'extra' => $extra && ($byEntity[$id]['extra'] ?? true),
                    ];
                    for ($i = 0; $i < $length; $i++) {
                        $date = $from->copy()->addDays($i)->toDateString();
                        $byDay[$date] = $this->add($byDay[$date], $this->metrics($metrics, $i));
                    }
                }
            }
        }

        return [$byEntity, $byDay];
    }

    /** @return array{0: array, 1: bool} data, whether WEB_CONVERSION was accepted */
    private function statsWithExtras(string $entity, array $ids, Carbon $start, Carbon $end): array
    {
        try {
            return [$this->stats($entity, $ids, $start, $end, 'DAY', array_merge(self::CORE, self::EXTRA)), true];
        } catch (XApiException $e) {
            if ($e->isAuthError() || $e->isRateLimited()) {
                throw $e;
            }

            return [$this->stats($entity, $ids, $start, $end, 'DAY', self::CORE), false];
        }
    }

    /** One synchronous stats request; end is exclusive and both are whole hours (UTC). */
    private function stats(string $entity, array $ids, Carbon $start, Carbon $end, string $granularity, array $groups): array
    {
        return $this->api->get('stats/accounts/' . $this->api->accountId(), [
            'entity' => $entity,
            'entity_ids' => implode(',', $ids),
            'start_time' => $start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'end_time' => $end->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'granularity' => $granularity,
            'metric_groups' => implode(',', $groups),
            'placement' => 'ALL_ON_TWITTER',
        ])['data'] ?? [];
    }

    /** One metrics object as numbers: the value at $index of each series, or the sum of the series when $index is null. */
    private function metrics(array $m, ?int $index): array
    {
        $at = function ($series) use ($index) {
            if (! is_array($series)) {
                return is_numeric($series) ? (float) $series : 0.0;
            }

            return $index === null ? (float) array_sum(array_filter($series, 'is_numeric')) : (float) (is_numeric($series[$index] ?? null) ? $series[$index] : 0);
        };
        $purchases = $m['conversion_purchases'] ?? null;

        return [
            'spend' => round($at($m['billed_charge_local_micro'] ?? null) / 1e6, 2),
            'impressions' => (int) $at($m['impressions'] ?? null),
            'clicks' => (int) $at($m['url_clicks'] ?? null),
            'engagements' => (int) $at($m['engagements'] ?? null),
            'video_views' => (int) $at($m['video_total_views'] ?? null),
            'reach' => 0,
            // The purchase count, when X returns it as {metric: [...]}.
            'conversions' => (int) (is_array($purchases) && isset($purchases['metric']) ? $at($purchases['metric']) : 0),
            'purchase_value' => 0.0,
        ];
    }

    private function add(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = is_float($v) ? round($a[$k] + $v, 2) : $a[$k] + $v;
        }

        return $a;
    }
}
