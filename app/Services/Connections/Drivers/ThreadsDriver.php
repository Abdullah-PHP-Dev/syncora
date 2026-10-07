<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Support\Connections\HubReturn;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Threads: posting via Threads Login (posts.threads). Long-lived tokens
 * with no refresh token - ThreadsPostService extends them - so status
 * follows the token's expiry. No documented revoke: disconnect is local.
 */
class ThreadsDriver extends MirroredTokenDriver
{
    public const LOGIN = 'threads.login';

    public function platform(): string
    {
        return 'threads';
    }

    public function label(): string
    {
        return 'Threads';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Posting',
            'icons' => [['icon' => 'bx-at', 'brand' => 'threads']],
            'connect_label' => 'Connect with Threads',
            'empty_title' => 'Connect your Threads profile',
            'empty_text' => 'Publish and schedule posts to Threads. To fully remove access later, also remove SocialEaz in your Threads settings.',
            'benefits' => ['posting', 'insights'],
            'asset_groups' => [
                'threads_profile' => ['label' => 'Threads profiles', 'icon' => 'bx-at', 'brand' => 'threads', 'capabilities' => ['posting', 'insights']],
            ],
            'step_icons' => [self::LOGIN => ['icon' => 'bx-at', 'brand' => 'threads']],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return 'threads_profile';
    }

    public function steps(): array
    {
        return [[
            'key' => self::LOGIN,
            'label' => 'Threads profile',
            'description' => 'Publish and schedule posts to Threads.',
            'primary' => true,
            'available' => (bool) adminSetting('posts.threads.client_id'),
        ]];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return redirect()->route('admin.post-accounts.threads.redirect');
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        $response = Http::get('https://graph.threads.net/v1.0/me', ['fields' => 'id', 'access_token' => $asset->access_token]);

        // Graph-style errors: code 190 = the token is no longer valid.
        if ((int) $response->json('error.code') === 190) {
            return ['status' => SocialConnection::NEEDS_REAUTH, 'error' => $response->json('error.message') ?: 'Threads no longer accepts this token.'];
        }

        return $this->problemFromStatus($response->status(), $response->json('error.message'));
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->disconnectLocally($connection);
    }
}
