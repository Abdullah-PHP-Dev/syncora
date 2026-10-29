<?php

namespace App\Services\AdServices;

use App\Mail\AdPerformanceReport;
use App\Models\Admin\AdCampaign;
use App\Models\AdReportSchedule;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Facebook performance reports: the CSV export and the scheduled email,
 * both built from the same Meta insights the campaigns page shows.
 */
class FacebookReportService
{
    /** @return array{success: bool, error?: string, insights?: array, summary?: array, campaigns?: array, csv?: string} */
    public function build(FacebookAdService $facebook, int $userId, int $days): array
    {
        $insights = $facebook->insights($days);
        if (!($insights['success'] ?? false)) {
            return ['success' => false, 'error' => $insights['error'] ?? 'Insights from Meta aren’t available.'];
        }

        $names = AdCampaign::where('user_id', $userId)->whereIn('id', array_keys($insights['campaigns']))->pluck('name', 'id');
        $campaigns = collect($insights['campaigns'])->map(fn ($c, $id) => $c + ['name' => $names[$id] ?? ('ID ' . $id), 'id' => $id])
            ->sortByDesc('spend')->values()->all();

        return [
            'success' => true,
            'insights' => $insights,
            'summary' => $this->summary($insights),
            'campaigns' => $campaigns,
            'csv' => $this->csv($insights, $campaigns),
        ];
    }

    /** Headline numbers with the change against the previous period. */
    private function summary(array $insights): array
    {
        $c = $insights['totals']['current'];
        $p = $insights['totals']['previous'] ?? null;
        $change = fn ($cur, $prev) => ($prev && $cur !== null) ? round((($cur - $prev) / $prev) * 100, 1) : null;

        return [
            'spend' => $c['spend'], 'spend_change' => $change($c['spend'], $p['spend'] ?? null),
            'impressions' => $c['impressions'], 'reach' => $c['reach'] ?? null, 'clicks' => $c['clicks'],
            'ctr' => $c['ctr'], 'cpc' => $c['cpc'],
            'cpm' => $c['impressions'] ? round($c['spend'] / $c['impressions'] * 1000, 2) : null,
            'results' => $c['results'], 'result_type' => $c['result_type'],
            'results_change' => $change($c['results'], $p['results'] ?? null),
            'cost_per_result' => $c['cost_per_result'],
        ];
    }

    /** UTF-8 CSV (BOM for Excel and Arabic), spreadsheet-formula safe. */
    public function csv(array $insights, array $campaigns): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        $row = fn (array $cells) => fputcsv($out, array_map(fn ($v) => $this->cell($v), $cells), ',', '"', '');
        $t = fn ($key) => (string) __('ads_campaigns.report.csv.' . $key);
        $c = $insights['totals']['current'];

        $row([$t('period'), $insights['since'] . ' - ' . $insights['until'], $t('currency'), $insights['currency'] ?? '']);
        $row([]);
        $row([$t('metric'), $t('value')]);
        foreach (['spend', 'impressions', 'reach', 'clicks', 'results', 'cost_per_result'] as $key) {
            $row([$t($key), $c[$key] ?? '']);
        }
        $row([$t('ctr'), $c['ctr'] !== null ? round($c['ctr'] * 100, 2) . '%' : '']);
        $row([$t('result_type'), $c['result_type'] ?? '']);
        $row([]);

        $row([$t('campaign'), $t('spend'), $t('impressions'), $t('clicks'), $t('ctr'), $t('results'), $t('result_type'), $t('cost_per_result')]);
        foreach ($campaigns as $r) {
            $row([$r['name'], $r['spend'], $r['impressions'], $r['clicks'], $r['ctr'] !== null ? round($r['ctr'] * 100, 2) . '%' : '', $r['results'], $r['result_type'], $r['cost_per_result']]);
        }

        foreach (['placements' => 'placement', 'regions' => 'region', 'ages' => 'age'] as $section => $label) {
            $rows = $insights['details'][$section] ?? null;
            if (!$rows) {
                continue;
            }
            $row([]);
            $row([$t($label), $t('spend'), $t('impressions'), $t('results')]);
            foreach ($rows as $r) {
                $row([str_replace('|', ' / ', $r['key']), $r['spend'], $r['impressions'] ?? '', $r['results']]);
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = is_float($value) ? (string) round($value, 4) : (string) $value;

        // Spreadsheet formula injection guard (OWASP CSV injection).
        return preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'" . $value : $value;
    }

    /**
     * Build and email one schedule now, as its owner on its own ad account.
     * Records the outcome on the schedule; returns it for the caller.
     */
    public function deliver(AdReportSchedule $schedule): array
    {
        $owner = $schedule->user;
        $account = SocialAccount::where('user_id', $schedule->user_id)->find($schedule->social_account_id);
        $result = ['success' => false, 'error' => 'The ad account for this report is no longer connected.'];

        if ($owner && $account) {
            $previousUser = Auth::user();
            $previousLocale = app()->getLocale();
            try {
                Auth::guard('web')->setUser($owner);
                app()->setLocale($schedule->locale === 'ar' ? 'ar' : 'en');
                $facebook = app(FacebookAdService::class)->useAccount($account);
                $result = $this->build($facebook, $owner->id, $schedule->period_days);

                if ($result['success']) {
                    Mail::to($schedule->recipient_email)->send(new AdPerformanceReport($schedule, $account, $result));
                }
            } catch (\Throwable $e) {
                Log::warning('Ad report delivery failed', ['schedule' => $schedule->id, 'error' => $e->getMessage()]);
                $result = ['success' => false, 'error' => 'The report couldn’t be sent - please try again later.'];
            } finally {
                app()->setLocale($previousLocale);
                if ($previousUser) {
                    Auth::guard('web')->setUser($previousUser);
                }
            }
        }

        $schedule->forceFill($result['success']
            ? ['last_sent_at' => now(), 'last_error' => null]
            : ['last_error' => mb_substr($result['error'] ?? 'Unknown error', 0, 500)])->save();

        return $result;
    }
}
