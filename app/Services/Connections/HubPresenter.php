<?php

namespace App\Services\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Connections\InstagramDuplicates;
use Illuminate\Support\Collection;


/**
 * Shapes the Connection Hub page data (docs/connection-hub-design.md §10):
 * one card per platform with its steps, consents, status and assets.
 */
class HubPresenter
{
    /** Platforms not in the Hub yet (empty: every platform has a card). */
    private const UPCOMING = [];

    public function __construct(private ConnectionService $connections)
    {
    }

    public function forUser(int $userId): array
    {
        $cards = collect($this->connections->platforms())
            ->keys()
            ->map(fn ($platform) => $this->card($userId, $platform))
            ->values();

        return [
            'cards' => $cards,
            'upcoming' => self::UPCOMING,
            'summary' => [
                'connected' => $cards->filter(fn ($c) => $c['connected'])->count(),
                'attention' => $cards->sum(fn ($c) => collect($c['connections'])->filter(fn ($x) => $x['needs_attention'])->count()),
            ],
        ];
    }

    public function card(int $userId, string $platform): array
    {
        $driver = $this->connections->driver($platform);

        $connections = SocialConnection::where('user_id', $userId)
            ->where('platform', $platform)
            ->with(['assets' => fn ($q) => $q->orderBy('platform')->orderBy('name')])
            ->orderBy('id')
            ->get();

        $byStep = $connections->groupBy('step');

        return [
            'platform' => $platform,
            'label' => $driver->label(),
            'presentation' => $driver->presentation(),
            'connected' => $connections->contains(fn ($c) => $c->isUsable()),
            'steps' => collect($driver->steps())->map(fn ($step) => $step + [
                'connect_url' => route('admin.connections.connect', ['platform' => $platform, 'step' => $step['key']]),
                'connected' => $byStep->has($step['key']) && $byStep[$step['key']]->contains(fn ($c) => $c->isUsable()),
            ])->values(),
            'connections' => $connections->map(fn ($c) => $this->connection($c))->values(),
            // Meta only: in-page WhatsApp Embedded Signup (null until configured).
            'whatsapp_signup' => $platform === 'meta' ? MetaDriver::whatsappSignup() : null,
        ];
    }

    public function connection(SocialConnection $connection): array
    {
        $driver = $this->connections->driver($connection->platform);
        $steps = collect($driver->steps())->keyBy('key');
        $legacy = $driver->presentation()['legacy_steps'][$connection->step] ?? null;
        // An earlier consent reconnects through the step that replaces it.
        $reconnectStep = $legacy['upgrade_step'] ?? $connection->step;
        $reconnectUrl = route('admin.connections.connect', ['platform' => $connection->platform, 'step' => $reconnectStep]);
        // Short-lived access tokens renewed by a refresh token: show when
        // the refresh token runs out, as SocialConnection::statusFor() does.
        $expiresAt = ($driver->presentation()['expiry_follows_refresh'] ?? false) && $connection->refresh_token
            ? $connection->refresh_expires_at
            : $connection->expires_at;

        return [
            'id' => $connection->id,
            'step' => $connection->step,
            'step_label' => $steps[$connection->step]['label'] ?? ($legacy['label'] ?? $connection->step),
            // What this consent can grant, when the driver says (null = every
            // capability the platform offers).
            'offered_capabilities' => $steps[$connection->step]['capabilities'] ?? null,
            // Missing capabilities can be requested again on the primary
            // consent, or on any step that declares what it can grant.
            'upgradable' => (bool) ($steps[$connection->step]['primary'] ?? false) || isset($steps[$connection->step]['capabilities']),
            'upgrade' => $legacy ? ['note' => $legacy['upgrade_note'], 'url' => $reconnectUrl] : null,
            'status' => $connection->status,
            'needs_attention' => $connection->needsAttention(),
            'provider_account_id' => $connection->provider_account_id,
            'capabilities' => $connection->capabilities ?? [],
            'granted_scopes' => $connection->granted_scopes ?? [],
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_days' => $expiresAt ? (int) floor(now()->diffInDays($expiresAt, false)) : null,
            'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
            'last_error' => $connection->last_error,
            'reconnect_url' => $reconnectUrl,
            'assets' => $this->assets($driver, $connection, $connection->assets),
        ];
    }

    private function assets(ProviderDriver $driver, SocialConnection $connection, Collection $assets): array
    {
        $groups = $driver->presentation()['asset_groups'] ?? [];

        return $assets->map(function (SocialAccount $asset) use ($driver, $connection, $groups) {
            $kind = $driver->assetKind($asset);
            $available = array_values(array_intersect($groups[$kind]['capabilities'] ?? [], $connection->capabilities ?? []));

            return [
                'id' => $asset->id,
                'kind' => $kind,
                'platform' => $asset->platform,
                'name' => $asset->name,
                'username' => $asset->username,
                'avatar_url' => $asset->avatar_url,
                'external_id' => $asset->platform_account_id,
                'token_ok' => (bool) $asset->is_token_valid,
                // Same Instagram account as a Page-linked one (InstagramDuplicates).
                // X Ads approval (social_account_ad_details.account_status).
                'provider_status' => $asset->platform === 'x' ? match ($asset->adDetails?->account_status) {
                    'REJECTED' => __('connections.provider_status.x_rejected'),
                    'PENDING', 'UNDER_REVIEW' => __('connections.provider_status.x_review'),
                    'deleted' => __('connections.provider_status.x_deleted'),
                    default => null,
                } : null,
                'duplicate_of' => isset($asset->metadata['settings'][InstagramDuplicates::MARK]) ? __('connections.duplicate_of_page') : null,
                // Another sign-in for an account already listed (TikTok comments
                // -> its posting account): the overview shows that one card only.
                'linked_asset_id' => isset($asset->metadata['posting_account_id']) ? (int) $asset->metadata['posting_account_id'] : null,
                'available_capabilities' => $available,
                // null = everything the connection allows is on
                'enabled_capabilities' => $asset->enabled_capabilities === null
                    ? $available
                    : array_values(array_intersect($available, $asset->enabled_capabilities)),
            ];
        })->groupBy('kind')->map->values()->all();
    }

}
