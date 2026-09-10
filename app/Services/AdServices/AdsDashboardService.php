<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model for the combined Ads Dashboard (admin.ads.dashboard).
 *
 * Everything here comes from data this app actually stores today:
 *  - connected ad accounts + token health (social_accounts / social_account_ad_details)
 *  - campaigns / ad groups / ads / creatives created through the app
 *  - the budgets configured on those campaigns
 *
 * It deliberately does NOT surface spend / impressions / clicks /
 * conversions / CTR / ROAS - this app has no reporting-API sync for any
 * platform (ad_reports is an empty stub, no AdService fetches insights),
 * so those would be fabricated. The dashboard shows a "not yet available"
 * panel in their place until an insights sync exists.
 */
class AdsDashboardService
{
    /** The platforms the Ads module supports, in display order. */
    private const PLATFORMS = [
        'facebook'  => ['label' => 'Meta / Facebook', 'icon' => 'bxl-facebook-circle', 'color' => '#1877F2'],
        'instagram' => ['label' => 'Instagram',       'icon' => 'bxl-instagram',       'color' => '#E1306C'],
        'google'    => ['label' => 'Google Ads',      'icon' => 'bxl-google',          'color' => '#4285F4'],
        'youtube'   => ['label' => 'YouTube',         'icon' => 'bxl-youtube',         'color' => '#FF0000'],
        'tiktok'    => ['label' => 'TikTok',          'icon' => 'bxl-tiktok',          'color' => '#111827'],
        'snapchat'  => ['label' => 'Snapchat',        'icon' => 'bxl-snapchat',        'color' => '#FFFC00'],
        'x'         => ['label' => 'X',               'icon' => 'bxl-twitter',         'color' => '#111827'],
        'linkedin'  => ['label' => 'LinkedIn',        'icon' => 'bxl-linkedin',        'color' => '#0A66C2'],
    ];

    public function __construct(private int $userId)
    {
    }

    public function build(): array
    {
        $accounts  = $this->accounts();
        $campaigns = $this->campaigns();

        $adGroupCounts = AdAdGroup::query()
            ->selectRaw('platform, count(*) as c')
            ->whereIn('ad_campaign_id', $campaigns->pluck('id'))
            ->groupBy('platform')->pluck('c', 'platform');

        $adCounts = Ad::query()
            ->selectRaw('platform, count(*) as c')
            ->whereIn('ad_campaign_id', $campaigns->pluck('id'))
            ->groupBy('platform')->pluck('c', 'platform');

        $creativeCounts = AdCreative::query()
            ->selectRaw('platform, count(*) as c')
            ->whereIn('ad_campaign_id', $campaigns->pluck('id'))
            ->groupBy('platform')->pluck('c', 'platform');

        $platforms = collect(self::PLATFORMS)->map(function (array $meta, string $key) use ($accounts, $campaigns, $adGroupCounts, $adCounts, $creativeCounts) {
            $platformAccounts  = $accounts->get($key, collect());
            $platformCampaigns = $campaigns->get($key, collect());

            return array_merge($meta, [
                'platform'       => $key,
                'connected'      => $platformAccounts->isNotEmpty(),
                'healthy'        => $platformAccounts->contains(fn ($a) => $this->isHealthy($a)),
                'accounts_count' => $platformAccounts->count(),
                'accounts'       => $platformAccounts->map(fn ($a) => $this->accountRow($a))->values()->all(),
                'summary'        => $this->summaryFor(
                    $platformCampaigns,
                    (int) $adGroupCounts->get($key, 0),
                    (int) $adCounts->get($key, 0),
                    (int) $creativeCounts->get($key, 0),
                    $platformAccounts->count(),
                ),
                'campaigns'      => $platformCampaigns
                    ->sortByDesc('created_at')
                    ->map(fn ($c) => $this->campaignRow($c))
                    ->values()->all(),
            ]);
        })->values();

        return [
            'currency'        => $this->primaryCurrency($accounts),
            'connect_url'     => null, // filled by the controller (needs route())
            'platforms'       => $platforms->all(),
            'combined'        => [
                'summary'          => $this->summaryFor(
                    $campaigns->flatten(1),
                    (int) $adGroupCounts->sum(),
                    (int) $adCounts->sum(),
                    (int) $creativeCounts->sum(),
                    $accounts->flatten(1)->count(),
                ),
                'platform_breakdown' => $this->platformBreakdown($platforms),
                'recent_campaigns'   => $campaigns->flatten(1)
                    ->sortByDesc('created_at')
                    ->take(8)
                    ->map(fn ($c) => $this->campaignRow($c))
                    ->values()->all(),
            ],
            'totals'          => [
                'platforms_connected' => $platforms->where('connected', true)->count(),
                'platforms_total'     => count(self::PLATFORMS),
                'accounts'            => $accounts->flatten(1)->count(),
            ],
            'has_connections' => $accounts->isNotEmpty(),
            'has_campaigns'   => $campaigns->isNotEmpty(),
        ];
    }

    /* ------------------------------------------------------------------ */

    /** @return Collection<string, Collection<SocialAccount>> keyed by platform */
    private function accounts(): Collection
    {
        return SocialAccount::query()
            ->where('user_id', $this->userId)
            ->where('has_ads_permission', true)
            ->with('adDetails')
            ->get()
            ->groupBy('platform');
    }

    /** @return Collection<string, Collection<AdCampaign>> keyed by platform */
    private function campaigns(): Collection
    {
        return AdCampaign::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('platform');
    }

    private function isHealthy(SocialAccount $account): bool
    {
        return (bool) $account->is_token_valid
            && (! $account->expires_at || $account->expires_at->isFuture());
    }

    private function summaryFor(Collection $campaigns, int $adGroups, int $ads, int $creatives, int $accounts): array
    {
        $byStatus = $campaigns->groupBy(fn ($c) => strtolower((string) ($c->status ?: 'unknown')))
            ->map->count();

        $active = $byStatus->get('active', 0) + $byStatus->get('enable', 0) + $byStatus->get('enabled', 0);
        $paused = $byStatus->get('paused', 0) + $byStatus->get('disable', 0) + $byStatus->get('disabled', 0);

        return [
            'accounts'             => $accounts,
            'campaigns_total'      => $campaigns->count(),
            'campaigns_active'     => $active,
            'campaigns_paused'     => $paused,
            'campaigns_other'      => max($campaigns->count() - $active - $paused, 0),
            'ad_groups'            => $adGroups,
            'ads'                  => $ads,
            'creatives'            => $creatives,
            'daily_budget_total'   => round((float) $campaigns->sum(fn ($c) => (float) $c->daily_budget), 2),
            'lifetime_budget_total' => round((float) $campaigns->sum(fn ($c) => (float) $c->budget), 2),
        ];
    }

    private function platformBreakdown(Collection $platforms): array
    {
        $withCampaigns = $platforms
            ->filter(fn ($p) => $p['summary']['campaigns_total'] > 0)
            ->values();

        $totalCampaigns = max($withCampaigns->sum(fn ($p) => $p['summary']['campaigns_total']), 1);

        return $withCampaigns->map(fn ($p) => [
            'platform'        => $p['platform'],
            'label'           => $p['label'],
            'color'           => $p['color'],
            'icon'            => $p['icon'],
            'campaigns_total' => $p['summary']['campaigns_total'],
            'daily_budget'    => $p['summary']['daily_budget_total'],
            'lifetime_budget' => $p['summary']['lifetime_budget_total'],
            'share'           => round($p['summary']['campaigns_total'] / $totalCampaigns * 100, 1),
        ])->sortByDesc('campaigns_total')->values()->all();
    }

    private function accountRow(SocialAccount $account): array
    {
        return [
            'id'             => $account->id,
            'name'           => $account->name ?: $account->username ?: "Account {$account->platform_account_id}",
            'external_id'    => $account->platform_account_id,
            'currency'       => $account->adDetails->currency ?? null,
            'account_status' => $account->adDetails->account_status ?? null,
            'healthy'        => $this->isHealthy($account),
            'expires_at'     => optional($account->expires_at)->toIso8601String(),
            'last_synced_at' => optional($account->adDetails->last_synced_at ?? null)->toIso8601String(),
        ];
    }

    private function campaignRow(AdCampaign $campaign): array
    {
        return [
            'id'             => $campaign->id,
            'name'           => $campaign->name ?: 'Untitled campaign',
            'platform'       => $campaign->platform,
            'status'         => $campaign->status,
            'objective'      => $campaign->objective,
            'daily_budget'   => $campaign->daily_budget !== null ? (float) $campaign->daily_budget : null,
            'lifetime_budget' => $campaign->budget !== null ? (float) $campaign->budget : null,
            'start_time'     => optional($campaign->start_time)->toIso8601String(),
            'end_time'       => optional($campaign->end_time)->toIso8601String(),
            'created_at'     => optional($campaign->created_at)->toIso8601String(),
        ];
    }

    private function primaryCurrency(Collection $accounts): ?string
    {
        return $accounts->flatten(1)
            ->map(fn ($a) => $a->adDetails->currency ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }
}
