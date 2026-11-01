<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Services\AdServices\Concerns\BuildsCampaignInsights;
use App\Support\Ads\SnapchatAdsApi;
use App\Support\Ads\SnapchatApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reporting and campaign import for the Snapchat campaigns page, read from
 * the seller's selected Snap ad account with the Measurement API
 * (GET adaccounts/{id}/stats - developers.snap.com/api/marketing-api/Ads-API/measurement)
 * and GET adaccounts/{id}/campaigns. The result has the same shape as
 * FacebookAdService::insights(), so MetaCampaigns.vue renders both.
 *
 * Snap rules followed here: money is micro-currency (÷ 1,000,000); DAY
 * stats start and end on the ad account's own day boundary and span at most
 * 31 days per request; ages and countries come from report_dimension.
 * Snap has no placement breakdown, so there is none. Results follow the
 * campaign's objective and are left empty where Snap has no matching
 * metric here - nothing is estimated.
 */
class SnapchatInsightsService
{
    use BuildsCampaignInsights;

    private const DAY_SPAN = 30;

    private const CORE = ['impressions', 'swipes', 'spend'];

    /** Asked for alongside the core; dropped (and the call retried) if Snap rejects them. */
    private const EXTRA = ['uniques', 'video_views', 'conversion_purchases', 'conversion_purchases_value'];

    /** Result per objective (objective_v2_type): [result_type, metric]. */
    private const RESULTS = [
        'AWARENESS_AND_ENGAGEMENT' => ['reach', 'reach'],
        'TRAFFIC' => ['link_click', 'clicks'],
        'SALES' => ['purchase', 'conversions'],
    ];

    public function __construct(private SnapchatAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = SnapchatAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function insights(int $days = 30, bool $fresh = false): array
    {
        $account = $this->api->account;
        $key = 'snap_insights:v1:' . $account->id . ':' . $days . ':' . now()->toDateString();
        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(15), function () use ($days, $account) {
                $until = now($this->timezone())->subDay()->startOfDay();
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
                    'platform' => 'snapchat',
                    'all_placements_spend' => $totals['spend'],
                    'totals' => ['current' => $totals, 'previous' => $this->insightTotals($previous)],
                    'campaigns' => $current,
                    'details' => $this->details($since, $until, $totals['result_type']),
                    'synced_at' => now()->toIso8601String(),
                ];
            });
        } catch (SnapchatApiException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'reconnect' => $e->isAuthError()];
        }
    }

    /** Imports the ad account's campaigns into ad_campaigns, for this seller and this ad account only. */
    public function syncCampaigns(): array
    {
        $account = $this->api->account;

        try {
            $campaigns = $this->api->all('adaccounts/' . $this->api->adAccountId() . '/campaigns', 'campaigns', 'campaign', [], 20);
        } catch (SnapchatApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $synced = 0;
        foreach ($campaigns as $c) {
            if (empty($c['id']) || ($c['deleted'] ?? false)) {
                continue;
            }
            $daily = isset($c['daily_budget_micro']) ? $c['daily_budget_micro'] / 1e6 : null;
            $lifetime = isset($c['lifetime_spend_cap_micro']) ? $c['lifetime_spend_cap_micro'] / 1e6 : null;
            AdCampaign::updateOrCreate(
                ['user_id' => $account->user_id, 'platform' => 'snapchat', 'ad_campaign_id' => (string) $c['id']],
                [
                    'social_account_id' => $account->id,
                    'name' => $c['name'] ?? (string) $c['id'],
                    'objective' => $c['objective_v2_properties']['objective_v2_type'] ?? null,
                    'daily_budget' => $daily,
                    'budget' => $lifetime,
                    // A lifetime cap is the campaign's total budget; a daily cap alone is its daily one.
                    'budget_mode' => $lifetime !== null ? 'total' : ($daily !== null ? 'daily' : null),
                    'start_time' => isset($c['start_time']) ? Carbon::parse($c['start_time']) : null,
                    'end_time' => isset($c['end_time']) ? Carbon::parse($c['end_time']) : null,
                    'status' => ($c['status'] ?? '') === 'ACTIVE',
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

    /** @return array<int|string, array> rows keyed by local campaign id (Snap id when not imported) */
    private function campaignRows(Carbon $since, Carbon $until): array
    {
        [$body, $extra] = $this->stats($since, $until->copy()->addDay(), 'TOTAL', ['breakdown' => 'campaign']);
        $local = AdCampaign::where('user_id', $this->api->account->user_id)->where('platform', 'snapchat')->whereNotNull('ad_campaign_id')
            ->get(['id', 'ad_campaign_id', 'objective'])->keyBy('ad_campaign_id');

        $out = [];
        foreach ($body['total_stats'][0]['total_stat']['breakdown_stats']['campaign'] ?? [] as $row) {
            $remote = (string) ($row['id'] ?? '');
            $campaign = $local[$remote] ?? null;
            $m = $this->metrics($row['stats'] ?? []);
            [$type, $metric] = self::RESULTS[strtoupper((string) $campaign?->objective)] ?? [null, null];
            // Reach and purchases are only known when Snap accepted the extra fields.
            if (in_array($metric, ['reach', 'conversions'], true) && ! $extra) {
                [$type, $metric] = [null, null];
            }

            $out[$campaign?->id ?? $remote] = $this->insightRow($m, $type, $metric ? $m[$metric] : null);
        }

        return $out;
    }

    /** Daily series, account totals (reach de-duplicated), ages, countries and today's spend. */
    private function details(Carbon $since, Carbon $until, ?string $resultType): array
    {
        $part = function (callable $build) {
            try {
                return $build();
            } catch (SnapchatApiException $e) {
                if ($e->isAuthError()) {
                    throw $e;
                }
                Log::info('Snapchat insight breakdown unavailable', ['account' => $this->api->account->id, 'error' => $e->getMessage()]);

                return null;
            }
        };
        $resultOf = fn (array $m) => match ($resultType) {
            'link_click' => (float) $m['clicks'],
            'purchase' => (float) $m['conversions'],
            default => null, // reach can't be added up per day; mixed results aren't comparable
        };

        $daily = $part(function () use ($since, $until, $resultOf) {
            $byDay = [];
            for ($from = $since->copy(); $from->lte($until); $from->addDays(self::DAY_SPAN)) {
                $to = $from->copy()->addDays(self::DAY_SPAN - 1)->min($until);
                $body = $this->stats($from, $to->copy()->addDay(), 'DAY', [], ['uniques'])[0];
                foreach ($body['timeseries_stats'][0]['timeseries_stat']['timeseries'] ?? [] as $point) {
                    $byDay[substr((string) ($point['start_time'] ?? ''), 0, 10)] = $this->metrics($point['stats'] ?? []);
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
                    'conversions' => $m['conversions'],
                ];
            }

            return $series;
        });

        $end = $until->copy()->addDay();
        $account = $part(function () use ($since, $end) {
            $m = $this->metrics($this->stats($since, $end, 'TOTAL')[0]['total_stats'][0]['total_stat']['stats'] ?? []);

            return ['spend' => $m['spend'], 'impressions' => $m['impressions'], 'reach' => $m['reach'], 'link_clicks' => $m['clicks'],
                'purchase_value' => $m['purchase_value'], 'conversions' => $m['conversions']];
        });

        $dimension = fn (string $dimension, string $field, callable $label) => $part(function () use ($since, $end, $dimension, $field, $label, $resultOf) {
            $stat = $this->stats($since, $end, 'TOTAL', ['report_dimension' => $dimension], ['uniques', 'video_views'])[0]['total_stats'][0]['total_stat'] ?? [];
            // Account level lists dimension_stats directly; with a breakdown they sit under each campaign.
            $rows = $stat['dimension_stats'] ?? collect($stat['breakdown_stats'] ?? [])->flatten(1)->pluck('dimension_stats')->flatten(1)->all();

            return collect($rows)->groupBy(fn ($r) => $label((string) ($r[$field] ?? '')))
                ->reject(fn ($group, $key) => $key === '')
                ->map(function ($group, $key) use ($resultOf) {
                    $rows = $group->map(fn ($r) => $this->metrics($r));
                    $m = array_map(fn ($k) => $rows->sum($k), array_combine(array_keys($this->metrics([])), array_keys($this->metrics([]))));

                    return ['key' => $key, 'spend' => round($m['spend'], 2), 'impressions' => (int) $m['impressions'], 'results' => $resultOf($m), 'purchase_value' => round($m['purchase_value'], 2)];
                })->sortByDesc('spend')->values()->all();
        });

        $today = $part(function () {
            $start = now($this->timezone())->startOfDay();
            $endHour = now($this->timezone())->startOfHour();
            if ($endHour->lte($start)) {
                return 0.0;
            }

            return $this->metrics($this->stats($start, $endHour, 'TOTAL', [], [], ['spend'])[0]['total_stats'][0]['total_stat']['stats'] ?? [])['spend'];
        });

        return [
            'result_type' => $resultType,
            'daily' => $daily,
            'funnel' => null,
            'placements' => null,
            'regions' => $dimension('country', 'country', fn ($v) => strtoupper($v)),
            'ages' => $dimension('age', 'age_bucket', fn ($v) => $v),
            'account' => $account,
            'today_spend' => $today,
        ];
    }

    /**
     * One stats request for the ad account; end is exclusive. Returns [body, whether the extra fields were accepted].
     *
     * @return array{0: array, 1: bool}
     */
    private function stats(Carbon $start, Carbon $end, string $granularity, array $query = [], array $without = [], ?array $fields = null): array
    {
        $tz = $this->timezone();
        $run = fn (array $fields) => $this->api->get('adaccounts/' . $this->api->adAccountId() . '/stats', $query + [
            'granularity' => $granularity,
            'fields' => implode(',', $fields),
            // Snap wants DAY boundaries in the ad account's timezone.
            'start_time' => $start->copy()->setTimezone($tz)->format('Y-m-d\TH:i:s.vP'),
            'end_time' => $end->copy()->setTimezone($tz)->format('Y-m-d\TH:i:s.vP'),
        ]);

        if ($fields !== null) {
            return [$run($fields), false];
        }

        try {
            return [$run(array_merge(self::CORE, array_values(array_diff(self::EXTRA, $without)))), true];
        } catch (SnapchatApiException $e) {
            if ($e->isAuthError() || $e->isRateLimited()) {
                throw $e;
            }

            return [$run(self::CORE), false];
        }
    }

    /** One stats object as numbers in currency units. */
    private function metrics(array $s): array
    {
        $num = fn (string $k) => isset($s[$k]) && is_numeric($s[$k]) ? (float) $s[$k] : 0.0;

        return [
            'spend' => round($num('spend') / 1e6, 2),
            'impressions' => (int) $num('impressions'),
            'clicks' => (int) $num('swipes'),
            'reach' => (int) $num('uniques'),
            'conversions' => (int) $num('conversion_purchases'),
            'purchase_value' => round($num('conversion_purchases_value') / 1e6, 2),
        ];
    }
}
