<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Support\Connections\OpenAiAds;
use Symfony\Component\HttpFoundation\Response;

/**
 * ChatGPT Ads (OpenAI Ads API). No OAuth: the user pastes an Ads API key
 * from ads.openai.com > Settings, and each key belongs to exactly one ad
 * account - one connection per key. Validation calls GET /ad_account with
 * the stored key. Keys can only be revoked in OpenAI's Ads Manager, so a
 * disconnect is local.
 */
class OpenAiDriver extends MirroredTokenDriver
{
    public const API_KEY = 'openai.api_key';

    public function platform(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'ChatGPT Ads';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => __('connections.platforms.openai.subtitle'),
            'icons' => [['icon' => 'bx-bot', 'brand' => 'openai']],
            'connect_label' => __('connections.platforms.openai.connect_label'),
            'empty_title' => __('connections.platforms.openai.empty_title'),
            'empty_text' => __('connections.platforms.openai.empty_text'),
            'benefits' => ['ads'],
            'asset_groups' => [
                'openai_ads' => ['label' => __('connections.platforms.openai.groups.openai_ads'), 'icon' => 'bx-bot', 'brand' => 'openai', 'capabilities' => ['ads']],
            ],
            'step_icons' => [self::API_KEY => ['icon' => 'bx-key', 'brand' => 'openai']],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return 'openai_ads';
    }

    public function steps(): array
    {
        return [[
            'key' => self::API_KEY,
            'label' => __('connections.platforms.openai.steps.api_key.label'),
            'description' => __('connections.platforms.openai.steps.api_key.description'),
            'primary' => true,
            'available' => true,
            // The Hub renders a key form for this step instead of a sign-in link.
            'input' => 'api_key',
        ]];
    }

    public function connect(string $step): Response
    {
        return redirect()->to(route('admin.connections.index') . '#openai');
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        $response = OpenAiAds::adAccount((string) $asset->access_token);

        return $this->problemFromStatus($response->status(), OpenAiAds::errorMessage($response, 'OpenAI Ads did not accept the API key.'));
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->disconnectLocally($connection);
    }
}
