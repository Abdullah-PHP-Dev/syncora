<?php

namespace App\Services\Connections\Drivers;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\SocialAuth\SocialAuthService;
use App\Support\Connections\ConnectionFlags;
use App\Support\Connections\GrantedScopes;
use App\Support\Connections\HubReturn;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * LinkedIn (docs/connection-hub-design.md §3, audit §3): Company Pages on
 * posts.linkedin and Ads on ads.linkedin - two apps until Community
 * Management Standard tier is added to the Ads app (flag
 * linkedin.single_app; folding them is a later step).
 *
 * Validation uses LinkedIn's token introspection (active / expired /
 * revoked + granted scopes). LinkedIn documents no revoke endpoint, so a
 * disconnect is local and the Hub tells the user to remove the app in
 * their LinkedIn settings too.
 */
class LinkedInDriver extends BaseDriver
{
    public const PAGES = 'linkedin.pages';
    public const ADS = 'linkedin.ads';

    private const INTROSPECT_URL = 'https://www.linkedin.com/oauth/v2/introspectToken';

    public function __construct(private SocialAuthService $socialAuth)
    {
    }

    public function platform(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
    }

    public function presentation(): array
    {
        return [
            'subtitle' => 'Company Pages · Campaign Manager Ads',
            'icons' => [['icon' => 'bxl-linkedin', 'brand' => 'linkedin']],
            'connect_label' => 'Connect with LinkedIn',
            'empty_title' => 'Connect your LinkedIn Pages and Ads',
            'empty_text' => 'Choose the Company Pages you post to and the Campaign Manager ad accounts you advertise with. To fully remove access later, also remove SocialEaz in your LinkedIn settings.',
            'benefits' => ['posting', 'ads', 'insights'],
            'asset_groups' => [
                'linkedin_page' => ['label' => 'Company Pages', 'icon' => 'bxl-linkedin', 'brand' => 'linkedin', 'capabilities' => ['posting', 'insights']],
                'linkedin_ads' => ['label' => 'Ad accounts', 'icon' => 'bx-bullseye', 'brand' => 'linkedin', 'capabilities' => ['ads']],
            ],
            'step_icons' => [
                self::PAGES => ['icon' => 'bxl-linkedin', 'brand' => 'linkedin'],
                self::ADS => ['icon' => 'bx-bullseye', 'brand' => 'linkedin'],
            ],
        ];
    }

    public function assetKind(SocialAccount $asset): string
    {
        return $asset->has_ads_permission && ! $asset->has_posting_permission ? 'linkedin_ads' : 'linkedin_page';
    }

    public function stepFor(string $capability): string
    {
        return $capability === 'ads' ? self::ADS : self::PAGES;
    }

    public function steps(): array
    {
        return [
            [
                'key' => self::PAGES,
                'label' => 'LinkedIn Pages',
                'description' => 'Post to the Company Pages you administer.',
                'primary' => true,
                'available' => (bool) adminSetting('posts.linkedin.client_id'),
            ],
            [
                'key' => self::ADS,
                'label' => 'LinkedIn Ads',
                'description' => 'Campaign Manager ad accounts where you have a role.',
                'primary' => false,
                'available' => (bool) adminSetting('ads.linkedin.client_id'),
                'note' => ConnectionFlags::on('linkedin.single_app') ? null : 'Separate LinkedIn app until Community Management is added to the Ads app.',
            ],
        ];
    }

    public function connect(string $step): Response
    {
        HubReturn::mark();

        return $step === self::ADS
            ? redirect()->route('admin.ads.redirect', 'linkedin')
            : $this->socialAuth->redirect('linkedin');
    }

    public function validate(SocialConnection $connection): SocialConnection
    {
        // The module services refresh the account's access token (LinkedIn
        // refresh tokens keep a fixed TTL, so nothing rotates) - check the newest.
        $asset = $connection->assets()->latest('updated_at')->first();
        $token = $asset?->access_token ?? $connection->access_token;

        if (! $token) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'No LinkedIn token stored - reconnect.');
        }

        if ($asset) {
            $connection->fill(array_filter([
                'access_token' => $asset->access_token,
                'refresh_token' => $asset->refresh_token,
                'expires_at' => $asset->expires_at,
            ]));
        }

        $app = $connection->provider_app ?: ($connection->step === self::ADS ? 'ads.linkedin' : 'posts.linkedin');
        $response = Http::asForm()->post(self::INTROSPECT_URL, [
            'client_id' => adminSetting("{$app}.client_id"),
            'client_secret' => adminSetting("{$app}.client_secret"),
            'token' => $token,
        ]);

        if (! $response->successful()) {
            return $this->mark($connection, SocialConnection::ERROR, 'LinkedIn token check failed (HTTP ' . $response->status() . ') - check the app credentials.');
        }

        $status = $response->json('status') ?? ($response->json('active') ? 'active' : 'revoked');

        if ($status === 'revoked' || ($status !== 'expired' && ! $response->json('active'))) {
            return $this->mark($connection, SocialConnection::NEEDS_REAUTH, 'LinkedIn revoked this access.');
        }

        // Expired access token with a refresh token is fine (renewed on use);
        // without one, timeStatus() reports needs_reauth.
        return $this->markHealthy($connection, GrantedScopes::fromTokenResponse($response->json()));
    }

    public function disconnect(SocialConnection $connection): void
    {
        $this->disconnectLocally($connection);
    }
}
