<?php

namespace App\Support\Connections;

use Illuminate\Support\Str;

/**
 * OAuth 1.0a request signing (RFC 5849, HMAC-SHA1) for X - every Ads API
 * call needs a freshly computed signature; there is no reusable Bearer
 * token. Shared by XAdService and the Hub's X driver.
 */
class XOAuth1
{
    /** `Authorization: OAuth ...` header value for one request. */
    public static function header(
        string $method,
        string $url,
        array $params,
        string $consumerKey,
        string $consumerSecret,
        ?string $token,
        ?string $tokenSecret,
    ): string {
        $oauth = array_filter([
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $token,
            'oauth_version' => '1.0',
        ], fn ($v) => $v !== null && $v !== '');

        $oauth['oauth_signature'] = self::signature($method, $url, array_merge($params, $oauth), $consumerSecret, (string) $tokenSecret);

        return 'OAuth ' . collect($oauth)->map(fn ($v, $k) => rawurlencode($k) . '="' . rawurlencode($v) . '"')->implode(', ');
    }

    /** RFC 5849 §3.4 signature over every request + oauth_* parameter. */
    public static function signature(string $method, string $url, array $params, string $consumerSecret, string $tokenSecret): string
    {
        $encoded = [];

        foreach ($params as $key => $value) {
            $encoded[rawurlencode($key)] = rawurlencode((string) $value);
        }

        ksort($encoded);

        $paramString = collect($encoded)->map(fn ($v, $k) => "$k=$v")->implode('&');
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }
}
