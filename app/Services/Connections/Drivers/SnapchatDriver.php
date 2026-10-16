<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Support\Connections\HubReturn;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Snapchat (audit §3): the Marketing API consent (ads.snapchat,
 * snapchat-marketing-api) for Snapchat Ads. Organic posting through the
 * Public Profile API is allowlist-only and uses its own scope - flag
 * snapchat.public_profile, not built yet. SnapchatAdService refreshes the
 * tokens, so validation mirrors them. No documented revoke: disconnect is
 * local.
 */
class SnapchatDriver extends MirroredTokenDriver
{
    public const MARKETING = 'snapchat.marketing';

    public function platform(): string
    {
        return 'snapchat';
    }

    public function label(): string
    {
        return 'Snapchat';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => __('connections.platforms.snapchat.subtitle'),
            'icons' => [['icon' => 'bxl-snapchat', 'brand' => 'snapchat']],
            'connect_label' => __('connections.platforms.snapchat.connect_label'),
            'empty_title' => __('connections.platforms.snapchat.empty_title'),
            'empty_text' => __('connections.platforms.snapchat.empty_text'),
            'benefits' => ['ads'],
            'asset_groups' => [
                'snapchat_ads' => ['label' => __('connections.platforms.snapchat.groups.snapchat_ads'), 'icon' => 'bx-bullseye', 'brand' => 'snapchat', 'capabilities' => ['ads']],
            ],
            'step_icons' => [self::MARKETING => ['icon' => 'bxl-snapchat', 'brand' => 'snapchat']],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return 'snapchat_ads';
    }

    public function steps(): array
    {
        return [[
            'key' => self::MARKETING,
            'label' => __('connections.platforms.snapchat.steps.marketing.label'),
            'description' => __('connections.platforms.snapchat.steps.marketing.description'),
            'primary' => true,
            'available' => (bool) adminSetting('ads.snapchat.client_id'),
        ]];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return redirect()->route('admin.ads.redirect', 'snapchat');
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        $response = Http::withToken($asset->access_token)->get('https://adsapi.snapchat.com/v1/me');

        return $this->problemFromStatus($response->status(), $response->json('debug_message') ?? $response->json('request_status'));
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->disconnectLocally($connection);
    }
}
