<?php

namespace App\Services\GoogleSuite;

use App\Services\AdServices\GoogleAdService;
use App\Support\Google\GoogleApiException;
use App\Support\Google\ReportRange;
use Illuminate\Support\Facades\Log;

/**
 * Google Ads results for the Google Suite: spend, clicks, conversions and
 * value as Google Ads reports them, read with GAQL through the ad account
 * already connected in the Connection Hub (the seller's selected one).
 * Conversions and value come from the account's own conversion tracking -
 * nothing here is modelled or attributed by SocialEaz.
 */
class GoogleAdsReportService
{
    private const METRICS = 'metrics.cost_micros, metrics.clicks, metrics.impressions, metrics.conversions, metrics.conversions_value';

    /** @return array{data?: array, error?: array, fetched_at?: string} */
    public function dashboard(int $userId, ReportRange $range, bool $fresh = false): array
    {
        $ads = app(GoogleAdService::class);
        $account = $ads->account();

        try {
            if (! $account) {
                throw new GoogleApiException(GoogleApiException::ADS_NOT_CONNECTED);
            }

            return GoogleReportCache::remember($userId, 'ads:' . $account->id . ':' . $range->cacheKey(), $fresh, fn () => [
                'data' => $this->build($ads, $range) + ['account' => [
                    'name' => $account->name ?: $account->username,
                    'customer_id' => $account->platform_account_id,
                ]],
                'fetched_at' => now()->toIso8601String(),
            ]);
        } catch (GoogleApiException $e) {
            return ['error' => $e->toArray()];
        }
    }

    private function build(GoogleAdService $ads, ReportRange $range): array
    {
        [$previousStart] = $range->previous();
        [, $end] = $range->current();

        $daily = $this->query($ads, "SELECT customer.currency_code, segments.date, " . self::METRICS
            . " FROM customer WHERE segments.date BETWEEN '{$previousStart}' AND '{$end}'");

        [$start] = $range->current();
        $campaigns = $this->query($ads, "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, " . self::METRICS
            . " FROM campaign WHERE segments.date BETWEEN '{$start}' AND '{$end}' AND campaign.status != 'REMOVED'"
            . ' ORDER BY metrics.cost_micros DESC LIMIT 25');

        // Whether conversions can be measured at all; a lookup failure only hides the hint.
        try {
            $tracking = count($this->query($ads, "SELECT conversion_action.id FROM conversion_action WHERE conversion_action.status = 'ENABLED' LIMIT 1")) > 0;
        } catch (GoogleApiException $e) {
            $tracking = null;
        }

        $byDate = [];
        foreach ($daily as $row) {
            $byDate[$row['segments']['date'] ?? ''] = $this->metrics($row['metrics'] ?? []);
        }

        $trend = ['dates' => []];
        foreach (['cost', 'clicks', 'conversions'] as $metric) {
            $trend[$metric] = ['current' => [], 'previous' => []];
        }
        $totals = ['current' => $this->metrics([]), 'previous' => $this->metrics([])];

        for ($i = 0; $i < $range->days(); $i++) {
            $day = $range->start->copy()->addDays($i)->toDateString();
            $previousDay = $range->previousStart->copy()->addDays($i)->toDateString();
            $trend['dates'][] = $day;

            foreach (['current' => $day, 'previous' => $previousDay] as $period => $date) {
                $values = $byDate[$date] ?? $this->metrics([]);
                foreach (['cost', 'clicks', 'conversions'] as $metric) {
                    $trend[$metric][$period][] = $values[$metric];
                }
                foreach (['cost', 'clicks', 'impressions', 'conversions', 'conversions_value'] as $metric) {
                    $totals[$period][$metric] += $values[$metric];
                }
            }
        }

        return [
            'currency' => $daily[0]['customer']['currencyCode'] ?? null,
            'totals' => ['current' => $this->derived($totals['current']), 'previous' => $this->derived($totals['previous'])],
            'trend' => $trend,
            'campaigns' => array_map(fn ($row) => [
                'id' => $row['campaign']['id'] ?? null,
                'name' => $row['campaign']['name'] ?? '',
                'status' => $row['campaign']['status'] ?? null,
                'channel' => $row['campaign']['advertisingChannelType'] ?? null,
            ] + $this->derived($this->metrics($row['metrics'] ?? [])), $campaigns),
            'conversion_tracking' => $tracking,
        ];
    }

    /** GAQL returns int64 as strings and omits zero metrics. */
    private function metrics(array $m): array
    {
        return [
            'cost' => round(((float) ($m['costMicros'] ?? 0)) / 1_000_000, 2),
            'clicks' => (int) ($m['clicks'] ?? 0),
            'impressions' => (int) ($m['impressions'] ?? 0),
            'conversions' => round((float) ($m['conversions'] ?? 0), 2),
            'conversions_value' => round((float) ($m['conversionsValue'] ?? 0), 2),
        ];
    }

    /** Ratios only where the denominator exists - never a fabricated 0. */
    private function derived(array $t): array
    {
        return $t + [
            'ctr' => $t['impressions'] > 0 ? $t['clicks'] / $t['impressions'] : null,
            'cpc' => $t['clicks'] > 0 ? round($t['cost'] / $t['clicks'], 2) : null,
            'conversion_rate' => $t['clicks'] > 0 ? $t['conversions'] / $t['clicks'] : null,
            'cost_per_conversion' => $t['conversions'] > 0 ? round($t['cost'] / $t['conversions'], 2) : null,
            'roas' => $t['cost'] > 0 && $t['conversions_value'] > 0 ? round($t['conversions_value'] / $t['cost'], 2) : null,
        ];
    }

    private function query(GoogleAdService $ads, string $gaql): array
    {
        $response = $ads->report($gaql);

        if ($response['success'] ?? false) {
            return $response['data'] ?? [];
        }

        if (($response['error'] ?? null) === 'reauth') {
            throw new GoogleApiException(GoogleApiException::ADS_NOT_CONNECTED);
        }

        Log::warning('Google Suite: Google Ads report failed', ['error' => $response['error'] ?? null]);

        throw new GoogleApiException(GoogleApiException::ADS_FAILED);
    }
}
