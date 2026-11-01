<?php

namespace App\Services\AdServices\Concerns;

/**
 * Rows and totals in the shape of FacebookAdService::insights(), for
 * platforms whose report API gives spend, impressions, clicks, reach,
 * conversions and purchase value (TikTok, Snapchat). MetaCampaigns.vue
 * renders them unchanged.
 *
 * $m holds one row's numbers: spend, impressions, clicks, reach,
 * conversions, purchase_value (currency units, not micros).
 */
trait BuildsCampaignInsights
{
    protected function insightRow(array $m, ?string $resultType, $results): array
    {
        $spend = $m['spend'];
        $impressions = $m['impressions'];
        $clicks = $m['clicks'];

        return [
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $m['reach'],
            'clicks' => $clicks,
            'link_clicks' => $clicks,
            'ctr' => $impressions ? $clicks / $impressions : null,
            'cpc' => $clicks ? round($spend / $clicks, 2) : null,
            'link_ctr' => $impressions ? $clicks / $impressions : null,
            'link_cpc' => $clicks ? round($spend / $clicks, 2) : null,
            'cpm' => $impressions ? round($spend / $impressions * 1000, 2) : null,
            'results' => $results,
            'result_type' => $resultType,
            'cost_per_result' => $results ? round($spend / $results, 2) : null,
            'conversions' => $m['conversions'],
            'purchase_value' => $m['purchase_value'],
            'roas' => $spend > 0 && $m['purchase_value'] > 0 ? round($m['purchase_value'] / $spend, 2) : null,
        ];
    }

    /** Totals; results only add up when every campaign counts the same result. */
    protected function insightTotals(array $rows): array
    {
        $rows = collect($rows);
        $spend = round($rows->sum('spend'), 2);
        $types = $rows->pluck('result_type')->unique()->values();
        $same = $types->count() === 1 && $types->first() !== null;
        $results = $same ? $rows->sum('results') : null;
        $impressions = (int) $rows->sum('impressions');
        $clicks = (int) $rows->sum('clicks');
        $conversions = $rows->sum('conversions');
        $value = round($rows->sum('purchase_value'), 2);

        return [
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => (int) $rows->sum('reach'),
            'clicks' => $clicks,
            'ctr' => $impressions ? $clicks / $impressions : null,
            'cpc' => $clicks ? round($spend / $clicks, 2) : null,
            'results' => $results,
            'result_type' => $same ? $types->first() : ($rows->isEmpty() ? null : 'mixed'),
            'cost_per_result' => $results ? round($spend / $results, 2) : null,
            'link_clicks' => $clicks,
            'link_ctr' => $impressions ? $clicks / $impressions : null,
            'link_cpc' => $clicks ? round($spend / $clicks, 2) : null,
            'cpm' => $impressions ? round($spend / $impressions * 1000, 2) : null,
            'purchase_value' => $value,
            'conversions' => $conversions,
            'cvr' => $clicks ? $conversions / $clicks : null,
            'roas' => $spend > 0 && $value > 0 ? round($value / $spend, 2) : null,
        ];
    }
}
