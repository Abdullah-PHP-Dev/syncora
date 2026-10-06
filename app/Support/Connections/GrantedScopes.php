<?php

namespace App\Support\Connections;

/**
 * Normalises what each provider says it actually GRANTED on an OAuth
 * callback into a sorted list for social_accounts.scopes
 * (docs/connection-hub-design.md §1b). Requested scopes are never stored -
 * users can untick permissions, and apps can lack approval for some.
 *
 * Every method returns null when the provider didn't report scopes, and
 * attributes() then omits the key, so a re-connect never wipes scopes that
 * were recorded earlier (the status job's validation pass fills gaps).
 */
class GrantedScopes
{
    /** OAuth 1.0a (X) has no scopes; capabilities come from app approval. */
    public const OAUTH1 = ['oauth1'];

    /**
     * Token-endpoint responses: Google, LinkedIn, TikTok (Login Kit and
     * Business), Snapchat, X OAuth 2.0 and Pinterest return `scope`;
     * Instagram Login returns `permissions`. Space- or comma-separated
     * strings and arrays are all accepted.
     */
    public static function fromTokenResponse(?array $token): ?array
    {
        if (! $token) {
            return null;
        }

        foreach (['scope', 'scopes', 'permissions'] as $key) {
            if (array_key_exists($key, $token) && $token[$key] !== null && $token[$key] !== '') {
                return self::normalise($token[$key]);
            }
        }

        // Instagram Login wraps the short-lived token in data[0].
        if (isset($token['data'][0]) && is_array($token['data'][0])) {
            return self::fromTokenResponse($token['data'][0]);
        }

        return null;
    }

    /**
     * Meta: the body of GET /me/permissions -
     * {"data":[{"permission":"pages_show_list","status":"granted"}, ...]}.
     * Declined/expired permissions are excluded.
     */
    public static function fromMetaPermissions(?array $body): ?array
    {
        $rows = $body['data'] ?? null;

        if (! is_array($rows)) {
            return null;
        }

        $granted = array_column(
            array_filter($rows, fn ($row) => ($row['status'] ?? null) === 'granted'),
            'permission'
        );

        return $granted ? self::normalise($granted) : null;
    }

    /** ['scopes' => [...]] or [] - spread into an updateOrCreate() payload. */
    public static function attributes(?array $scopes): array
    {
        return $scopes === null ? [] : ['scopes' => $scopes];
    }

    private static function normalise(string|array $value): ?array
    {
        $list = is_array($value) ? $value : preg_split('/[\s,]+/', $value);

        $list = array_values(array_unique(array_filter(array_map(
            fn ($scope) => trim((string) $scope),
            $list
        ), fn ($scope) => $scope !== '')));

        sort($list);

        return $list ?: null;
    }
}
