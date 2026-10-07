<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\HubReturn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TikTok (docs/connection-hub-design.md §6): two separate TikTok apps and
 * authorizations - Login Kit (posts.tiktok) for posting and TikTok for
 * Business (ads.tiktok) for ads. The old Inbox "TikTok Messenger" connect
 * (audit flow #14) is gone: it never received a messaging scope. DMs
 * return as a Business step once TikTok approves Business Messaging
 * (flag tiktok.business_messaging).
 *
 * Login Kit tokens are refreshed (and rotated) by TiktokPostService, so
 * validation mirrors the account's latest token (MirroredTokenDriver).
 */
class TikTokDriver extends MirroredTokenDriver
{
    public const LOGIN_KIT = 'tiktok.login_kit';
    public const BUSINESS = 'tiktok.business';

    public function __construct(private SocialAuthService $socialAuth)
    {
    }

    public function platform(): string
    {
        return 'tiktok';
    }

    public function label(): string
    {
        return 'TikTok';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Video posting · TikTok Ads',
            'icons' => [['icon' => 'bxl-tiktok', 'brand' => 'tiktok']],
            'connect_label' => 'Connect with TikTok',
            'empty_title' => 'Connect TikTok for posting and ads',
            'empty_text' => 'TikTok uses two separate sign-ins: one for publishing videos to your account and one for your TikTok for Business advertiser accounts.',
            'benefits' => ['posting', 'ads'],
            'asset_groups' => [
                'tiktok_account' => ['label' => 'TikTok accounts', 'icon' => 'bxl-tiktok', 'brand' => 'tiktok', 'capabilities' => ['posting', 'insights']],
                'tiktok_ads' => ['label' => 'Advertiser accounts', 'icon' => 'bx-bullseye', 'brand' => 'tiktok', 'capabilities' => ['ads']],
            ],
            'step_icons' => [
                self::LOGIN_KIT => ['icon' => 'bxl-tiktok', 'brand' => 'tiktok'],
                self::BUSINESS => ['icon' => 'bx-bullseye', 'brand' => 'tiktok'],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return $asset->has_ads_permission && ! $asset->has_posting_permission ? 'tiktok_ads' : 'tiktok_account';
    }

    public function stepFor(string $capability): string
    {
        return $capability === 'ads' ? self::BUSINESS : self::LOGIN_KIT;
    }

    public function steps(): array
    {
        return [
            [
                'key' => self::LOGIN_KIT,
                'label' => 'TikTok account',
                'description' => 'Publish videos to your TikTok account.',
                'primary' => true,
                'available' => (bool) adminSetting('posts.tiktok.client_id'),
            ],
            [
                'key' => self::BUSINESS,
                'label' => 'TikTok for Business',
                'description' => 'Advertiser accounts for TikTok Ads.',
                'primary' => false,
                'available' => (bool) adminSetting('ads.tiktok.client_id'),
                'note' => 'TikTok uses a separate app and sign-in for ads.',
            ],
        ];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return $step === self::BUSINESS
            ? redirect()->route('admin.ads.redirect', 'tiktok')
            : $this->socialAuth->redirect('tiktok');
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        if ($connection->step === self::BUSINESS) {
            // Business API answers HTTP 200 with a non-zero `code` on errors.
            $response = Http::withHeaders(['Access-Token' => $asset->access_token])
                ->get(rtrim(adminSetting('ads.tiktok.base_url') ?: 'https://business-api.tiktok.com/open_api/v1.3/', '/') . '/oauth2/advertiser/get/', [
                    'app_id' => adminSetting('ads.tiktok.client_id'),
                    'secret' => adminSetting('ads.tiktok.client_secret'),
                ]);

            $code = $response->json('code');
            if ($response->successful() && (int) $code === 0) {
                return null;
            }

            $message = $response->json('message') ?: 'HTTP ' . $response->status();

            return preg_match('/access.?token/i', $message)
                ? ['status' => SocialConnection::NEEDS_REAUTH, 'error' => $message]
                : ['status' => SocialConnection::ERROR, 'error' => $message];
        }

        $response = Http::withToken($asset->access_token)->get('https://open.tiktokapis.com/v2/user/info/', ['fields' => 'open_id']);

        return $this->problemFromStatus($response->status(), $response->json('error.message'));
    }

    public function disconnect(SocialConnection $connection): void
    {
        // Login Kit: POST /v2/oauth/revoke/ (TikTok for Developers). The
        // Business API has no user-facing revoke.
        if ($connection->step === self::LOGIN_KIT) {
            // Through the model: the column is encrypted (value() would return ciphertext).
            $token = $connection->assets()->latest('updated_at')->first()?->access_token ?? $connection->access_token;

            if ($token) {
                try {
                    Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/revoke/', [
                        'client_key' => adminSetting('posts.tiktok.client_id'),
                        'client_secret' => adminSetting('posts.tiktok.client_secret'),
                        'token' => $token,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('TikTok token revoke failed; disconnecting locally.', ['connection_id' => $connection->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->disconnectLocally($connection);
    }
}
