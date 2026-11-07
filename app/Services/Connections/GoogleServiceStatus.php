<?php

namespace App\Services\Connections;

use App\Models\GoogleProperty;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\GoogleSuite\PageSpeedService;
use App\Models\GoogleSuiteSetting;
use App\Support\Connections\GoogleClient;
use App\Support\Google\GoogleServices;
use Illuminate\Support\Collection;

/**
 * One line per Google service for the Connection Hub: is it connected, with
 * which Google account(s), and what the customer can do next. Statuses come
 * from what Google actually granted (granted_scopes) and the consent's
 * health - never from the redirect.
 *
 * Status: connected | not_connected | expired | permission_required | error | unavailable
 */
class GoogleServiceStatus
{
    /** Worst-first order for summarising several Google accounts on one service (after "connected"). */
    private const ATTENTION_ORDER = ['permission_required', 'expired', 'error'];

    public static function forUser(int $userId): array
    {
        $connections = SocialConnection::where(['user_id' => $userId, 'platform' => 'google'])
            ->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', SocialConnection::DISCONNECTED_BY_USER))
            ->with('assets')
            ->orderBy('id')
            ->get();
        $configured = (bool) (GoogleClient::credentials()['client_id'] ?? null);

        return collect(GoogleServices::all())
            ->map(fn (array $service) => self::service($userId, $service, $connections, $configured))
            ->values()
            ->all();
    }

    private static function service(int $userId, array $service, Collection $connections, bool $configured): array
    {
        $base = [
            'key' => $service['key'],
            'icon' => $service['icon'] ?? 'bxl-google',
            'brand' => $service['brand'] ?? 'google',
            'auth' => $service['auth'],
            'manage_url' => self::manageUrl($service),
            'connect_url' => null,
            'accounts' => [],
            'shares_with' => [],
            'note' => null,
        ];

        return match ($service['auth']) {
            'api_key' => self::apiKeyService($userId, $base),
            'oauth' => self::oauthService($userId, $service, $connections, $configured, $base),
            default => $base + ['status' => 'unavailable', 'reason' => 'planned'],
        };
    }

    private static function oauthService(int $userId, array $service, Collection $connections, bool $configured, array $base): array
    {
        if (! GoogleServices::enabled($service)) {
            return array_merge($base, ['status' => 'unavailable', 'reason' => 'approval']);
        }
        if (! $configured) {
            return array_merge($base, ['status' => 'unavailable', 'reason' => 'not_configured']);
        }

        $accounts = $connections
            ->filter(fn (SocialConnection $c) => $c->step === $service['step']
                ? GoogleServices::granted($service, $c->granted_scopes)
                // An earlier app for this service: its consent was for this service only.
                : in_array($c->step, $service['legacy_steps'] ?? [], true))
            ->map(fn (SocialConnection $c) => [
                'connection_id' => $c->id,
                'email' => $c->provider_account_label,
                'status' => self::connectionStatus($c),
                'assets' => isset($service['asset_kind']) ? self::assetCount($c, $service['asset_kind']) : null,
            ])
            ->values();

        $statuses = $accounts->pluck('status');
        $status = $statuses->contains('connected')
            ? 'connected'
            : (collect(self::ATTENTION_ORDER)->first(fn ($s) => $statuses->contains($s)) ?? 'not_connected');

        // Other services the same Google consent covers: disconnecting it removes them too.
        $sharesWith = array_keys(array_diff_key(GoogleServices::forStep($service['step']), [$service['key'] => true]));

        return array_merge($base, [
            'status' => $status,
            'connect_url' => route('admin.connections.google.connect', ['service' => $service['key']]),
            'accounts' => $accounts->all(),
            'shares_with' => $sharesWith,
            'note' => $status === 'connected' ? self::note($userId, $service, $accounts) : null,
        ]);
    }

    private static function apiKeyService(int $userId, array $base): array
    {
        $own = GoogleSuiteSetting::pageSpeedKey($userId) !== null;
        $platform = PageSpeedService::apiKey() !== null;

        return array_merge($base, [
            // No Google sign-in: "connected" means tests can run (a key exists).
            'status' => $own || $platform ? 'connected' : 'not_connected',
            'reason' => $own ? 'own_key' : ($platform ? 'platform_key' : 'needs_key'),
        ]);
    }

    /** Hub status of one Google consent. */
    public static function connectionStatus(SocialConnection $connection): string
    {
        return match ($connection->status) {
            SocialConnection::ACTIVE, SocialConnection::EXPIRING => 'connected',
            SocialConnection::EXPIRED => 'expired',
            SocialConnection::NEEDS_REAUTH, SocialConnection::REVOKED => 'permission_required',
            default => 'error',
        };
    }

    private static function assetCount(SocialConnection $connection, string $kind): int
    {
        return $connection->assets->filter(fn (SocialAccount $a) => match ($kind) {
            'youtube' => $a->platform === 'youtube',
            'business_profile' => $a->platform === 'google' && $a->account_type === 'location',
            'google_ads' => $a->platform === 'google' && $a->account_type !== 'location',
            default => false,
        })->count();
    }

    /** What still needs doing on a connected service (no locations, no property picked). */
    private static function note(int $userId, array $service, Collection $accounts): ?string
    {
        if (isset($service['asset_kind']) && $accounts->sum('assets') === 0) {
            return 'no_assets';
        }

        $propertyService = ['analytics' => GoogleProperty::GA4, 'search_console' => GoogleProperty::SEARCH_CONSOLE][$service['key']] ?? null;
        if ($propertyService && ! GoogleProperty::ownedBy($userId)->where('service', $propertyService)->where('is_selected', true)->exists()) {
            return 'no_property';
        }

        return null;
    }

    private static function manageUrl(array $service): ?string
    {
        $route = $service['manage_route'] ?? null;

        return $route ? route($route[0], $route[1]) : null;
    }
}
