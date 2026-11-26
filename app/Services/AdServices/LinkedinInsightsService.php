<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Services\AdServices\Concerns\BuildsCampaignInsights;
use App\Support\AdAccountSelection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Ads reporting from the adAnalytics finder (q=analytics), the only
 * way LinkedIn reports delivery (no webhooks). Needs the r_ads_reporting scope,
 * which LinkedinAdService::redirect() already requests.
 *
 * Docs: learn.microsoft.com/linkedin/marketing/integrations/ads-reporting/ads-reporting
 * (li-lms-2026-11): Rest.li 2.0 query syntax, up to 20 `fields`, at most 15,000
 * elements and no pagination, costs as strings in the account currency, dates in
 * UTC with inclusive start and end, approximateMemberReach only up to 92 days.
 *
 * A local "campaign" here is a LinkedIn Campaign Group (see LinkedinAdService), so
 * rows use the CAMPAIGN_GROUP pivot. Output has the shape of
 * FacebookAdService::insights() so the dashboard and campaigns page read it as-is.
 */
class LinkedinInsightsService
{
    use BuildsCampaignInsights;

    public const VERSION = '202606';

    private const FIELDS = ['costInLocalCurrency', 'impressions', 'clicks', 'landingPageClicks', 'externalWebsiteConversions',
        'oneClickLeads', 'conversionValueInLocalCurrency', 'approximateMemberReach', 'videoViews', 'pivotValues', 'dateRange'];

    /** Result per objective: [result_type, metric]. */
    private const RESULTS = [
        'LEAD_GENERATION' => ['lead', 'leads'],
        'WEBSITE_CONVERSION' => ['conversion', 'website_conversions'],
        'WEBSITE_CONVERSIONS' => ['conversion', 'website_conversions'],
        'WEBSITE_VISIT' => ['link_click', 'landing_page_clicks'],
        'WEBSITE_VISITS' => ['link_click', 'landing_page_clicks'],
        'VIDEO_VIEW' => ['video_view', 'video_views'],
        'VIDEO_VIEWS' => ['video_view', 'video_views'],
        'BRAND_AWARENESS' => ['reach', 'reach'],
    ];

    public function __construct(public SocialAccount $account) {}

    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'linkedin');

        return $account ? new self($account) : null;
    }

    public function insights(int $days = 30, bool $fresh = false): array
    {
        $key = 'li_insights:v1:'.$this->account->id.':'.$days.':'.now()->toDateString();
        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(15), function () use ($days) {
                $until = now()->subDay()->startOfDay();
                $since = $until->copy()->subDays($days - 1);
                $current = $this->campaignRows($since, $until);
                $previous = $this->campaignRows($since->copy()->subDays($days), $since->copy()->subDay());
                $totals = $this->insightTotals($current);

                $this->account->forceFill(['metadata' => array_merge($this->account->metadata ?? [], ['insights_synced_at' => now()->toIso8601String()])])->save();

                return [
                    'success' => true,
                    'days' => $days,
                    'since' => $since->toDateString(),
                    'until' => $until->toDateString(),
                    'currency' => $this->account->adDetails->currency ?? ($this->account->metadata['currency'] ?? null),
                    'platform' => 'linkedin',
                    'all_placements_spend' => $totals['spend'],
                    'totals' => ['current' => $totals, 'previous' => $this->insightTotals($previous)],
                    'campaigns' => $current,
                    'details' => ['result_type' => $totals['result_type'], 'daily' => $this->daily($since, $until),
                        'funnel' => null, 'placements' => null, 'regions' => null, 'ages' => null, 'account' => null, 'today_spend' => null],
                    'synced_at' => now()->toIso8601String(),
                ];
            });
        } catch (LinkedinReportingException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'reconnect' => $e->reconnect];
        }
    }

    /** @return array<int|string, array> rows keyed by local campaign id (LinkedIn group id when not created here) */
    private function campaignRows(Carbon $since, Carbon $until): array
    {
        $local = AdCampaign::where('user_id', $this->account->user_id)->where('platform', 'linkedin')->whereNotNull('ad_campaign_id')
            ->get(['id', 'ad_campaign_id', 'objective'])
            ->keyBy(fn ($c) => self::numericId($c->ad_campaign_id));

        $out = [];
        foreach ($this->report($since, $until, 'CAMPAIGN_GROUP', 'ALL') as $row) {
            $remote = self::numericId($row['pivotValues'][0] ?? '');
            if ($remote === '') {
                continue;
            }
            $campaign = $local[$remote] ?? null;
            $m = $this->metrics($row);
            [$type, $metric] = self::RESULTS[strtoupper((string) $campaign?->objective)] ?? [null, null];
            $out[$campaign?->id ?? $remote] = $this->insightRow($m, $type, $metric ? $m[$metric] : null);
        }

        return $out;
    }

    /** One entry per day of the period (zero on days LinkedIn reports nothing). */
    private function daily(Carbon $since, Carbon $until): ?array
    {
        try {
            $byDay = [];
            foreach ($this->report($since, $until, 'ACCOUNT', 'DAILY') as $row) {
                $s = $row['dateRange']['start'] ?? null;
                if ($s) {
                    $byDay[sprintf('%04d-%02d-%02d', $s['year'], $s['month'], $s['day'])] = $this->metrics($row);
                }
            }
        } catch (LinkedinReportingException $e) {
            if ($e->reconnect) {
                throw $e;
            }
            Log::info('LinkedIn daily report unavailable', ['account' => $this->account->id, 'error' => $e->getMessage()]);

            return null;
        }

        $series = [];
        for ($d = $since->copy(); $d->lte($until); $d->addDay()) {
            $m = $byDay[$d->toDateString()] ?? $this->metrics([]);
            $series[] = ['date' => $d->toDateString(), 'spend' => $m['spend'], 'results' => null, 'impressions' => $m['impressions'],
                'link_clicks' => $m['clicks'], 'purchase_value' => $m['purchase_value'], 'conversions' => $m['conversions']];
        }

        return $series;
    }

    /** Rows of one adAnalytics request for this ad account. */
    private function report(Carbon $since, Carbon $until, string $pivot, string $granularity): array
    {
        $fields = self::FIELDS;
        // Reach is only reported for ranges of up to 92 days.
        if ($since->diffInDays($until, true) + 1 > 92) {
            $fields = array_values(array_diff($fields, ['approximateMemberReach']));
        }
        // Rest.li 2.0: parentheses, colons and commas stay literal; only the URN inside List() is encoded.
        $range = sprintf('(start:(year:%d,month:%d,day:%d),end:(year:%d,month:%d,day:%d))',
            $since->year, $since->month, $since->day, $until->year, $until->month, $until->day);
        $url = rtrim(adminSetting('ads.linkedin.base_url') ?: 'https://api.linkedin.com/rest/', '/').'/adAnalytics?q=analytics'
            .'&pivot='.$pivot.'&timeGranularity='.$granularity.'&dateRange='.$range
            .'&accounts=List('.rawurlencode('urn:li:sponsoredAccount:'.$this->account->platform_account_id).')'
            .'&fields='.implode(',', $fields);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token(),
            'LinkedIn-Version' => self::VERSION,
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->acceptJson()->timeout(45)->get($url);

        if ($response->successful()) {
            return $response->json('elements') ?? [];
        }
        $message = (string) ($response->json('message') ?: 'LinkedIn did not return reporting.');
        throw match ($response->status()) {
            401 => new LinkedinReportingException('LinkedIn access expired - reconnect the LinkedIn ad account.', true),
            403 => new LinkedinReportingException('LinkedIn refused reporting for this ad account. Reconnect it and allow reporting access (r_ads_reporting), or check your role on the ad account.', true),
            429 => new LinkedinReportingException('LinkedIn is limiting reporting requests right now. Please try again in a few minutes.'),
            default => new LinkedinReportingException($message),
        };
    }

    /** A valid access token: refreshed when expired and LinkedIn issued a refresh token. */
    private function token(): string
    {
        $expires = $this->account->expires_at ? Carbon::parse($this->account->expires_at) : null;
        if ($expires && $expires->isPast()) {
            if (! $this->account->refresh_token) {
                throw new LinkedinReportingException('LinkedIn access expired - reconnect the LinkedIn ad account.', true);
            }
            // LinkedinAdService needs a signed-in user (it isn't one in scheduled reports): a failure means reconnect.
            $refreshed = rescue(fn () => app(LinkedinAdService::class)->refreshToken($this->account), ['success' => false], false);
            if (! ($refreshed['success'] ?? false)) {
                throw new LinkedinReportingException('LinkedIn access expired - reconnect the LinkedIn ad account.', true);
            }
            $this->account->refresh();
        }

        return (string) $this->account->access_token;
    }

    /** One element's metrics as numbers (costs arrive as strings). */
    private function metrics(array $r): array
    {
        $num = fn (string $k) => isset($r[$k]) && is_numeric($r[$k]) ? (float) $r[$k] : 0.0;
        $leads = (int) $num('oneClickLeads');
        $website = (int) $num('externalWebsiteConversions');

        return [
            'spend' => round($num('costInLocalCurrency'), 2),
            'impressions' => (int) $num('impressions'),
            'clicks' => (int) $num('clicks'),
            'reach' => (int) $num('approximateMemberReach'),
            'landing_page_clicks' => (int) $num('landingPageClicks'),
            'leads' => $leads,
            'website_conversions' => $website,
            'video_views' => (int) $num('videoViews'),
            'conversions' => $leads + $website,
            'purchase_value' => round($num('conversionValueInLocalCurrency'), 2),
        ];
    }

    /** "urn:li:sponsoredCampaignGroup:123" or "123" → "123". */
    public static function numericId(?string $value): string
    {
        return preg_match('/(\d+)\s*$/', (string) $value, $m) ? $m[1] : '';
    }
}
