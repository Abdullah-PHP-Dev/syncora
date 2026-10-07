<?php

namespace App\Support\Connections;

use App\Models\SocialAccount;

/**
 * Which Google OAuth client to use (docs/connection-hub-design.md §5).
 *
 * New consents use the client chosen by the `google.oauth_client` flag
 * (default `posts` - project 868193692422). Refreshing an existing token
 * must use the client that issued it (Google rejects a refresh token sent
 * with another client's credentials), so accounts carry it through their
 * connection's provider_app; older unlinked accounts fall back to the
 * client their module always used.
 */
class GoogleClient
{
    /** adminSetting prefix for new consents: posts.google | ads.google. */
    public static function current(): string
    {
        return ConnectionFlags::get('google.oauth_client') === 'ads' ? 'ads.google' : 'posts.google';
    }

    /** The client that issued this account's token. */
    public static function forAccount(SocialAccount $account, string $legacyDefault): string
    {
        $app = $account->connection?->provider_app;

        return in_array($app, ['posts.google', 'ads.google'], true) ? $app : $legacyDefault;
    }

    /** @return array{client_id: ?string, client_secret: ?string} */
    public static function credentials(?string $prefix = null): array
    {
        $prefix ??= self::current();

        return [
            'client_id' => adminSetting("{$prefix}.client_id"),
            'client_secret' => adminSetting("{$prefix}.client_secret"),
        ];
    }

    /**
     * Google Ads request headers. The developer token was sunset on
     * 2026-09-09 ("optional and ignored by the API servers" - Google Ads
     * API docs, Developer token), so it's sent only when one is still set.
     */
    public static function adsHeaders(string $accessToken): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        if ($developerToken = adminSetting('ads.google.developer_token')) {
            $headers['developer-token'] = $developerToken;
        }

        if ($loginCustomerId = adminSetting('ads.google.login_customer_id')) {
            $headers['login-customer-id'] = str_replace('-', '', $loginCustomerId);
        }

        return $headers;
    }
}
