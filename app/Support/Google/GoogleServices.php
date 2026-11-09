<?php

namespace App\Support\Google;

use App\Support\Connections\ConnectionFlags;

/**
 * The Google service registry (config/google_services.php): which services
 * exist, how each is authorised, and the scopes each consent requests.
 */
class GoogleServices
{
    /** Session key: the services the next Google consent is for (set by the Hub, read once). */
    public const REQUEST_KEY = 'google_services_request';

    /** @return array<string, array> every service, with its key */
    public static function all(): array
    {
        $services = [];
        foreach (config('google_services.services', []) as $key => $service) {
            $services[$key] = $service + ['key' => $key];
        }

        return $services;
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Switched on: no flag, or its connections flag is on. */
    public static function enabled(array $service): bool
    {
        return empty($service['flag']) || ConnectionFlags::on($service['flag']);
    }

    /** OAuth services of one consent step that are switched on. */
    public static function forStep(string $step): array
    {
        return array_filter(self::all(), fn ($s) => ($s['auth'] ?? null) === 'oauth' && ($s['step'] ?? null) === $step && self::enabled($s));
    }

    /**
     * Scopes for one consent request.
     *
     * @param  array|null  $services  service keys picked in the Hub; null = the step's full set (previous behaviour)
     */
    public static function scopesFor(string $step, ?array $services = null): array
    {
        $available = self::forStep($step);
        $picked = $services === null ? $available : array_intersect_key($available, array_flip($services));

        // Nothing valid picked for this consent: fall back to its full set rather than an empty request.
        if ($picked === []) {
            $picked = $available;
            $services = null;
        }

        $scopes = array_merge(...array_values(array_map(fn ($s) => $s['scopes'], $picked)));
        if ($services === null) {
            // Step keys contain dots (google.oauth): index the array, not config()'s dot path.
            $scopes = array_merge($scopes, config('google_services.default_extra_scopes', [])[$step] ?? []);
        }

        return array_values(array_unique(array_merge(config('google_services.identity_scopes', []), $scopes)));
    }

    /** Remember which services the next consent is for. */
    public static function requestFor(array $services): void
    {
        session([self::REQUEST_KEY => array_values($services)]);
    }

    /** The services the current consent was started for (read once), or null for the full set. */
    public static function pullRequest(): ?array
    {
        $services = session()->pull(self::REQUEST_KEY);

        return is_array($services) && $services !== [] ? $services : null;
    }

    /** Whether every scope a service needs was granted. */
    public static function granted(array $service, ?array $grantedScopes): bool
    {
        return ! array_diff($service['scopes'] ?? [], $grantedScopes ?? []);
    }

    /**
     * ID token claims (sub, email). The token comes straight from Google's
     * token endpoint over TLS in the code exchange, so its signature needn't
     * be checked (OpenID Connect: "obtained directly from Google").
     */
    public static function idTokenClaims(?string $idToken): array
    {
        $parts = $idToken ? explode('.', $idToken) : [];
        if (count($parts) !== 3) {
            return [];
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) ? array_intersect_key($claims, array_flip(['sub', 'email'])) : [];
    }
}
