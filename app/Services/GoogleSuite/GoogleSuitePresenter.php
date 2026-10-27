<?php

namespace App\Services\GoogleSuite;

use App\Models\GoogleProperty;
use App\Models\GoogleSuiteSetting;
use App\Models\GoogleSyncEvent;
use App\Models\SocialConnection;
use App\Services\PackageAccessService;
use App\Support\AdAccountSelection;
use App\Support\Google\GoogleSuiteScopes;
use Illuminate\Http\Request;

/**
 * What every Google Suite page boots with. Tokens, secrets and raw
 * provider errors never reach the browser - only statuses, codes and
 * labels the workspace owns.
 */
class GoogleSuitePresenter
{
    private const SYNC_HOURS = ['hourly' => 1, 'every_6_hours' => 6, 'daily' => 24];

    public function boot(Request $request, string $screen, array $extra = []): array
    {
        $userId = (int) $request->user()->id;
        $connection = GoogleSuiteAuth::connectionFor($userId);
        $preferences = GoogleSuiteSetting::for($userId);
        $properties = GoogleProperty::ownedBy($userId)->orderBy('account_name')->orderBy('display_name')->get();

        return array_merge([
            'screen' => $screen,
            'locale' => app()->getLocale(),
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            // Connecting, reconnecting and disconnecting stay with the owner (UseSellerWorkspace).
            'can_manage' => ! $request->attributes->has('workspace_member'),
            'configured' => GoogleSuiteAuth::configured(),
            'account' => $this->account($connection, $preferences),
            'properties' => [
                GoogleProperty::GA4 => $properties->where('service', GoogleProperty::GA4)->values()->map(fn ($p) => $this->property($p))->all(),
                GoogleProperty::SEARCH_CONSOLE => $properties->where('service', GoogleProperty::SEARCH_CONSOLE)->values()->map(fn ($p) => $this->property($p))->all(),
            ],
            'preferences' => collect($preferences)->except('account')->all(),
            'events' => GoogleSyncEvent::where('user_id', $userId)->latest('id')->limit(12)->get()->map(fn ($e) => [
                'id' => $e->id, 'service' => $e->service, 'event' => $e->event, 'status' => $e->status,
                'message' => $e->message, 'at' => $e->created_at?->toIso8601String(),
            ])->all(),
            'next_sync_at' => $this->nextSync($properties, $preferences['sync_frequency']),
            'website_url' => $this->websiteUrl($properties, $preferences),
            // CSV export is its own package module (package_access.routes.data_export).
            'can_export' => app(PackageAccessService::class)->allows($request->user(), 'data_export'),
            'ads_account' => $this->adsAccount($userId),
            'urls' => $this->urls(),
            'messages' => __('google_suite'),
        ], $extra);
    }

    public function account(?SocialConnection $connection, array $preferences): array
    {
        if (! $connection) {
            return ['connected' => false];
        }

        // Google reports the "email" scope back as userinfo.email.
        $granted = array_map(fn ($s) => $s === 'https://www.googleapis.com/auth/userinfo.email' ? 'email' : $s, $connection->granted_scopes ?? []);

        return [
            'connected' => true,
            'id' => $connection->id,
            'status' => $connection->status,
            'usable' => $connection->isUsable(),
            'email' => $preferences['account']['email'] ?? null,
            'connected_at' => $preferences['account']['connected_at'] ?? $connection->created_at?->toIso8601String(),
            'scopes' => [
                GoogleProperty::GA4 => GoogleSuiteScopes::grants($granted, GoogleProperty::GA4),
                GoogleProperty::SEARCH_CONSOLE => GoogleSuiteScopes::grants($granted, GoogleProperty::SEARCH_CONSOLE),
            ],
            'scope_count' => count(array_intersect(GoogleSuiteScopes::requested(), $granted)),
            'scope_total' => count(GoogleSuiteScopes::requested()),
            'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
            'last_refreshed_at' => $connection->last_refreshed_at?->toIso8601String(),
        ];
    }

    public function property(GoogleProperty $property): array
    {
        $meta = $property->meta ?? [];

        return [
            'id' => $property->id,
            'service' => $property->service,
            'name' => $property->display_name,
            'external_id' => $property->external_id,
            'account_name' => $property->account_name,
            'website_url' => $meta['website_url'] ?? null,
            'measurement_id' => $meta['measurement_id'] ?? null,
            'stream_name' => $meta['stream_name'] ?? null,
            'property_type' => $meta['property_type'] ?? null,
            'permission_level' => $meta['permission_level'] ?? null,
            'time_zone' => $meta['time_zone'] ?? null,
            'currency_code' => $meta['currency_code'] ?? null,
            'is_selected' => $property->is_selected,
            'last_synced_at' => $property->last_synced_at?->toIso8601String(),
            'last_error_code' => $property->last_error_code,
            'last_error_at' => $property->last_error_at?->toIso8601String(),
        ];
    }

    /** The site PageSpeed tests by default: last tested, else the GA4 stream, else the Search Console property. */
    private function websiteUrl($properties, array $preferences): ?string
    {
        if (! empty($preferences['pagespeed_url'])) {
            return $preferences['pagespeed_url'];
        }

        $ga4 = $properties->first(fn ($p) => $p->service === GoogleProperty::GA4 && $p->is_selected);
        if ($url = $ga4?->meta['website_url'] ?? null) {
            return $url;
        }

        $site = $properties->first(fn ($p) => $p->service === GoogleProperty::SEARCH_CONSOLE && $p->is_selected)?->external_id;

        return match (true) {
            ! $site => null,
            str_starts_with($site, 'sc-domain:') => 'https://' . substr($site, strlen('sc-domain:')) . '/',
            default => $site,
        };
    }

    /** The Google Ads account Ads Manager uses (name only - no tokens). */
    private function adsAccount(int $userId): ?array
    {
        $account = AdAccountSelection::resolve($userId, 'google');

        return $account ? ['name' => $account->name ?: $account->username, 'customer_id' => $account->platform_account_id] : null;
    }

    private function nextSync($properties, string $frequency): ?string
    {
        $hours = self::SYNC_HOURS[$frequency] ?? null;
        $last = $properties->where('is_selected', true)->pluck('last_synced_at')->filter()->min();

        if (! $hours || ! $last) {
            return null;
        }

        $next = $last->copy()->addHours($hours)->startOfHour();

        return ($next->isPast() ? now()->addHour()->startOfHour() : $next)->toIso8601String();
    }

    private function urls(): array
    {
        return [
            'overview' => route('admin.google.overview'),
            'analytics' => route('admin.google.analytics'),
            'search_console' => route('admin.google.search-console'),
            'advertising' => route('admin.google.advertising'),
            'pagespeed' => route('admin.google.website-performance'),
            'business_profile' => route('admin.google.business-profile'),
            'tag_manager' => route('admin.google.tag-manager'),
            'integrations' => route('admin.google.integrations'),
            'setup' => route('admin.google.setup'),
            'connect' => route('admin.google.connect'),
            'properties' => route('admin.google.properties'),
            'reports' => route('admin.google.reports'),
            'preferences' => route('admin.google.preferences'),
            'health' => route('admin.google.health'),
            'settings_ga4' => route('admin.google.settings', ['service' => 'ga4']),
            'settings_search_console' => route('admin.google.settings', ['service' => 'search-console']),
            'ads' => route('admin.ads.campaigns.index', ['platform' => 'google']),
            'hub' => route('admin.connections.index'),
            'ads_connect' => route('admin.ads.dashboard'),
            'upgrade' => route('admin.subscription.select'),
            'pixels' => route('admin.integrations.index'),
            'privacy' => route('privacy'),
            'google_permissions' => 'https://myaccount.google.com/connections',
            'api' => [
                'overview' => route('admin.google.api.overview'),
                'ga4' => route('admin.google.api.ga4'),
                'realtime' => route('admin.google.api.realtime'),
                'search_console' => route('admin.google.api.search-console'),
                'inspect' => route('admin.google.api.inspect'),
                'google_ads' => route('admin.google.api.google-ads'),
                'pagespeed' => route('admin.google.api.pagespeed'),
                'discover' => route('admin.google.api.properties.sync'),
                'select' => route('admin.google.api.properties.select'),
                'preferences' => route('admin.google.api.preferences'),
                'refresh' => route('admin.google.api.refresh'),
                'check' => route('admin.google.api.check'),
                'report_preview' => route('admin.google.api.reports.preview'),
                'report_export' => route('admin.google.api.reports.export'),
                'disconnect' => route('admin.google.disconnect'),
            ],
        ];
    }
}
