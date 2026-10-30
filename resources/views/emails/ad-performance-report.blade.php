@php
    $cur = $insights['currency'] ?? '';
    $money = fn ($v) => $v === null ? '—' : trim($cur . ' ' . number_format((float) $v, 2));
    $int = fn ($v) => $v === null ? '—' : number_format((float) $v);
    $resultLabel = __('ads_campaigns.result_types.' . str_replace('.', '_', (string) ($summary['result_type'] ?? 'results')));
    $resultLabel = str_starts_with($resultLabel, 'ads_campaigns.') ? __('ads_campaigns.result_types.results') : $resultLabel;
    $change = fn ($v) => $v === null ? '' : ' (' . ($v >= 0 ? '+' : '') . $v . '% ' . __('ads_campaigns.report.vs_previous') . ')';
    $align = $rtl ? 'right' : 'left';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<body style="margin:0;padding:0;background:#f6f7fb;font-family:-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:#161B2B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" dir="{{ $rtl ? 'rtl' : 'ltr' }}" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #E7E9F0;border-radius:14px;">
      <tr><td style="padding:24px 28px 8px;text-align:{{ $align }};">
        <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#004CFF;">{{ __($schedule->platform === 'instagram' ? 'ads_campaigns.report.eyebrow_instagram' : 'ads_campaigns.report.eyebrow') }}</p>
        <h1 style="margin:0;font-size:20px;line-height:1.3;color:#161B2B;">{{ $schedule->name }}</h1>
        <p style="margin:6px 0 0;font-size:13px;color:#6B7385;"><bdi>{{ $account->name }}</bdi> · {{ $insights['since'] }} – {{ $insights['until'] }}</p>
      </td></tr>
      <tr><td style="padding:16px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          @foreach ([
              [__('ads_campaigns.report.csv.spend'), $money($summary['spend']) . $change($summary['spend_change'])],
              [$resultLabel, $int($summary['results']) . $change($summary['results_change'])],
              [__('ads_campaigns.report.csv.cost_per_result'), $money($summary['cost_per_result'])],
              [__('ads_campaigns.report.csv.impressions'), $int($summary['impressions'])],
              [__('ads_campaigns.report.csv.reach'), $int($summary['reach'])],
              [__('ads_campaigns.report.csv.clicks'), $int($summary['clicks'])],
              [__('ads_campaigns.report.csv.ctr'), $summary['ctr'] === null ? '—' : number_format($summary['ctr'] * 100, 2) . '%'],
              [__('ads_campaigns.report.csv.cpm'), $money($summary['cpm'])],
          ] as [$label, $value])
            <tr>
              <td style="padding:9px 0;border-bottom:1px solid #F1F3F7;font-size:13px;color:#4B5263;text-align:{{ $align }};">{{ $label }}</td>
              <td style="padding:9px 0;border-bottom:1px solid #F1F3F7;font-size:14px;font-weight:700;color:#161B2B;text-align:{{ $rtl ? 'left' : 'right' }};">{{ $value }}</td>
            </tr>
          @endforeach
        </table>
      </td></tr>
      @if ($campaigns)
        <tr><td style="padding:4px 28px 8px;text-align:{{ $align }};">
          <h2 style="margin:8px 0 10px;font-size:15px;color:#161B2B;">{{ __('ads_campaigns.report.top_campaigns') }}</h2>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
            <tr style="background:#F8F9FC;color:#8A92A3;">
              <td style="padding:8px;text-align:{{ $align }};">{{ __('ads_campaigns.report.csv.campaign') }}</td>
              <td style="padding:8px;text-align:{{ $rtl ? 'left' : 'right' }};">{{ __('ads_campaigns.report.csv.spend') }}</td>
              <td style="padding:8px;text-align:{{ $rtl ? 'left' : 'right' }};">{{ __('ads_campaigns.report.csv.results') }}</td>
              <td style="padding:8px;text-align:{{ $rtl ? 'left' : 'right' }};">{{ __('ads_campaigns.report.csv.cost_per_result') }}</td>
            </tr>
            @foreach ($campaigns as $c)
              <tr>
                <td style="padding:8px;border-bottom:1px solid #F1F3F7;color:#161B2B;text-align:{{ $align }};"><bdi>{{ $c['name'] }}</bdi></td>
                <td style="padding:8px;border-bottom:1px solid #F1F3F7;text-align:{{ $rtl ? 'left' : 'right' }};">{{ $money($c['spend']) }}</td>
                <td style="padding:8px;border-bottom:1px solid #F1F3F7;text-align:{{ $rtl ? 'left' : 'right' }};">{{ $int($c['results']) }}</td>
                <td style="padding:8px;border-bottom:1px solid #F1F3F7;text-align:{{ $rtl ? 'left' : 'right' }};">{{ $money($c['cost_per_result']) }}</td>
              </tr>
            @endforeach
          </table>
        </td></tr>
      @endif
      <tr><td style="padding:16px 28px 24px;text-align:{{ $align }};">
        <p style="margin:0 0 6px;font-size:12px;color:#6B7385;">{{ __('ads_campaigns.report.attachment_note') }}</p>
        <p style="margin:0;font-size:11px;color:#8A92A3;">{{ __('ads_campaigns.report.footer') }}</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
