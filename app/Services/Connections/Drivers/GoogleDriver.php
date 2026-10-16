<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\GoogleClient;
use App\Support\Connections\GrantedScopes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google: YouTube, Google Ads and Analytics in one OAuth consent on one
 * client (docs/connection-hub-design.md §5). Consents made earlier through
 * the Ads module's separate ads.google client stay working and show as
 * "earlier connection" with a one-time upgrade.
 */
class GoogleDriver extends BaseDriver
{
    public const OAUTH = 'google.oauth';
    public const ADS_LEGACY = 'google.ads_legacy';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function __construct(private SocialAuthService $socialAuth)
    {
    }

    public function platform(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => __('connections.platforms.google.subtitle'),
            'icons' => [['icon' => 'bxl-google', 'brand' => 'google'], ['icon' => 'bxl-youtube', 'brand' => 'youtube']],
            'connect_label' => __('connections.platforms.google.connect_label'),
            'empty_title' => __('connections.platforms.google.empty_title'),
            'empty_text' => __('connections.platforms.google.empty_text'),
            'benefits' => ['posting', 'ads', 'insights'],
            'asset_groups' => [
                'youtube' => ['label' => __('connections.platforms.google.groups.youtube'), 'icon' => 'bxl-youtube', 'brand' => 'youtube', 'capabilities' => ['posting', 'insights']],
                'google_ads' => ['label' => __('connections.platforms.google.groups.google_ads'), 'icon' => 'bx-bullseye', 'brand' => 'google', 'capabilities' => ['ads']],
            ],
            'step_icons' => [
                self::OAUTH => ['icon' => 'bxl-google', 'brand' => 'google'],
                self::ADS_LEGACY => ['icon' => 'bx-bullseye', 'brand' => 'google'],
            ],
            'legacy_steps' => [
                self::ADS_LEGACY => [
                    'label' => __('connections.platforms.google.legacy.label'),
                    'upgrade_note' => __('connections.platforms.google.legacy.upgrade_note'),
                    'upgrade_step' => self::OAUTH,
                ],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return $asset->platform === 'youtube' ? 'youtube' : 'google_ads';
    }

    public function steps(): array
    {
        return [[
            'key' => self::OAUTH,
            'label' => __('connections.platforms.google.steps.oauth.label'),
            'description' => __('connections.platforms.google.steps.oauth.description'),
            'primary' => true,
            'available' => (bool) GoogleClient::credentials()['client_id'],
        ]];
    }

    public function connect(string $step): Response
    {
        session(['social_oauth_return_to' => 'hub']);

        return $this->socialAuth->redirect('google');
    }

    public function validate(SocialConnection $connection): SocialConnection
    {
        if (! $connection->refresh_token && ! $connection->access_token) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'No tokens stored - reconnect.');
        }

        // A refresh both proves the grant is alive and gives a token to
        // read the scopes with. Must use the client that issued it.
        if ($connection->refresh_token) {
            $client = GoogleClient::credentials($connection->provider_app ?: GoogleClient::current());
            $refresh = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'refresh_token' => $connection->refresh_token,
            ]);

            if (! $refresh->successful()) {
                $error = $refresh->json('error');
                $message = $refresh->json('error_description') ?: ($error ?: 'HTTP ' . $refresh->status());

                // invalid_grant: the refresh token was revoked or expired - a
                // new consent is the only fix (Google OAuth 2.0 docs).
                return $error === 'invalid_grant'
                    ? $this->mark($connection, SocialConnection::NEEDS_REAUTH, $message)
                    : $this->mark($connection, SocialConnection::ERROR, $message);
            }

            $this->storeRefreshedToken($connection, $refresh->json());
        }

        $info = Http::get(self::TOKENINFO_URL, ['access_token' => $connection->access_token]);

        if (! $info->successful()) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, $info->json('error_description') ?: 'Google no longer accepts this token.');
        }

        return $this->markHealthy($connection, GrantedScopes::fromTokenResponse($info->json()));
    }

    public function disconnect(SocialConnection $connection): void
    {
        // Revoking the refresh token removes the whole grant (Google OAuth 2.0 docs).
        $token = $connection->refresh_token ?: $connection->access_token;

        if ($token) {
            $response = Http::asForm()->post(self::REVOKE_URL, ['token' => $token]);

            if (! $response->successful()) {
                Log::warning('Google token revoke failed; disconnecting locally.', ['connection_id' => $connection->id, 'status' => $response->status()]);
            }
        }

        $this->disconnectLocally($connection);
    }

    /** New access token on the connection and its assets (modules read the asset columns). */
    private function storeRefreshedToken(SocialConnection $connection, array $token): void
    {
        $expiresAt = now()->addSeconds((int) ($token['expires_in'] ?? 3600));

        DB::transaction(function () use ($connection, $token, $expiresAt) {
            $connection->fill([
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? $connection->refresh_token,
                'expires_at' => $expiresAt,
                'last_refreshed_at' => now(),
            ])->save();

            foreach ($connection->assets as $asset) {
                $asset->forceFill(['access_token' => $token['access_token'], 'expires_at' => $expiresAt])->saveQuietly();
            }
        });
    }
}
