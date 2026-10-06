<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialConnection;
use App\Services\Connections\ProviderDriver;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\ConnectionFlags;
use App\Support\Connections\GrantedScopes;
use Illuminate\Support\Facades\DB;
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
class MetaDriver implements ProviderDriver
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
                'note' => $this->whatsappConfigId() ? null : 'Needs a WhatsApp Embedded Signup configuration in the Meta app.',
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
            $scopes = $connection->step === self::LOGIN
                ? (GrantedScopes::fromMetaPermissions($response->json()) ?? $connection->granted_scopes)
                : $connection->granted_scopes;

            $connection->fill([
                'granted_scopes' => $scopes,
                'capabilities' => $scopes
                    ? SocialConnection::capabilitiesFrom($scopes, config('connections.capabilities.meta'))
                    : $connection->capabilities,
                'status' => SocialConnection::statusFor($connection->access_token, $connection->expires_at, false),
                'last_error' => null,
                'last_checked_at' => now(),
            ])->save();

            return $connection;
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

        DB::transaction(function () use ($connection) {
            $connection->fill([
                'access_token' => null,
                'refresh_token' => null,
                'status' => SocialConnection::REVOKED,
                'revoked_at' => now(),
                'last_error' => SocialConnection::DISCONNECTED_BY_USER,
            ])->save();

            // Assets stay (campaign/post history points at them) but can no
            // longer act; reconnecting re-links and re-validates them.
            DB::table('social_accounts')->where('social_connection_id', $connection->id)->update([
                'is_token_valid' => false,
                'access_token' => null,
                'asset_token' => null,
                'user_token' => null,
            ]);
        });
    }

    private function mark(SocialConnection $connection, string $status, string $error, bool $revoked = false): SocialConnection
    {
        $connection->fill([
            'status' => $status,
            'last_error' => $error,
            'last_checked_at' => now(),
            'revoked_at' => $revoked ? now() : $connection->revoked_at,
        ])->save();

        return $connection;
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
