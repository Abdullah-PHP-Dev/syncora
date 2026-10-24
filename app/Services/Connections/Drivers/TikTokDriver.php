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
 * TikTok (docs/connection-hub-design.md §6): three authorizations that
 * cannot be combined - each has its own authorize and token endpoint and
 * returns a different kind of token:
 *   - Login Kit (posts.tiktok app, open.tiktokapis.com): posting, insights.
 *   - Advertiser authorization (ads.tiktok app, portal/auth): ads.
 *   - Account-holder authorization (ads.tiktok app, tt_user/oauth2/token):
 *     comments (TiktokCommentsService).
 * Each step declares what it can grant ('capabilities'), so the Hub never
 * offers to unlock one product's feature through another's sign-in.
 * The old Inbox "TikTok Messenger" connect
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
    /** Organic Accounts API (comments) - account-holder authorization of the Business app. */
    public const COMMENTS = 'tiktok.comments';

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
            'subtitle' => __('connections.platforms.tiktok.subtitle'),
            'icons' => [['icon' => 'bxl-tiktok', 'brand' => 'tiktok']],
            'connect_label' => __('connections.platforms.tiktok.connect_label'),
            'empty_title' => __('connections.platforms.tiktok.empty_title'),
            'empty_text' => __('connections.platforms.tiktok.empty_text'),
            'benefits' => ['posting', 'insights', 'engagement', 'ads'],
            // Access tokens last 24h and are refreshed on use; the refresh token's expiry is what matters.
            'expiry_follows_refresh' => true,
            'asset_groups' => [
                'tiktok_account' => ['label' => __('connections.platforms.tiktok.groups.tiktok_account'), 'icon' => 'bxl-tiktok', 'brand' => 'tiktok', 'capabilities' => ['posting', 'insights']],
                'tiktok_ads' => ['label' => __('connections.platforms.tiktok.groups.tiktok_ads'), 'icon' => 'bx-bullseye', 'brand' => 'tiktok', 'capabilities' => ['ads']],
                'tiktok_comments' => ['label' => __('connections.platforms.tiktok.groups.tiktok_comments'), 'icon' => 'bx-message-rounded-dots', 'brand' => 'tiktok', 'capabilities' => ['engagement']],
            ],
            'step_icons' => [
                self::LOGIN_KIT => ['icon' => 'bxl-tiktok', 'brand' => 'tiktok'],
                self::BUSINESS => ['icon' => 'bx-bullseye', 'brand' => 'tiktok'],
                self::COMMENTS => ['icon' => 'bx-message-rounded-dots', 'brand' => 'tiktok'],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        if ($asset->account_type === \App\Services\PostServices\TiktokCommentsService::ACCOUNT_TYPE) {
            return 'tiktok_comments';
        }

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
                'label' => __('connections.platforms.tiktok.steps.login_kit.label'),
                'description' => __('connections.platforms.tiktok.steps.login_kit.description'),
                'primary' => true,
                'available' => (bool) adminSetting('posts.tiktok.client_id'),
                'capabilities' => ['posting', 'insights'],
            ],
            [
                'key' => self::BUSINESS,
                'label' => __('connections.platforms.tiktok.steps.business.label'),
                'description' => __('connections.platforms.tiktok.steps.business.description'),
                'primary' => false,
                'available' => (bool) adminSetting('ads.tiktok.client_id'),
                'note' => __('connections.platforms.tiktok.steps.business.note'),
                'capabilities' => ['ads'],
                // Signing in again adds advertisers granted since.
                'connected_action' => __('connections.platforms.tiktok.steps.business.connected_action'),
            ],
            [
                'key' => self::COMMENTS,
                'label' => __('connections.platforms.tiktok.steps.comments.label'),
                'description' => __('connections.platforms.tiktok.steps.comments.description'),
                'primary' => false,
                'available' => (bool) adminSetting('ads.tiktok.client_id'),
                'note' => __('connections.platforms.tiktok.steps.comments.note'),
                'capabilities' => ['engagement'],
                // Nothing to add once connected: missing permissions and
                // reconnects are offered on the connection itself.
                'connected_action' => null,
            ],
        ];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return match ($step) {
            self::BUSINESS => redirect()->route('admin.ads.redirect', 'tiktok'),
            self::COMMENTS => app(\App\Services\PostServices\TiktokCommentsService::class)->redirect(),
            default => $this->socialAuth->redirect('tiktok'),
        };
    }

    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        if ($connection->step === self::COMMENTS) {
            $profile = app(\App\Services\PostServices\TiktokCommentsService::class)->profile((string) $asset->access_token, (string) ($asset->metadata['business_id'] ?? ''));

            return $profile ? null : ['status' => SocialConnection::NEEDS_REAUTH, 'error' => 'TikTok comments access was refused - reconnect "TikTok comments".'];
        }

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
