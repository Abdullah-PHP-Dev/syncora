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
            'subtitle' => 'Ads in ChatGPT conversations · OpenAI Ads Manager',
            'icons' => [['icon' => 'bx-bot', 'brand' => 'openai']],
            'connect_label' => 'Add API key',
            'empty_title' => 'Connect your ChatGPT Ads account',
            'empty_text' => 'Create an Ads API key in OpenAI Ads Manager (ads.openai.com > Settings) and paste it here. Each key connects one ad account. ChatGPT Ads is available in the US, UK, Mexico, Brazil, Japan, South Korea and Europe.',
            'benefits' => ['ads'],
            'asset_groups' => [
                'openai_ads' => ['label' => 'Ad accounts', 'icon' => 'bx-bot', 'brand' => 'openai', 'capabilities' => ['ads']],
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
            'label' => 'ChatGPT Ads account',
            'description' => 'Paste an Ads API key from ads.openai.com > Settings. Each key connects one ad account.',
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
