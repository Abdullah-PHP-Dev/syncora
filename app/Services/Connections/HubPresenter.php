<?php

namespace App\Services\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\Drivers\MetaDriver;
use Illuminate\Support\Collection;

/**
 * Shapes the Connection Hub page data (docs/connection-hub-design.md §10):
 * one card per platform with its steps, consents, status and assets.
 */
class HubPresenter
{
    /** Which capabilities make sense per asset kind (the asset picker's toggles). */
    private const ASSET_CAPABILITIES = [
        'page' => ['posting', 'messaging', 'insights'],
        'instagram' => ['posting', 'messaging', 'insights'],
        'ad_account' => ['ads'],
        'whatsapp' => ['messaging', 'posting'],
        'youtube' => ['posting', 'insights'],
        'google_ads' => ['ads'],
        'x_account' => ['posting', 'messaging'],
        'x_ads' => ['ads'],
    ];

    /** Platforms that move into the Hub in later commits (design §11). */
    private const UPCOMING = [
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'detail' => 'Pages, Ads'],
        ['key' => 'tiktok', 'label' => 'TikTok', 'detail' => 'Posting, Ads'],
        ['key' => 'snapchat', 'label' => 'Snapchat', 'detail' => 'Ads'],
        ['key' => 'threads', 'label' => 'Threads', 'detail' => 'Posting'],
        ['key' => 'pinterest', 'label' => 'Pinterest', 'detail' => 'Posting'],
    ];

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

        return [
            'id' => $connection->id,
            'step' => $connection->step,
            'step_label' => $steps[$connection->step]['label'] ?? ($legacy['label'] ?? $connection->step),
            // Missing capabilities can be requested again on the primary consent.
            'upgradable' => (bool) ($steps[$connection->step]['primary'] ?? false),
            'upgrade' => $legacy ? ['note' => $legacy['upgrade_note'], 'url' => $reconnectUrl] : null,
            'status' => $connection->status,
            'needs_attention' => $connection->needsAttention(),
            'provider_account_id' => $connection->provider_account_id,
            'capabilities' => $connection->capabilities ?? [],
            'granted_scopes' => $connection->granted_scopes ?? [],
            'expires_at' => $connection->expires_at?->toIso8601String(),
            'expires_in_days' => $connection->expires_at ? (int) floor(now()->diffInDays($connection->expires_at, false)) : null,
            'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
            'last_error' => $connection->last_error,
            'reconnect_url' => $reconnectUrl,
            'assets' => $this->assets($connection, $connection->assets),
        ];
    }

    private function assets(SocialConnection $connection, Collection $assets): array
    {
        return $assets->map(function (SocialAccount $asset) use ($connection) {
            $kind = $this->kind($asset);
            $available = array_values(array_intersect(self::ASSET_CAPABILITIES[$kind] ?? [], $connection->capabilities ?? []));

            return [
                'id' => $asset->id,
                'kind' => $kind,
                'platform' => $asset->platform,
                'name' => $asset->name,
                'username' => $asset->username,
                'avatar_url' => $asset->avatar_url,
                'external_id' => $asset->platform_account_id,
                'token_ok' => (bool) $asset->is_token_valid,
                'available_capabilities' => $available,
                // null = everything the connection allows is on
                'enabled_capabilities' => $asset->enabled_capabilities === null
                    ? $available
                    : array_values(array_intersect($available, $asset->enabled_capabilities)),
            ];
        })->groupBy('kind')->map->values()->all();
    }

    private function kind(SocialAccount $asset): string
    {
        return match (true) {
            $asset->platform === 'youtube' => 'youtube',
            $asset->platform === 'google' => 'google_ads',
            $asset->platform === 'x' => $asset->has_ads_permission && ! $asset->has_posting_permission && ! $asset->has_messaging_permission ? 'x_ads' : 'x_account',
            $asset->platform === 'whatsapp' => 'whatsapp',
            $asset->platform === 'instagram' => 'instagram',
            $asset->account_type === 'ad_account' || ($asset->has_ads_permission && ! $asset->has_posting_permission) => 'ad_account',
            default => 'page',
        };
    }
}
