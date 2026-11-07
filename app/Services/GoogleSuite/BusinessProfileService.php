<?php

namespace App\Services\GoogleSuite;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Services\Connections\Drivers\GoogleDriver;
use App\Support\Connections\ConnectionFlags;
use App\Support\Google\GoogleApi;
use App\Support\Google\GoogleApiException;
use App\Support\Google\ReportRange;
use Carbon\Carbon;

/**
 * Google Business Profile for the Google Suite: per-location performance
 * (Business Profile Performance API), reviews and review replies (My
 * Business v4). Locations are the SocialAccounts the main Google sign-in
 * imports once business.manage is granted (SocialAuthService); their
 * consent is that sign-in's SocialConnection (step google.oauth), never
 * the read-only Google Suite consent.
 *
 * Every figure is what Google returns - days Google reports nothing for
 * count as 0 only because Google omits zero values.
 */
class BusinessProfileService
{
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';
    public const FLAG = 'google.business_profile';

    public const ACCOUNTS_API = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';
    public const INFO_API = 'https://mybusinessbusinessinformation.googleapis.com/v1/';
    public const PERFORMANCE_API = 'https://businessprofileperformance.googleapis.com/v1/';
    public const REVIEWS_API = 'https://mybusiness.googleapis.com/v4/';

    /** Business Profile Performance daily metrics, grouped the way the page shows them. */
    public const METRICS = [
        'BUSINESS_IMPRESSIONS_DESKTOP_MAPS' => 'views',
        'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'views',
        'BUSINESS_IMPRESSIONS_MOBILE_MAPS' => 'views',
        'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => 'views',
        'CALL_CLICKS' => 'calls',
        'BUSINESS_DIRECTION_REQUESTS' => 'directions',
        'WEBSITE_CLICKS' => 'website_clicks',
        'BUSINESS_CONVERSATIONS' => 'messages',
        'BUSINESS_BOOKINGS' => 'bookings',
    ];

    public const KPIS = ['views', 'calls', 'directions', 'website_clicks', 'messages', 'bookings'];

    /** Search vs Maps split of the views, for the breakdown. */
    private const VIEW_SOURCES = [
        'BUSINESS_IMPRESSIONS_DESKTOP_MAPS' => 'maps', 'BUSINESS_IMPRESSIONS_MOBILE_MAPS' => 'maps',
        'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'search', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => 'search',
    ];

    private const STARS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    /** What the page boots with: switched on, connected, granted, and the imported locations. */
    public static function status(int $userId): array
    {
        if (! ConnectionFlags::on(self::FLAG)) {
            return ['enabled' => false, 'connected' => false, 'granted' => false, 'locations' => []];
        }

        $connection = self::connectionFor($userId);

        return [
            'enabled' => true,
            'connected' => $connection !== null,
            'granted' => $connection !== null && in_array(self::SCOPE, $connection->granted_scopes ?? [], true),
            'locations' => self::locations($userId)->map(fn (SocialAccount $l) => self::present($l))->values()->all(),
        ];
    }

    /** The main Google sign-in (YouTube / Ads / Business Profile consent). */
    public static function connectionFor(int $userId): ?SocialConnection
    {
        return SocialConnection::where(['user_id' => $userId, 'platform' => 'google', 'step' => GoogleDriver::OAUTH])
            ->where(fn ($q) => $q->whereNull('last_error')->orWhere('last_error', '!=', SocialConnection::DISCONNECTED_BY_USER))
            ->latest('id')
            ->first();
    }

    public static function locations(int $userId)
    {
        return SocialAccount::where(['user_id' => $userId, 'platform' => 'google', 'account_type' => 'location'])
            ->orderBy('name')
            ->get();
    }

    public static function present(SocialAccount $location): array
    {
        $meta = $location->metadata ?? [];

        return [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $meta['address'] ?? null,
            'maps_uri' => $meta['maps_uri'] ?? null,
            'review_uri' => $meta['new_review_uri'] ?? null,
            'website' => $meta['website'] ?? null,
        ];
    }

    /** @return array{data?: array, error?: array, fetched_at?: string} */
    public function dashboard(int $userId, ReportRange $range, ?int $locationId, bool $fresh = false): array
    {
        try {
            $location = $this->location($userId, $locationId);
            $api = $this->api($userId);

            $result = GoogleReportCache::remember($userId, 'gbp:' . $location->id . ':' . $range->cacheKey(), $fresh, fn () => [
                'data' => ['location' => self::present($location)] + $this->performance($api, $location, $range),
                'fetched_at' => now()->toIso8601String(),
            ]);
            // SocialEaz's own records, never cached: a post just created shows straight away.
            $result['data']['posts'] = self::recentPosts($location);

            return $result;
        } catch (GoogleApiException $e) {
            return ['error' => $e->toArray()];
        }
    }

    /** The latest posts SocialEaz created for this location, with their publishing status. */
    public static function recentPosts(SocialAccount $location, int $limit = 8): array
    {
        return Post::where(['user_id' => $location->user_id, 'social_account_id' => $location->id, 'platform' => 'google'])
            ->with('media')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'content' => mb_strimwidth((string) $p->content, 0, 220, '…'),
                // pending/scheduled/publishing/completed/failed -> what the seller needs to know.
                'status' => match (true) {
                    $p->status === 'completed' => 'live',
                    $p->status === 'failed' => 'failed',
                    $p->status === 'scheduled' || ($p->schedule_mode && $p->schedule_at && $p->schedule_at > now()) => 'scheduled',
                    default => 'publishing',
                },
                'error' => $p->status === 'failed' ? $p->error_message : null,
                'at' => ($p->status === 'scheduled' ? $p->schedule_at : $p->created_at)?->toIso8601String(),
                'url' => $p->status === 'completed' && $p->getRawOriginal('post_url') ? $p->getRawOriginal('post_url') : null,
                'thumbnail' => $p->media->first()?->media_url,
            ])
            ->all();
    }

    /** One page of reviews, newest first, with the location's rating summary. */
    public function reviews(int $userId, int $locationId, ?string $pageToken = null): array
    {
        try {
            $location = $this->location($userId, $locationId);
            $body = $this->api($userId)->get($this->reviewsUrl($location), array_filter([
                'pageSize' => 20,
                'orderBy' => 'updateTime desc',
                'pageToken' => $pageToken,
            ]));

            return ['data' => [
                'average_rating' => isset($body['averageRating']) ? round((float) $body['averageRating'], 1) : null,
                'total' => (int) ($body['totalReviewCount'] ?? 0),
                'next_page_token' => $body['nextPageToken'] ?? null,
                'reviews' => array_map(fn (array $r) => $this->review($r), $body['reviews'] ?? []),
            ]];
        } catch (GoogleApiException $e) {
            return ['error' => $e->toArray()];
        }
    }

    /** Post or replace the owner's public reply to one review. */
    public function reply(int $userId, int $locationId, string $reviewId, string $comment): array
    {
        try {
            $location = $this->location($userId, $locationId);
            $body = $this->api($userId)->put($this->reviewsUrl($location) . '/' . rawurlencode($reviewId) . '/reply', ['comment' => $comment]);

            GoogleReportCache::flush($userId);

            return ['data' => [
                'review_id' => $reviewId,
                'reply' => ['comment' => $body['comment'] ?? $comment, 'updated_at' => $body['updateTime'] ?? now()->toIso8601String()],
            ], 'message' => __('google_suite.gbp.reply_saved')];
        } catch (GoogleApiException $e) {
            return ['error' => $e->toArray()];
        }
    }

    private function performance(GoogleApi $api, SocialAccount $location, ReportRange $range): array
    {
        // One call covers the previous and current periods.
        $query = $this->dateQuery('dailyRange.startDate', $range->previousStart) + $this->dateQuery('dailyRange.endDate', $range->end);
        // Repeated dailyMetrics params: Google wants dailyMetrics=A&dailyMetrics=B, not dailyMetrics[0]=A.
        $url = self::PERFORMANCE_API . 'locations/' . rawurlencode($location->platform_account_id) . ':fetchMultiDailyMetricsTimeSeries?'
            . implode('&', array_map(fn ($m) => 'dailyMetrics=' . $m, array_keys(self::METRICS)))
            . '&' . http_build_query($query);

        $body = $api->get($url);

        // [metric => [date => value]]
        $byMetric = [];
        foreach ($body['multiDailyMetricTimeSeries'] ?? [] as $group) {
            foreach ($group['dailyMetricTimeSeries'] ?? [] as $series) {
                $metric = $series['dailyMetric'] ?? null;
                foreach ($series['timeSeries']['datedValues'] ?? [] as $point) {
                    $d = $point['date'] ?? [];
                    if (! isset($d['year'], $d['month'], $d['day'])) {
                        continue;
                    }
                    $date = sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']);
                    // Google omits "value" when it is 0.
                    $byMetric[$metric][$date] = (int) ($point['value'] ?? 0);
                }
            }
        }

        $empty = array_fill_keys(self::KPIS, 0);
        $totals = ['current' => $empty, 'previous' => $empty];
        $sources = ['search' => 0, 'maps' => 0];
        $trend = ['dates' => []] + array_fill_keys(self::KPIS, ['current' => [], 'previous' => []]);

        for ($i = 0; $i < $range->days(); $i++) {
            $day = $range->start->copy()->addDays($i)->toDateString();
            $previousDay = $range->previousStart->copy()->addDays($i)->toDateString();
            $trend['dates'][] = $day;

            foreach (['current' => $day, 'previous' => $previousDay] as $period => $date) {
                $values = $empty;
                foreach (self::METRICS as $metric => $kpi) {
                    $value = $byMetric[$metric][$date] ?? 0;
                    $values[$kpi] += $value;
                    if ($period === 'current' && isset(self::VIEW_SOURCES[$metric])) {
                        $sources[self::VIEW_SOURCES[$metric]] += $value;
                    }
                }
                foreach (self::KPIS as $kpi) {
                    $trend[$kpi][$period][] = $values[$kpi];
                    $totals[$period][$kpi] += $values[$kpi];
                }
            }
        }

        return [
            'totals' => $totals,
            'trend' => $trend,
            'view_sources' => $sources,
            // Calls + directions + website clicks as a fraction of views; null when there were no views.
            'action_rate' => $totals['current']['views'] > 0
                ? ($totals['current']['calls'] + $totals['current']['directions'] + $totals['current']['website_clicks']) / $totals['current']['views']
                : null,
        ];
    }

    private function review(array $r): array
    {
        return [
            'id' => $r['reviewId'] ?? null,
            'author' => ($r['reviewer']['isAnonymous'] ?? false) ? null : ($r['reviewer']['displayName'] ?? null),
            'photo' => $r['reviewer']['profilePhotoUrl'] ?? null,
            'rating' => self::STARS[$r['starRating'] ?? ''] ?? null,
            // Google appends "(Translated by Google)" blocks to the original; keep the text as Google sends it.
            'comment' => $r['comment'] ?? null,
            'created_at' => $r['createTime'] ?? null,
            'updated_at' => $r['updateTime'] ?? null,
            'reply' => isset($r['reviewReply']['comment'])
                ? ['comment' => $r['reviewReply']['comment'], 'updated_at' => $r['reviewReply']['updateTime'] ?? null]
                : null,
        ];
    }

    private function reviewsUrl(SocialAccount $location): string
    {
        $account = $location->metadata['parent_account_id'] ?? null;
        if (! $account) {
            throw new GoogleApiException(GoogleApiException::NOT_FOUND, 'Location has no Business Profile account');
        }

        return self::REVIEWS_API . 'accounts/' . rawurlencode($account) . '/locations/' . rawurlencode($location->platform_account_id) . '/reviews';
    }

    private function dateQuery(string $prefix, Carbon $date): array
    {
        return [$prefix . '.year' => $date->year, $prefix . '.month' => $date->month, $prefix . '.day' => $date->day];
    }

    /** The workspace's location (the first one when none is picked) - another workspace's id 404s. */
    private function location(int $userId, ?int $locationId): SocialAccount
    {
        if (! ConnectionFlags::on(self::FLAG)) {
            throw new GoogleApiException(GoogleApiException::NOT_CONFIGURED, 'Business Profile is switched off');
        }

        $locations = self::locations($userId);
        $location = $locationId ? $locations->firstWhere('id', $locationId) : $locations->first();

        if (! $location) {
            throw new GoogleApiException($locationId ? GoogleApiException::NOT_FOUND : GoogleApiException::GBP_NO_LOCATION);
        }

        return $location;
    }

    private function api(int $userId): GoogleApi
    {
        $connection = self::connectionFor($userId);

        if (! $connection) {
            throw new GoogleApiException(GoogleApiException::NOT_CONNECTED);
        }
        if (! in_array(self::SCOPE, $connection->granted_scopes ?? [], true)) {
            throw new GoogleApiException(GoogleApiException::MISSING_SCOPE);
        }

        return new GoogleApi($connection);
    }
}
