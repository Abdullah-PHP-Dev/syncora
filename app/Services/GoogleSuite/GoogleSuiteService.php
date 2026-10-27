<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Models\GoogleSyncEvent;
use App\Models\SocialConnection;
use App\Support\Google\GoogleApi;
use App\Support\Google\GoogleApiException;
use App\Support\Google\GoogleSuiteScopes;
use App\Support\Google\ReportRange;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for everything the Google Suite pages read. Every lookup is
 * scoped to the workspace owner's user id (UseSellerWorkspace sets it), so
 * a property id from another workspace never resolves.
 */
class GoogleSuiteService
{
    public function __construct(
        private Ga4Service $ga4,
        private SearchConsoleService $searchConsole,
        private GoogleSuiteDiscovery $discovery,
    ) {
    }

    public function connection(int $userId): ?SocialConnection
    {
        return GoogleSuiteAuth::connectionFor($userId);
    }

    public function api(int $userId): GoogleApi
    {
        $connection = $this->connection($userId);

        if (! $connection) {
            throw new GoogleApiException(GoogleApiException::NOT_CONNECTED);
        }

        if (! $connection->isUsable()) {
            throw new GoogleApiException(GoogleApiException::AUTH_EXPIRED);
        }

        return new GoogleApi($connection);
    }

    public function selected(int $userId, string $service): ?GoogleProperty
    {
        return GoogleProperty::ownedBy($userId)->selected($service)->first();
    }

    /** @return array{data?: array, error?: array, fetched_at?: string} */
    public function ga4Dashboard(int $userId, ReportRange $range, bool $fresh = false): array
    {
        return $this->run($userId, GoogleProperty::GA4, 'ga4:' . $range->cacheKey(), $fresh,
            fn (GoogleApi $api, GoogleProperty $p) => $this->ga4->dashboard($api, $p, $range));
    }

    public function ga4Realtime(int $userId): array
    {
        return $this->run($userId, GoogleProperty::GA4, 'ga4:realtime:' . now()->format('YmdHi'), false,
            fn (GoogleApi $api, GoogleProperty $p) => $this->ga4->realtime($api, $p), record: false);
    }

    public function searchConsoleDashboard(int $userId, ReportRange $range, array $filters, bool $fresh = false): array
    {
        return $this->run($userId, GoogleProperty::SEARCH_CONSOLE, 'sc:' . $range->cacheKey() . ':' . json_encode($filters), $fresh,
            fn (GoogleApi $api, GoogleProperty $p) => $this->searchConsole->dashboard($api, $p, $range, $filters));
    }

    public function inspectUrl(int $userId, string $url, string $locale): array
    {
        return $this->run($userId, GoogleProperty::SEARCH_CONSOLE, 'sc:inspect:' . $url, false,
            fn (GoogleApi $api, GoogleProperty $p) => $this->searchConsole->inspect($api, $p, $url, $locale), record: false);
    }

    public function report(int $userId, string $service, string $type, ReportRange $range, int $limit, array $filters = []): array
    {
        return $this->run($userId, $service, "report:{$service}:{$type}:{$limit}:" . $range->cacheKey() . ':' . json_encode($filters), false,
            fn (GoogleApi $api, GoogleProperty $p) => $service === GoogleProperty::GA4
                ? $this->ga4->report($api, $p, $type, $range, $limit)
                : $this->searchConsole->report($api, $p, $type, $range, $limit, $filters), record: false);
    }

    /** Both services side by side; each fails on its own. */
    public function overview(int $userId, ReportRange $range, bool $fresh = false): array
    {
        return [
            'ga4' => $this->ga4Dashboard($userId, $range, $fresh),
            'search_console' => $this->searchConsoleDashboard($userId, $range, ['type' => 'web'], $fresh),
        ];
    }

    public function discover(int $userId): array
    {
        $connection = $this->connection($userId);

        if (! $connection || ! $connection->isUsable()) {
            throw new GoogleApiException($connection ? GoogleApiException::AUTH_EXPIRED : GoogleApiException::NOT_CONNECTED);
        }

        return $this->discovery->discover($connection);
    }

    /** Make one discovered property the workspace's source for a service (null clears it). */
    public function select(int $userId, string $service, ?int $propertyId): ?GoogleProperty
    {
        $property = $propertyId === null ? null : GoogleProperty::ownedBy($userId)->where('service', $service)->findOrFail($propertyId);

        DB::transaction(function () use ($userId, $service, $property) {
            GoogleProperty::ownedBy($userId)->where('service', $service)->update(['is_selected' => false]);
            $property?->forceFill(['is_selected' => true])->save();
        });

        if ($property && $service === GoogleProperty::GA4) {
            try {
                $details = $this->ga4->propertyDetails($this->api($userId), $property);
                $property->forceFill(['meta' => array_merge($property->meta ?? [], $details)])->save();
            } catch (GoogleApiException $e) {
                // Labels only; the dashboards work without them.
            }
        }

        GoogleReportCache::flush($userId);
        GoogleSyncEvent::log($userId, $service, $property ? 'property_selected' : 'property_cleared', 'ok', $property?->display_name);

        return $property;
    }

    /** Manual or scheduled refresh: drop cached reports, then rebuild the default range. */
    public function sync(int $userId, bool $manual = false): array
    {
        GoogleReportCache::flush($userId);
        $range = ReportRange::make(GoogleSuiteSetting::for($userId)['default_range']);
        $results = [];

        foreach (GoogleProperty::SERVICES as $service) {
            if (! $this->selected($userId, $service)) {
                continue;
            }

            $outcome = $service === GoogleProperty::GA4
                ? $this->ga4Dashboard($userId, $range, true)
                : $this->searchConsoleDashboard($userId, $range, ['type' => 'web'], true);
            $results[$service] = isset($outcome['error']) ? $outcome['error']['code'] : 'ok';

            if ($manual && ! isset($outcome['error'])) {
                GoogleSyncEvent::log($userId, $service, 'synced');
            }
        }

        return $results;
    }

    /**
     * Calls Google for the workspace's selected property, caching the
     * result. Failures become {error} (never an exception to the page) and
     * are recorded on the property for the health screen.
     */
    private function run(int $userId, string $service, string $key, bool $fresh, callable $callback, bool $record = true): array
    {
        $property = $this->selected($userId, $service);

        try {
            $connection = $this->connection($userId);

            if ($connection && ! GoogleSuiteScopes::grants($connection->granted_scopes, $service)) {
                throw new GoogleApiException(GoogleApiException::MISSING_SCOPE);
            }

            $api = $this->api($userId);

            if (! $property) {
                throw new GoogleApiException(GoogleApiException::NO_PROPERTY);
            }

            $result = GoogleReportCache::remember($userId, $service . ':' . $property->id . ':' . $key, $fresh, function () use ($callback, $api, $property) {
                return ['data' => $callback($api, $property), 'fetched_at' => now()->toIso8601String()];
            });

            if ($record && ($fresh || $property->last_error_code || ! $property->last_synced_at)) {
                $recovered = (bool) $property->last_error_code;
                $property->recordSuccess();

                if ($recovered) {
                    GoogleSyncEvent::log($userId, $service, 'recovered');
                }
            }

            return $result;
        } catch (GoogleApiException $e) {
            if ($record && $property && ! in_array($e->errorCode, [GoogleApiException::NO_PROPERTY, GoogleApiException::NOT_CONNECTED], true)) {
                // One log line per new problem, not one per page view.
                if ($property->last_error_code !== $e->errorCode) {
                    GoogleSyncEvent::log($userId, $service, 'failed', 'error', $e->errorCode);
                }
                $property->recordFailure($e->errorCode, $e->customerMessage());
            }

            return ['error' => $e->toArray()];
        }
    }
}
