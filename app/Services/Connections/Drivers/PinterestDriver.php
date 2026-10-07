<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Support\Connections\HubReturn;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pinterest: posting (posts.pinterest). PinterestPostService refreshes the
 * tokens, so validation mirrors them; the refresh token's own lifetime
 * (refresh_token_expires_in) governs status. No documented revoke:
 * disconnect is local.
 */
class PinterestDriver extends MirroredTokenDriver
{
    public const LOGIN = 'pinterest.login';

    public function platform(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return 'Pinterest';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Pins and boards',
            'icons' => [['icon' => 'bxl-pinterest', 'brand' => 'pinterest']],
            'connect_label' => 'Connect with Pinterest',
            'empty_title' => 'Connect your Pinterest account',
            'empty_text' => 'Publish Pins to your boards. To fully remove access later, also remove SocialEaz in your Pinterest settings.',
            'benefits' => ['posting', 'insights'],
            'asset_groups' => [
                'pinterest_account' => ['label' => 'Pinterest accounts', 'icon' => 'bxl-pinterest', 'brand' => 'pinterest', 'capabilities' => ['posting', 'insights']],
            ],
            'step_icons' => [self::LOGIN => ['icon' => 'bxl-pinterest', 'brand' => 'pinterest']],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return 'pinterest_account';
    }

    public function steps(): array
    {
        return [[
            'key' => self::LOGIN,
            'label' => 'Pinterest account',
            'description' => 'Publish Pins to your boards.',
            'primary' => true,
            'available' => (bool) adminSetting('posts.pinterest.client_id'),
        ]];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return redirect()->route('admin.post-accounts.pinterest.redirect');
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        $response = Http::withToken($asset->access_token)->get('https://api.pinterest.com/v5/user_account');

        return $this->problemFromStatus($response->status(), $response->json('message'));
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->disconnectLocally($connection);
    }
}
