<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\ConnectionFlags;
use App\Support\Connections\GrantedScopes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meta: Facebook Pages, Instagram, Messenger and Ads in one Facebook Login
 * for Business consent, plus WhatsApp (Embedded Signup) and Instagram Login
 * as separate steps (docs/connection-hub-design.md §4). The consent and the
 * asset discovery stay in SocialAuthService / PostAccountController - the
 * callback URLs registered in the Meta App Dashboard don't change.
 */
class MetaDriver extends BaseDriver
{
    public const LOGIN = 'meta.login';
    public const WHATSAPP = 'meta.whatsapp';
    public const INSTAGRAM_LOGIN = 'meta.instagram_login';

    public function __construct(private SocialAuthService $socialAuth)
    {
    }

    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Facebook Pages · Instagram · Messenger · WhatsApp · Ads',
            'icons' => [['icon' => 'bxl-facebook', 'brand' => 'facebook'], ['icon' => 'bxl-instagram', 'brand' => 'instagram'], ['icon' => 'bxl-whatsapp', 'brand' => 'whatsapp']],
            'connect_label' => 'Connect with Facebook',
            'empty_title' => 'One consent for everything Meta',
            'empty_text' => 'Choose the Pages, Instagram accounts and ad accounts SocialEaz may use. You can change your choice at any time, here or in your Facebook settings.',
            'benefits' => ['posting', 'messaging', 'ads', 'insights'],
            'asset_groups' => [
                'page' => ['label' => 'Facebook Pages', 'icon' => 'bxl-facebook', 'brand' => 'facebook', 'capabilities' => ['posting', 'messaging', 'insights']],
                'instagram' => ['label' => 'Instagram accounts', 'icon' => 'bxl-instagram', 'brand' => 'instagram', 'capabilities' => ['posting', 'messaging', 'insights']],
                'ad_account' => ['label' => 'Ad accounts', 'icon' => 'bx-bullseye', 'brand' => 'meta', 'capabilities' => ['ads']],
                'whatsapp' => ['label' => 'WhatsApp numbers', 'icon' => 'bxl-whatsapp', 'brand' => 'whatsapp', 'capabilities' => ['messaging', 'posting']],
            ],
            'step_icons' => [
                self::LOGIN => ['icon' => 'bxl-facebook', 'brand' => 'facebook'],
                self::WHATSAPP => ['icon' => 'bxl-whatsapp', 'brand' => 'whatsapp'],
                self::INSTAGRAM_LOGIN => ['icon' => 'bxl-instagram', 'brand' => 'instagram'],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return match (true) {
            $asset->platform === 'whatsapp' => 'whatsapp',
            $asset->platform === 'instagram' => 'instagram',
            $asset->account_type === 'ad_account' || ($asset->has_ads_permission && ! $asset->has_posting_permission) => 'ad_account',
            default => 'page',
        };
    }

    public function steps(): array
    {
        $whatsappMerged = ConnectionFlags::on('meta.whatsapp_in_main_config');

        $steps = [[
            'key' => self::LOGIN,
            'label' => 'Facebook & Instagram',
            'description' => $whatsappMerged
                ? 'Pages, Instagram business accounts, Messenger, WhatsApp and ad accounts in one consent.'
                : 'Pages, Instagram business accounts, Messenger and ad accounts in one consent.',
            'primary' => true,
            'available' => (bool) adminSetting('posts.facebook.client_id'),
        ]];

        if (! $whatsappMerged) {
            $steps[] = [
                'key' => self::WHATSAPP,
                'label' => 'WhatsApp Business',
                'description' => 'Embedded Signup: pick or create a WhatsApp Business number.',
                'primary' => false,
                'available' => (bool) $this->whatsappConfigId(),
                'note' => $this->whatsappConfigId() ? null : 'Embedded Signup needs a configuration in the Meta app - until then, enter a number manually.',
            ];
        }

        if (ConnectionFlags::on('meta.instagram_login')) {
            $steps[] = [
                'key' => self::INSTAGRAM_LOGIN,
                'label' => 'Instagram without a Facebook Page',
                'description' => 'Instagram Login for professional accounts that aren\'t linked to a Page.',
                'primary' => false,
                'available' => (bool) adminSetting('posts.instagram.client_id'),
            ];
        }

        return $steps;
    }

    public function connect(string $step): Response
    {
        // SocialAuthService / PostAccountController send the user back here.
        session(['social_oauth_return_to' => 'hub']);

        return match ($step) {
            self::LOGIN => $this->socialAuth->redirect('facebook'),
            self::INSTAGRAM_LOGIN => redirect()->route('admin.post-accounts.instagram.redirect'),
            // Embedded Signup runs in the browser (FB JS SDK) on the Hub's
            // Meta card itself - see whatsappSignup().
            self::WHATSAPP => redirect()->to(route('admin.connections.index') . '#meta'),
        };
    }

    public function validate(SocialConnection $connection): SocialConnection
    {
        if (! $connection->access_token) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'No access token stored - reconnect.');
        }

        $response = match ($connection->step) {
            self::INSTAGRAM_LOGIN => Http::get('https://graph.instagram.com/me', ['fields' => 'id', 'access_token' => $connection->access_token]),
            self::WHATSAPP => Http::get($this->graph($connection->provider_account_id), $this->auth($connection->access_token) + ['fields' => 'id']),
            default => Http::get($this->graph('me/permissions'), $this->auth($connection->access_token)),
        };

        if ($response->successful()) {
            return $this->markHealthy($connection, $connection->step === self::LOGIN
                ? GrantedScopes::fromMetaPermissions($response->json())
                : null);
        }

        $error = $response->json('error') ?? [];
        $message = $error['message'] ?? ('HTTP ' . $response->status());

        // Graph error 190 = access token problem. Subcode 458 = the user
        // removed the app; 460/463/464/467 = a fresh login fixes it.
        // https://developers.facebook.com/docs/graph-api/guides/error-handling
        if ((int) ($error['code'] ?? 0) === 190) {
            return (int) ($error['error_subcode'] ?? 0) === 458
                ? $this->mark($connection, SocialConnection::REVOKED, $message, revoked: true)
                : $this->mark($connection, SocialConnection::NEEDS_REAUTH, $message);
        }

        return $this->mark($connection, SocialConnection::ERROR, $message);
    }

    public function disconnect(SocialConnection $connection): void
    {
        // Facebook Login: de-authorize the app for this person -
        // DELETE /{user-id}/permissions (Meta "Revoking Permissions").
        if ($connection->step === self::LOGIN && $connection->access_token) {
            $response = Http::delete($this->graph('me/permissions') . '?' . http_build_query($this->auth($connection->access_token)));

            if (! $response->successful()) {
                Log::warning('Meta permission revoke failed; disconnecting locally.', ['connection_id' => $connection->id, 'status' => $response->status()]);
            }
        }

        $this->disconnectLocally($connection);
    }


    private function graph(?string $path): string
    {
        $base = adminSetting('posts.facebook.base_url') ?: 'https://graph.facebook.com/v25.0/';

        return rtrim($base, '/') . '/' . ltrim((string) $path, '/');
    }

    /** access_token + appsecret_proof (required when "Require App Secret" is on). */
    private function auth(string $token): array
    {
        return [
            'access_token' => $token,
            'appsecret_proof' => hash_hmac('sha256', $token, (string) adminSetting('posts.facebook.client_secret')),
        ];
    }

    private function whatsappConfigId(): ?string
    {
        return adminSetting('connections.meta.whatsapp_config_id') ?: adminSetting('messaging.meta.whatsapp_config_id');
    }

    /**
     * Everything the browser needs to run WhatsApp Embedded Signup, on the
     * ONE Meta app (posts.facebook, design doc §4) - the same app the
     * backend exchanges the code with (PostAccountController::
     * storeWhatsappEmbedded), so the two can never disagree. Null until an
     * Embedded Signup configuration exists.
     *
     * @return array{app_id: string, config_id: string, graph_version: string, store_url: string}|null
     */
    public static function whatsappSignup(): ?array
    {
        $configId = adminSetting('connections.meta.whatsapp_config_id') ?: adminSetting('messaging.meta.whatsapp_config_id');
        $appId = adminSetting('posts.facebook.client_id');

        if (! $configId || ! $appId) {
            return null;
        }

        return [
            'app_id' => (string) $appId,
            'config_id' => (string) $configId,
            'graph_version' => adminSetting('posts.facebook.graph_version') ?: (adminSetting('messaging.meta.graph_version') ?: 'v21.0'),
            'store_url' => route('admin.post-accounts.whatsapp.embedded'),
        ];
    }
}
