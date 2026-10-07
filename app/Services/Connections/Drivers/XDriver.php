<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Support\Connections\ConnectionFlags;
use App\Support\Connections\HubReturn;
use App\Support\Connections\XOAuth1;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * X (docs/connection-hub-design.md §6b). The surviving X app is posts.x:
 *  - x.oauth2: posts.x OAuth 2.0 - posts, DMs, X Chat (one consent instead
 *    of the separate Publishing and Inbox buttons)
 *  - x.ads:    ads.x OAuth 1.0a - X Ads, a separate app until X approves
 *    Ads API access for posts.x; then both fold into one OAuth 1.0a
 *    consent on posts.x.
 *
 * X rotates OAuth 2.0 refresh tokens on every use and the posting / DM
 * services refresh the account's tokens themselves, so the OAuth 2.0 step
 * validates as a MirroredTokenDriver (never refreshes).
 */
class XDriver extends MirroredTokenDriver
{
    public const OAUTH2 = 'x.oauth2';
    public const ADS = 'x.ads';

    public function platform(): string
    {
        return 'x';
    }

    public function label(): string
    {
        return 'X';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Posts · Direct Messages · X Chat · Ads',
            'icons' => [['icon' => 'bxl-x-logo', 'brand' => 'x']],
            'connect_label' => 'Connect with X',
            'empty_title' => 'Connect your X account once',
            'empty_text' => 'One sign-in covers publishing and your X inbox, including X Chat. You can remove access at any time, here or in your X settings.',
            'benefits' => ['posting', 'messaging', 'ads'],
            'asset_groups' => [
                'x_account' => ['label' => 'X accounts', 'icon' => 'bxl-x-logo', 'brand' => 'x', 'capabilities' => ['posting', 'messaging']],
                'x_ads' => ['label' => 'X Ads accounts', 'icon' => 'bx-bullseye', 'brand' => 'x', 'capabilities' => ['ads']],
            ],
            'step_icons' => [
                self::OAUTH2 => ['icon' => 'bxl-x-logo', 'brand' => 'x'],
                self::ADS => ['icon' => 'bx-bullseye', 'brand' => 'x'],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return $asset->has_ads_permission && ! $asset->has_posting_permission && ! $asset->has_messaging_permission ? 'x_ads' : 'x_account';
    }

    public function stepFor(string $capability): string
    {
        return $capability === 'ads' ? self::ADS : self::OAUTH2;
    }

    public function steps(): array
    {
        $steps = [[
            'key' => self::OAUTH2,
            'label' => 'X account',
            'description' => 'Posts, Direct Messages and X Chat in one consent.',
            'primary' => true,
            'available' => (bool) adminSetting('posts.x.client_id'),
        ]];

        if (ConnectionFlags::on('x.ads')) {
            $steps[] = [
                'key' => self::ADS,
                'label' => 'X Ads',
                'description' => 'Promoted posts on your X Ads accounts.',
                'primary' => false,
                'available' => XOAuth1::consumer()[0] !== '',
                'note' => 'Separate X app until Ads API access is approved for the main one.',
            ];
        }

        return $steps;
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return $step === self::ADS
            ? redirect()->route('admin.ads.redirect', 'x')
            : redirect()->route('admin.messaging.auth.x.redirect');
    }

    public function validate(SocialConnection $connection): SocialConnection
    {
        return $connection->step === self::ADS ? $this->validateAds($connection) : parent::validate($connection);
    }

    public function disconnect(SocialConnection $connection): void
    {
        try {
            if ($connection->step === self::ADS && $connection->access_token) {
                // OAuth 1.0a: POST oauth/invalidate_token (X API v1.1).
                $url = 'https://api.x.com/1.1/oauth/invalidate_token';
                Http::withHeaders(['Authorization' => $this->adsHeader('POST', $url, $connection)])->post($url);
            } elseif ($token = $this->latestAsset($connection)?->refresh_token ?? $connection->refresh_token) {
                // OAuth 2.0: POST /2/oauth2/revoke (confidential client, Basic auth).
                Http::asForm()
                    ->withBasicAuth((string) adminSetting('posts.x.client_id'), (string) adminSetting('posts.x.client_secret'))
                    ->post('https://api.x.com/2/oauth2/revoke', ['token' => $token, 'token_type_hint' => 'refresh_token', 'client_id' => adminSetting('posts.x.client_id')]);
            }
        } catch (\Throwable $e) {
            Log::warning('X token revoke failed; disconnecting locally.', ['connection_id' => $connection->id, 'error' => $e->getMessage()]);
        }

        $this->disconnectLocally($connection);
    }

    /** OAuth 2.0 step: GET /2/users/me with the account's current token. */
    protected function probe(SocialConnection $connection, SocialAccount $asset): ?array
    {
        $response = Http::withToken($asset->access_token)->get('https://api.x.com/2/users/me');

        return $this->problemFromStatus($response->status(), $response->json('detail') ?? $response->json('title'));
    }

    private function validateAds(SocialConnection $connection): SocialConnection
    {
        if (! $connection->access_token || ! $connection->token_secret) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'No X Ads token stored - reconnect.');
        }

        $url = rtrim(adminSetting('ads.x.base_url') ?: 'https://ads-api.x.com/12/', '/') . '/accounts';
        $response = Http::withHeaders(['Authorization' => $this->adsHeader('GET', $url, $connection)])->get($url);

        if ($response->status() === 401) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'X no longer accepts this Ads token.');
        }

        if (! $response->successful()) {
            return $this->mark($connection, SocialConnection::ERROR, $response->json('errors.0.message') ?? 'HTTP ' . $response->status());
        }

        // X's approval status per Ads account (ACCEPTED / REJECTED / ...),
        // so an account X approves later shows as such without reconnecting.
        $statuses = collect($response->json('data') ?? [])->keyBy('id');
        foreach ($connection->assets()->get() as $asset) {
            if ($acct = $statuses->get($asset->platform_account_id)) {
                $asset->syncAdDetails(['account_status' => ($acct['deleted'] ?? false) ? 'deleted' : ($acct['approval_status'] ?? null)]);
            }
        }

        return $this->markHealthy($connection, null);
    }

    private function adsHeader(string $method, string $url, SocialConnection $connection): string
    {
        [$key, $secret] = XOAuth1::consumer();

        return XOAuth1::header($method, $url, [], $key, $secret, $connection->access_token, $connection->token_secret);
    }

    private function latestAsset(SocialConnection $connection): ?SocialAccount
    {
        return $connection->assets()->latest('updated_at')->first();
    }
}
