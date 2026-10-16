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
            'subtitle' => __('connections.platforms.threads.subtitle'),
            'icons' => [['icon' => 'bx-at', 'brand' => 'threads']],
            'connect_label' => __('connections.platforms.threads.connect_label'),
            'empty_title' => __('connections.platforms.threads.empty_title'),
            'empty_text' => __('connections.platforms.threads.empty_text'),
            'benefits' => ['posting', 'insights'],
            'asset_groups' => [
                'threads_profile' => ['label' => __('connections.platforms.threads.groups.threads_profile'), 'icon' => 'bx-at', 'brand' => 'threads', 'capabilities' => ['posting', 'insights']],
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
            'label' => __('connections.platforms.threads.steps.login.label'),
            'description' => __('connections.platforms.threads.steps.login.description'),
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
