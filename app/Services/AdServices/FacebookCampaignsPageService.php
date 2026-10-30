<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\SocialAccount;
use App\Support\AdAccountSelection;
use Illuminate\Support\Collection;

/**
 * Read model for the Facebook campaigns page (admin.ads.campaigns.index,
 * platform=facebook): everything is scoped to the seller's selected ad
 * account. Only stored values - spend and results load separately from
 * Meta insights, and are never filled in here.
 */
class FacebookCampaignsPageService
{
    /**
     * $platform 'instagram' shows the same Meta ad account (Instagram ads run on it),
     * with campaigns created from either page; insights are narrowed to Instagram
     * placements by InstagramAdService.
     */
    public function __construct(private int $userId, private bool $memberCanWrite = true, private string $platform = 'facebook')
    {
    }

    public function build(): array
    {
        $options = AdAccountSelection::options($this->userId, 'facebook')->load('adDetails');
        $account = AdAccountSelection::resolve($this->userId, 'facebook');
        $account?->loadMissing('adDetails');

        $campaigns = $account ? $this->scoped(AdCampaign::query(), $account)->withCount(['adGroups', 'ads'])->orderByDesc('created_at')->get() : collect();
        $campaignIds = $campaigns->pluck('id');
        $campaignNames = $campaigns->pluck('name', 'id');

        $adSets = $account ? AdAdGroup::whereIn('ad_campaign_id', $campaignIds)->orderByDesc('created_at')->get() : collect();
        $adSetNames = $adSets->pluck('name', 'id');
        $ads = $account ? Ad::whereIn('ad_campaign_id', $campaignIds)->orderByDesc('created_at')->get() : collect();
        $creatives = $account ? AdCreative::whereIn('ad_campaign_id', $campaignIds)->orderByDesc('created_at')->get() : collect();

        $blocker = FacebookAdService::writeBlocker($account);

        return [
            'platform' => $this->platform,
            'instagram' => $this->platform === 'instagram' && $account ? $this->instagramIdentity($account) : null,
            'account' => $account ? $this->accountRow($account, $account) : null,
            'accounts' => $options->map(fn ($a) => $this->accountRow($a, $account))->values()->all(),
            // Workspace members with read access, read-only Meta roles and expired tokens can't change anything.
            'can_manage' => $this->memberCanWrite && $blocker === null,
            'manage_blocker' => !$this->memberCanWrite ? 'member_read_only' : ($blocker ? $this->blockerCode($account) : null),
            'counts' => [
                'campaigns' => $campaigns->count(),
                'adsets' => $adSets->count(),
                'ads' => $ads->count(),
                'creatives' => $creatives->count(),
            ],
            'summary' => $this->summary($campaigns),
            'campaigns' => $campaigns->map(fn (AdCampaign $c) => [
                'id' => $c->id,
                'remote_id' => $c->ad_campaign_id,
                'name' => $c->name,
                'objective' => $c->objective,
                'status' => strtolower((string) $c->status),
                'daily_budget' => $c->daily_budget !== null ? (float) $c->daily_budget : null,
                'lifetime_budget' => $c->budget !== null ? (float) $c->budget : null,
                'start_time' => optional($c->start_time)->toIso8601String(),
                'end_time' => optional($c->end_time)->toIso8601String(),
                'created_at' => optional($c->created_at)->toIso8601String(),
                'adsets_count' => (int) $c->ad_groups_count,
                'ads_count' => (int) $c->ads_count,
            ])->values()->all(),
            'adsets' => $adSets->map(fn (AdAdGroup $s) => [
                'id' => $s->id,
                'remote_id' => $s->ad_adgroup_id,
                'name' => $s->name,
                'status' => strtolower((string) $s->status),
                'campaign' => $campaignNames[$s->ad_campaign_id] ?? null,
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
                'status' => strtolower((string) $a->status),
                'adset' => $adSetNames[$a->ad_adgroup_id] ?? null,
                'campaign' => $campaignNames[$a->ad_campaign_id] ?? null,
            ])->values()->all(),
            'creatives' => $creatives->map(fn (AdCreative $c) => [
                'id' => $c->id,
                'remote_id' => $c->ad_creative_id,
                'name' => $c->name,
                'headline' => $c->headline,
                'message' => $c->message ? mb_substr($c->message, 0, 140) : null,
                'call_to_action' => $c->call_to_action,
                'campaign' => $campaignNames[$c->ad_campaign_id] ?? null,
            ])->values()->all(),
        ];
    }

    /** Campaigns of this account; rows from before accounts were recorded on campaigns stay visible. */
    private function scoped($query, SocialAccount $account)
    {
        return $query->where('user_id', $this->userId)->whereIn('platform', $this->platform === 'instagram' ? ['facebook', 'instagram'] : ['facebook'])
            ->where(fn ($q) => $q->where('social_account_id', $account->id)->orWhereNull('social_account_id'));
    }

    /** The Instagram account ads appear as: one in the ad account's Meta business first, else the seller's first. */
    private function instagramIdentity(SocialAccount $adAccount): ?array
    {
        $business = $adAccount->adDetails->business_id ?? null;
        $instagram = SocialAccount::where('user_id', $this->userId)->where('platform', 'instagram')->with('adDetails')->orderBy('id')->get()
            ->sortByDesc(fn ($a) => $business && ($a->adDetails->business_id ?? null) === $business)->first();

        return $instagram ? [
            'id' => $instagram->id,
            'name' => $instagram->name,
            'username' => $instagram->username,
            'avatar_url' => $instagram->avatar_url,
        ] : null;
    }

    private function accountRow(SocialAccount $a, ?SocialAccount $selected): array
    {
        $id = (string) $a->platform_account_id;
        $tasks = $a->metadata['user_tasks'] ?? null;

        return [
            'id' => $a->id,
            'name' => $a->name ?: $a->username,
            'masked_id' => '…' . substr(preg_replace('/\D/', '', $id), -4),
            'external_id' => $id,
            'currency' => $a->adDetails->currency ?? null,
            'account_status' => $a->adDetails->account_status ?? null,
            'business_name' => $a->metadata['business_name'] ?? null,
            'last_synced_at' => optional($a->adDetails->last_synced_at ?? null)->toIso8601String(),
            'insights_synced_at' => $a->metadata['insights_synced_at'] ?? null,
            'healthy' => (bool) $a->is_token_valid && (!$a->expires_at || $a->expires_at->isFuture()),
            // Unknown until the first sync after this release; only a role without MANAGE/ADVERTISE is read-only.
            'read_only' => is_array($tasks) && !array_intersect(['MANAGE', 'ADVERTISE'], $tasks),
            'role_known' => is_array($tasks),
            'selected' => $selected && $selected->id === $a->id,
        ];
    }

    private function blockerCode(?SocialAccount $account): string
    {
        if (!$account) {
            return 'no_account';
        }
        if (!$account->is_token_valid || ($account->expires_at && $account->expires_at->isPast())) {
            return 'token_expired';
        }

        return 'role_read_only';
    }

    private function summary(Collection $campaigns): array
    {
        $status = fn ($c) => strtolower((string) $c->status);
        $active = $campaigns->filter(fn ($c) => in_array($status($c), ['active', 'enable', 'enabled', '1', 'true'], true) || $c->status === true)->count();
        $paused = $campaigns->filter(fn ($c) => in_array($status($c), ['paused', 'disable', 'disabled', '0', 'false', ''], true) || $c->status === false)->count();
        $daily = $campaigns->filter(fn ($c) => $c->daily_budget !== null);
        $lifetime = $campaigns->filter(fn ($c) => $c->budget !== null);

        return [
            'total' => $campaigns->count(),
            'active' => $active,
            'paused' => $paused,
            'other' => max(0, $campaigns->count() - $active - $paused),
            'daily_budget_total' => round((float) $daily->sum(fn ($c) => (float) $c->daily_budget), 2),
            'daily_campaigns' => $daily->count(),
            'lifetime_budget_total' => round((float) $lifetime->sum(fn ($c) => (float) $c->budget), 2),
            'lifetime_campaigns' => $lifetime->count(),
        ];
    }
}
