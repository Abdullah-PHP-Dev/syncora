<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Models\GoogleSyncEvent;
use App\Models\SocialConnection;
use App\Support\Google\GoogleApi;
use App\Support\Google\GoogleApiException;
use App\Support\Google\GoogleSuiteScopes;
use Illuminate\Support\Facades\DB;

/**
 * Finds the GA4 properties and Search Console sites the linked Google
 * account can read, and mirrors them into google_properties. Only what
 * Google returns for this token is ever selectable.
 */
class GoogleSuiteDiscovery
{
    private const ADMIN_API = 'https://analyticsadmin.googleapis.com/v1beta/';
    private const SC_API = 'https://www.googleapis.com/webmasters/v3/';

    /** Properties whose web streams are looked up (one call each). */
    private const STREAM_LOOKUPS = 25;

    /** @return array<string, array{count?: int, error?: string}> */
    public function discover(SocialConnection $connection): array
    {
        $api = new GoogleApi($connection);
        $result = [];

        foreach ([GoogleProperty::GA4 => fn () => $this->ga4($api), GoogleProperty::SEARCH_CONSOLE => fn () => $this->searchConsole($api)] as $service => $fetch) {
            if (! GoogleSuiteScopes::grants($connection->granted_scopes, $service)) {
                $result[$service] = ['error' => GoogleApiException::MISSING_SCOPE];
                continue;
            }

            try {
                $rows = $fetch();
                $this->mirror($connection, $service, $rows);
                $result[$service] = ['count' => count($rows)];
            } catch (GoogleApiException $e) {
                $result[$service] = ['error' => $e->errorCode];
                GoogleSyncEvent::log($connection->user_id, $service, 'discovery_failed', 'error', $e->errorCode);
            }
        }

        return $result;
    }

    /** GA4: accountSummaries lists every account and property the user can read. */
    private function ga4(GoogleApi $api): array
    {
        $rows = [];
        $pageToken = null;

        do {
            $page = $api->get(self::ADMIN_API . 'accountSummaries', array_filter(['pageSize' => 200, 'pageToken' => $pageToken]));

            foreach ($page['accountSummaries'] ?? [] as $account) {
                foreach ($account['propertySummaries'] ?? [] as $property) {
                    $rows[] = [
                        'external_id' => $property['property'],
                        'display_name' => $property['displayName'] ?? $property['property'],
                        'account_name' => $account['displayName'] ?? null,
                        'meta' => [
                            'account' => $account['account'] ?? null,
                            'property_type' => $property['propertyType'] ?? null,
                        ],
                    ];
                }
            }

            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken && count($rows) < 1000);

        // Web stream URL + Measurement ID help the customer recognise a
        // property. They are labels only - reports always go through OAuth.
        foreach (array_slice(array_keys($rows), 0, self::STREAM_LOOKUPS) as $i) {
            try {
                $streams = $api->get(self::ADMIN_API . $rows[$i]['external_id'] . '/dataStreams', ['pageSize' => 20])['dataStreams'] ?? [];
            } catch (GoogleApiException $e) {
                continue;
            }

            $web = collect($streams)->firstWhere('type', 'WEB_DATA_STREAM');
            $rows[$i]['meta']['website_url'] = $web['webStreamData']['defaultUri'] ?? null;
            $rows[$i]['meta']['measurement_id'] = $web['webStreamData']['measurementId'] ?? null;
            $rows[$i]['meta']['stream_name'] = $web['displayName'] ?? null;
            $rows[$i]['meta']['streams'] = count($streams);
        }

        return $rows;
    }

    /** Search Console: verified sites only - unverified entries return no data. */
    private function searchConsole(GoogleApi $api): array
    {
        return collect($api->get(self::SC_API . 'sites')['siteEntry'] ?? [])
            ->reject(fn ($site) => ($site['permissionLevel'] ?? '') === 'siteUnverifiedUser')
            ->map(fn ($site) => [
                'external_id' => $site['siteUrl'],
                'display_name' => str_starts_with($site['siteUrl'], 'sc-domain:') ? substr($site['siteUrl'], 10) : $site['siteUrl'],
                'account_name' => null,
                'meta' => [
                    'property_type' => str_starts_with($site['siteUrl'], 'sc-domain:') ? 'domain' : 'url_prefix',
                    'permission_level' => $site['permissionLevel'] ?? null,
                ],
            ])
            ->values()->all();
    }

    private function mirror(SocialConnection $connection, string $service, array $rows): void
    {
        DB::transaction(function () use ($connection, $service, $rows) {
            $seen = [];

            foreach ($rows as $row) {
                $property = GoogleProperty::firstOrNew(['user_id' => $connection->user_id, 'service' => $service, 'external_id' => $row['external_id']]);
                $property->fill([
                    'social_connection_id' => $connection->id,
                    'display_name' => mb_substr((string) $row['display_name'], 0, 250),
                    'account_name' => $row['account_name'] ? mb_substr($row['account_name'], 0, 250) : null,
                    // Keep details learned on selection (time zone, currency).
                    'meta' => array_merge($property->meta ?? [], array_filter($row['meta'], fn ($v) => $v !== null)),
                ])->save();
                $seen[] = $property->id;
            }

            // Access removed in Google: drop it here too (a selected one included).
            GoogleProperty::ownedBy($connection->user_id)->where('service', $service)->whereNotIn('id', $seen)->delete();
        });
    }
}
