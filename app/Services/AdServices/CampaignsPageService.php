<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\SocialAccount;
use App\Support\AdAccountSelection;

/**
 * Read model for the TikTok and Snapchat campaigns pages - the same shape
 * as FacebookCampaignsPageService, so MetaCampaigns.vue renders them.
 * Scoped to the seller's selected ad account on that platform; spend and
 * results load separately (TiktokInsightsService, SnapchatInsightsService).
 */
class CampaignsPageService
{
    public const PLATFORMS = ['tiktok', 'snapchat'];

    public function __construct(private int $userId, private string $platform, private bool $memberCanWrite = true)
    {
    }

    public function build(): array
    {
        $options = AdAccountSelection::options($this->userId, $this->platform)->load('adDetails');
        $account = AdAccountSelection::resolve($this->userId, $this->platform);
        $account?->loadMissing('adDetails');

        $campaigns = $account ? AdCampaign::where('user_id', $this->userId)->where('platform', $this->platform)
            ->where(fn ($q) => $q->where('social_account_id', $account->id)->orWhereNull('social_account_id'))
            ->withCount(['adGroups', 'ads'])->orderByDesc('created_at')->get() : collect();
        $ids = $campaigns->pluck('id');
        $names = $campaigns->pluck('name', 'id');
        $adGroups = $account ? AdAdGroup::whereIn('ad_campaign_id', $ids)->orderByDesc('created_at')->get() : collect();
        $groupNames = $adGroups->pluck('name', 'id');
        $ads = $account ? Ad::whereIn('ad_campaign_id', $ids)->orderByDesc('created_at')->get() : collect();
        $creatives = $account ? AdCreative::whereIn('ad_campaign_id', $ids)->orderByDesc('created_at')->get() : collect();

        $expired = $account && ! $this->healthy($account);
        $rows = $campaigns->map(fn (AdCampaign $c) => [
            'id' => $c->id,
            'remote_id' => $c->ad_campaign_id,
            'name' => $c->name,
            'objective' => $c->objective,
            'status' => self::status($c->status),
            ...$this->budgets($c),
            'start_time' => optional($c->start_time)->toIso8601String(),
            'end_time' => optional($c->end_time)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'adsets_count' => (int) $c->ad_groups_count,
            'ads_count' => (int) $c->ads_count,
        ])->values();

        return [
            'platform' => $this->platform,
            'instagram' => null,
            'account' => $account ? $this->accountRow($account, $account) : null,
            'accounts' => $options->map(fn ($a) => $this->accountRow($a, $account))->values()->all(),
            'can_manage' => $this->memberCanWrite && $account && ! $expired,
            'manage_blocker' => ! $this->memberCanWrite ? 'member_read_only' : (! $account ? 'no_account' : ($expired ? 'token_expired' : null)),
            'counts' => ['campaigns' => $campaigns->count(), 'adsets' => $adGroups->count(), 'ads' => $ads->count(), 'creatives' => $creatives->count()],
            'summary' => $this->summary($rows->all()),
            'campaigns' => $rows->all(),
            'adsets' => $adGroups->map(fn (AdAdGroup $s) => [
                'id' => $s->id,
                'remote_id' => $s->ad_adgroup_id,
                'name' => $s->name,
                'status' => self::status($s->status),
                'campaign' => $names[$s->ad_campaign_id] ?? null,
                'budget' => $s->budget !== null ? (float) $s->budget : null,
                'budget_mode' => $s->budget_mode,
                'optimization_goal' => $s->optimization_goal,
                'start_time' => optional($s->schedule_start_time)->toIso8601String(),
                'end_time' => optional($s->schedule_end_time)->toIso8601String(),
            ])->values()->all(),
            'ads' => $ads->map(fn (Ad $a) => [
                'id' => $a->id,
                'remote_id' => $a->ad_id,
                'name' => $a->name,
                'status' => self::status($a->status),
                'adset' => $groupNames[$a->ad_adgroup_id] ?? null,
                'campaign' => $names[$a->ad_campaign_id] ?? null,
            ])->values()->all(),
            'creatives' => $creatives->map(fn (AdCreative $c) => [
                'id' => $c->id,
                'remote_id' => $c->ad_creative_id,
                'name' => $c->name,
                'headline' => $c->headline,
                'message' => $c->message ? mb_substr($c->message, 0, 140) : null,
                'call_to_action' => $c->call_to_action,
                'campaign' => $names[$c->ad_campaign_id] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * Daily and lifetime budget. TikTok: one campaign budget with a mode
     * (BUDGET_MODE_DAY / BUDGET_MODE_TOTAL). Snapchat: the campaign's daily
     * cap, else the ad squad budget the builder stored with its mode
     * (daily / total).
     *
     * @return array{daily_budget: ?float, lifetime_budget: ?float}
     */
    private function budgets(AdCampaign $c): array
    {
        $budget = $c->budget !== null ? (float) $c->budget : null;
        [$day, $total] = $this->platform === 'tiktok' ? ['BUDGET_MODE_DAY', 'BUDGET_MODE_TOTAL'] : ['daily', 'total'];

        return [
            'daily_budget' => $c->daily_budget !== null ? (float) $c->daily_budget : ($c->budget_mode === $day ? $budget : null),
            'lifetime_budget' => $c->budget_mode === $total ? $budget : null,
        ];
    }

    /** TikTok and Snapchat rows store delivery as a boolean (builder, sync); older rows may hold ENABLE/DISABLE. */
    private static function status($value): string
    {
        if (is_bool($value) || in_array((string) $value, ['0', '1'], true)) {
            return (bool) $value ? 'active' : 'paused';
        }

        return strtolower((string) $value);
    }

    /** Snap access tokens live ~30 minutes and SnapchatAdsApi refreshes them, so a refresh token keeps the account usable. */
    private function healthy(SocialAccount $a): bool
    {
        return (bool) $a->is_token_valid && (! $a->expires_at || $a->expires_at->isFuture() || ($this->platform === 'snapchat' && $a->refresh_token));
    }

    private function accountRow(SocialAccount $a, ?SocialAccount $selected): array
    {
        $id = (string) $a->platform_account_id;

        return [
            'id' => $a->id,
            'name' => $a->name ?: $a->username,
            'masked_id' => '…' . substr(preg_replace('/\D/', '', $id), -4),
            'external_id' => $id,
            'currency' => $a->adDetails->currency ?? ($a->metadata['currency'] ?? null),
            'account_status' => $a->adDetails->account_status ?? null,
            'business_name' => null,
            'last_synced_at' => optional($a->adDetails->last_synced_at ?? null)->toIso8601String(),
            'insights_synced_at' => $a->metadata['insights_synced_at'] ?? null,
            'healthy' => $this->healthy($a),
            'read_only' => false,
            'role_known' => false,
            'selected' => $selected && $selected->id === $a->id,
        ];
    }

    private function summary(array $campaigns): array
    {
        $rows = collect($campaigns);
        $daily = $rows->whereNotNull('daily_budget');
        $lifetime = $rows->whereNotNull('lifetime_budget');
        $active = $rows->where('status', 'active')->count();
        $paused = $rows->where('status', 'paused')->count();

        return [
            'total' => $rows->count(),
            'active' => $active,
            'paused' => $paused,
            'other' => max(0, $rows->count() - $active - $paused),
            'daily_budget_total' => round((float) $daily->sum('daily_budget'), 2),
            'daily_campaigns' => $daily->count(),
            'lifetime_budget_total' => round((float) $lifetime->sum('lifetime_budget'), 2),
            'lifetime_campaigns' => $lifetime->count(),
        ];
    }
}
