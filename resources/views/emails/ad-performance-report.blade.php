@php
    $cur = $insights['currency'] ?? '';
    $money = fn ($v) => $v === null ? '—' : trim($cur . ' ' . number_format((float) $v, 2));
    $int = fn ($v) => $v === null ? '—' : number_format((float) $v);
    $resultLabel = __('ads_campaigns.result_types.' . str_replace('.', '_', (string) ($summary['result_type'] ?? 'results')));
    $resultLabel = str_starts_with($resultLabel, 'ads_campaigns.') ? __('ads_campaigns.result_types.results') : $resultLabel;
    $change = fn ($v) => $v === null ? '' : ' (' . ($v >= 0 ? '+' : '') . $v . '% ' . __('ads_campaigns.report.vs_previous') . ')';
    $align = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    if ($metrics !== null) {
        // Google, YouTube, TikTok, Snapchat, LinkedIn, X and ChatGPT Ads (PlatformReportService).
        $moneyKeys = ['spend', 'cpc', 'cpm', 'cost_per_conversion', 'conversion_value', 'daily_budget', 'lifetime_budget'];
        $num = fn ($v) => $v === null ? '—' : number_format((float) $v, fmod((float) $v, 1) ? 1 : 0);
        $rows = [];
        foreach ($metrics as $key => [$value, $delta]) {
            $shown = $key === 'ctr' ? ($value === null ? '—' : number_format($value * 100, 2) . '%') : (in_array($key, $moneyKeys, true) ? $money($value) : $num($value));
            $rows[] = [__('ads_campaigns.report.csv.' . $key), $shown . $change($delta)];
        }
        $reporting = $insights['reporting'];
        $eyebrow = __('ads_campaigns.report.eyebrow_platform', ['name' => $insights['name']]);
        $head = $reporting
            ? array_merge([__('ads_campaigns.report.csv.campaign'), __('ads_campaigns.report.csv.spend'), __('ads_campaigns.report.csv.clicks')], $insights['has_conversions'] ? [__('ads_campaigns.report.csv.conversions')] : [])
            : [__('ads_campaigns.report.csv.campaign'), __('ads_campaigns.report.csv.status'), __('ads_campaigns.report.csv.daily_budget')];
        $table = array_map(fn ($c) => $reporting
            ? array_merge([$c['name'], isset($c['spend']) ? $money($c['spend']) : '—', isset($c['clicks']) ? $int($c['clicks']) : '—'], $insights['has_conversions'] ? [$num($c['conversions'] ?? null)] : [])
            : [$c['name'], $c['status'] ?: '—', $money($c['daily_budget'])], $campaigns);
        $tableTitle = __($reporting ? 'ads_campaigns.report.top_campaigns' : 'ads_campaigns.report.campaigns');
        $attachmentNote = __('ads_campaigns.report.attachment_note_platform');
        $footer = $reporting ? __('ads_campaigns.report.footer_platform', ['name' => $insights['name']]) : __('ads_campaigns.report.no_reporting', ['name' => $insights['name']]);
    } else {
        $rows = [
            [__('ads_campaigns.report.csv.spend'), $money($summary['spend']) . $change($summary['spend_change'])],
            [$resultLabel, $int($summary['results']) . $change($summary['results_change'])],
            [__('ads_campaigns.report.csv.cost_per_result'), $money($summary['cost_per_result'])],
            [__('ads_campaigns.report.csv.impressions'), $int($summary['impressions'])],
            [__('ads_campaigns.report.csv.reach'), $int($summary['reach'])],
            [__('ads_campaigns.report.csv.clicks'), $int($summary['clicks'])],
            [__('ads_campaigns.report.csv.ctr'), $summary['ctr'] === null ? '—' : number_format($summary['ctr'] * 100, 2) . '%'],
            [__('ads_campaigns.report.csv.cpm'), $money($summary['cpm'])],
        ];
        $eyebrow = __($schedule->platform === 'instagram' ? 'ads_campaigns.report.eyebrow_instagram' : 'ads_campaigns.report.eyebrow');
        $head = [__('ads_campaigns.report.csv.campaign'), __('ads_campaigns.report.csv.spend'), __('ads_campaigns.report.csv.results'), __('ads_campaigns.report.csv.cost_per_result')];
        $table = array_map(fn ($c) => [$c['name'], $money($c['spend']), $int($c['results']), $money($c['cost_per_result'])], $campaigns);
        $tableTitle = __('ads_campaigns.report.top_campaigns');
        $attachmentNote = __('ads_campaigns.report.attachment_note');
        $footer = __('ads_campaigns.report.footer');
    }
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<body style="margin:0;padding:0;background:#f6f7fb;font-family:-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:#161B2B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #E7E9F0;border-radius:14px;">
      <tr><td style="padding:24px 28px 8px;text-align:{{ $align }};">
        <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#004CFF;">{{ $eyebrow }}</p>
        <h1 style="margin:0;font-size:20px;line-height:1.3;color:#161B2B;">{{ $schedule->name }}</h1>
        <p style="margin:6px 0 0;font-size:13px;color:#6B7385;"><bdi>{{ $account->name }}</bdi> · {{ $insights['since'] }} – {{ $insights['until'] }}</p>
      </td></tr>
      <tr><td style="padding:16px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          @foreach ($rows as [$label, $value])
            <tr>
              <td style="padding:9px 0;border-bottom:1px solid #F1F3F7;font-size:13px;color:#4B5263;text-align:{{ $align }};">{{ $label }}</td>
              <td style="padding:9px 0;border-bottom:1px solid #F1F3F7;font-size:14px;font-weight:700;color:#161B2B;text-align:{{ $rtl ? 'left' : 'right' }};">{{ $value }}</td>
            </tr>
          @endforeach
        </table>
      </td></tr>
      @if ($campaigns)
        <tr><td style="padding:4px 28px 8px;text-align:{{ $align }};">
          <h2 style="margin:8px 0 10px;font-size:15px;color:#161B2B;">{{ $tableTitle }}</h2>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
            <tr style="background:#F8F9FC;color:#8A92A3;">
              @foreach ($head as $i => $label)
                <td style="padding:8px;text-align:{{ $i ? $end : $align }};">{{ $label }}</td>
              @endforeach
            </tr>
            @foreach ($table as $cells)
              <tr>
                @foreach ($cells as $i => $cell)
                  <td style="padding:8px;border-bottom:1px solid #F1F3F7;{{ $i ? '' : 'color:#161B2B;' }}text-align:{{ $i ? $end : $align }};">@if ($i){{ $cell }}@else<bdi>{{ $cell }}</bdi>@endif</td>
                @endforeach
              </tr>
            @endforeach
          </table>
        </td></tr>
      @endif
      <tr><td style="padding:16px 28px 24px;text-align:{{ $align }};">
        <p style="margin:0 0 6px;font-size:12px;color:#6B7385;">{{ $attachmentNote }}</p>
        <p style="margin:0;font-size:11px;color:#8A92A3;">{{ $footer }}</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
