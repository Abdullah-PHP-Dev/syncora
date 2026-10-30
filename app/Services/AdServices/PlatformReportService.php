<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Support\Google\ReportRange;

/**
 * CSV export and scheduled-email data for the Google, YouTube, TikTok,
 * Snapchat, LinkedIn, X and ChatGPT Ads campaigns pages - the same numbers
 * those pages show. Platforms with reporting (AdsPerformanceService) get
 * spend and results; the others get their campaigns, budgets and status,
 * and say so - nothing is estimated.
 */
class PlatformReportService
{
    /** @return array{success: bool, error?: string, kind?: string, insights?: array, metrics?: array, campaigns?: array, csv?: string} */
    public function build(string $platform, int $userId, int $days): array
    {
        $perf = (new AdsPerformanceService($userId))->forPlatform($platform, $days);
        if (in_array($perf['status'], ['error', 'not_connected'], true)) {
            return ['success' => false, 'error' => $perf['status'] === 'error' ? ($perf['error'] ?? 'Reporting isn’t available right now.') : __('ads_campaigns.reports.no_account')];
        }

        $reporting = $perf['status'] === 'ok';
        [$since, $until] = ReportRange::make($days . 'd')->current();
        $campaigns = AdCampaign::where('user_id', $userId)->where('platform', $platform)->orderByDesc('created_at')->get()
            ->map(fn (AdCampaign $c) => $this->campaignRow($c, $reporting ? ($perf['campaigns'][$c->id] ?? null) : null));
        if ($reporting) {
            $campaigns = $campaigns->sortByDesc(fn ($c) => $c['spend'] ?? -1);
        }
        $campaigns = $campaigns->values()->all();

        $insights = [
            'since' => $since,
            'until' => $until,
            'currency' => $perf['currency'] ?? null,
            'name' => $this->name($platform),
            'reporting' => $reporting,
            'has_conversions' => (bool) ($perf['has_conversions'] ?? false),
            'totals' => $reporting ? $perf['totals'] : null,
            'trend' => $reporting ? ($perf['trend'] ?? null) : null,
        ];
        $metrics = $reporting ? $this->performanceMetrics($insights) : $this->campaignMetrics($campaigns);

        return [
            'success' => true,
            'kind' => $reporting ? 'performance' : 'campaigns',
            'insights' => $insights,
            'metrics' => $metrics,
            'campaigns' => $campaigns,
            'csv' => $this->csv($insights, $metrics, $campaigns),
        ];
    }

    public function name(string $platform): string
    {
        $label = AdsDashboardService::PLATFORMS[$platform]['label'] ?? ucfirst($platform);

        return preg_replace('/ Ads$/', '', $label);
    }

    private function campaignRow(AdCampaign $c, ?array $m): array
    {
        $row = [
            'id' => $c->id,
            'name' => $c->name ?: 'ID ' . $c->id,
            'type' => $c->advertising_channel_type ?? $c->objective,
            'status' => strtolower((string) $c->status),
            'daily_budget' => $c->daily_budget !== null ? (float) $c->daily_budget : null,
            'lifetime_budget' => $c->budget !== null ? (float) $c->budget : null,
            'start' => optional($c->start_time)->toDateString(),
            'end' => optional($c->end_time)->toDateString(),
        ];
        if ($m) {
            $row += [
                'spend' => (float) $m['spend'],
                'impressions' => (int) $m['impressions'],
                'clicks' => (int) $m['clicks'],
                'ctr' => $m['impressions'] ? $m['clicks'] / $m['impressions'] : null,
                'cpc' => $m['clicks'] ? round($m['spend'] / $m['clicks'], 2) : null,
                'conversions' => $m['conversions'] ?? null,
                'value' => $m['value'] ?? null,
            ];
        }

        return $row;
    }

    /** Headline numbers as [key => value, change %|null]. */
    private function performanceMetrics(array $insights): array
    {
        $c = $insights['totals']['current'];
        $p = $insights['totals']['previous'] ?? null;
        $change = fn ($cur, $prev) => ($prev && $cur !== null) ? round((($cur - $prev) / $prev) * 100, 1) : null;
        $out = [
            'spend' => [$c['spend'], $change($c['spend'], $p['spend'] ?? null)],
            'impressions' => [$c['impressions'], $change($c['impressions'], $p['impressions'] ?? null)],
            'clicks' => [$c['clicks'], $change($c['clicks'], $p['clicks'] ?? null)],
            'ctr' => [$c['impressions'] ? $c['clicks'] / $c['impressions'] : null, null],
            'cpc' => [$c['clicks'] ? round($c['spend'] / $c['clicks'], 2) : null, null],
            'cpm' => [$c['impressions'] ? round($c['spend'] / $c['impressions'] * 1000, 2) : null, null],
        ];
        if ($insights['has_conversions']) {
            $out['conversions'] = [$c['conversions'], $change($c['conversions'], $p['conversions'] ?? null)];
            $out['cost_per_conversion'] = [$c['conversions'] ? round($c['spend'] / $c['conversions'], 2) : null, null];
            $out['conversion_value'] = [$c['value'], $change($c['value'], $p['value'] ?? null)];
        }

        return $out;
    }

    private function campaignMetrics(array $campaigns): array
    {
        $count = fn (string $status) => count(array_filter($campaigns, fn ($c) => in_array($c['status'], $status === 'active' ? ['active', 'enable', 'enabled', '1'] : ['paused', 'disable', 'disabled', '0', ''], true)));

        return [
            'campaigns' => [count($campaigns), null],
            'active' => [$count('active'), null],
            'paused' => [$count('paused'), null],
            'daily_budget' => [round(array_sum(array_map(fn ($c) => $c['daily_budget'] ?? 0, $campaigns)), 2), null],
            'lifetime_budget' => [round(array_sum(array_map(fn ($c) => $c['lifetime_budget'] ?? 0, $campaigns)), 2), null],
        ];
    }

    /** UTF-8 CSV (BOM for Excel and Arabic), spreadsheet-formula safe. */
    private function csv(array $insights, array $metrics, array $campaigns): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        $row = fn (array $cells) => fputcsv($out, array_map(fn ($v) => FacebookReportService::safeCell($v), $cells), ',', '"', '');
        $t = fn ($key) => (string) __('ads_campaigns.report.csv.' . $key);
        $pct = fn ($v) => $v === null ? '' : round($v * 100, 2) . '%';

        $row([$t('period'), $insights['since'] . ' - ' . $insights['until'], $t('currency'), $insights['currency'] ?? '']);
        if (!$insights['reporting']) {
            $row([__('ads_campaigns.report.no_reporting', ['name' => $insights['name']])]);
        }
        $row([]);
        $row([$t('metric'), $t('value')]);
        foreach ($metrics as $key => [$value]) {
            $row([$t($key), $key === 'ctr' ? $pct($value) : $value]);
        }

        if ($insights['trend']) {
            $row([]);
            $row([$t('date'), $t('spend'), $t('clicks'), $t('conversions')]);
            foreach ($insights['trend']['dates'] as $i => $date) {
                $row([$date, $insights['trend']['spend'][$i] ?? 0, $insights['trend']['clicks'][$i] ?? 0, $insights['trend']['conversions'][$i] ?? '']);
            }
        }

        $row([]);
        $head = [$t('campaign'), $t('type'), $t('status'), $t('daily_budget'), $t('lifetime_budget'), $t('start'), $t('end')];
        if ($insights['reporting']) {
            $head = array_merge($head, [$t('spend'), $t('impressions'), $t('clicks'), $t('ctr'), $t('cpc')], $insights['has_conversions'] ? [$t('conversions'), $t('conversion_value')] : []);
        }
        $row($head);
        foreach ($campaigns as $c) {
            $cells = [$c['name'], $c['type'], $c['status'], $c['daily_budget'], $c['lifetime_budget'], $c['start'], $c['end']];
            if ($insights['reporting']) {
                $cells = array_merge($cells, isset($c['spend']) ? [$c['spend'], $c['impressions'], $c['clicks'], $pct($c['ctr']), $c['cpc']] : ['', '', '', '', ''],
                    $insights['has_conversions'] ? [$c['conversions'] ?? '', $c['value'] ?? ''] : []);
            }
            $row($cells);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
