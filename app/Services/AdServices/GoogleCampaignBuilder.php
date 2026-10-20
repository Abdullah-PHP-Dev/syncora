<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\MediaAsset;
use App\Support\Ads\GoogleAdsApi;
use App\Support\Ads\GoogleAdsApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Google Ads campaign builder (ads/google/campaigns/create): Search, Display
 * and Performance Max through the Google Ads API (REST, v24).
 *
 * - Lookups (customer, conversion goals, languages, audience segments,
 *   topics, location suggestions, keyword ideas) are read live from Google.
 * - The whole campaign - budget, campaign, campaign criteria, ad group,
 *   ad group criteria, ad / asset group - is created in ONE atomic
 *   googleAds:mutate with temporary resource names, so a failure never
 *   leaves half a campaign behind. Image assets are uploaded first (they're
 *   reusable account assets) and recorded, so a retry doesn't re-upload.
 * - submit() is idempotent per key (AdBuilderRun, platform "google").
 * - Video campaigns can't be created through the API (read-only per the
 *   Video campaigns guide); Google points to Demand Gen instead, which this
 *   app builds on the YouTube page.
 */
class GoogleCampaignBuilder
{
    /** What each campaign type supports - the UI only shows what's listed here. */
    public const TYPES = [
        'SEARCH' => [
            'icon' => 'bx-search-alt',
            'bidding' => ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE', 'MAXIMIZE_CLICKS', 'TARGET_IMPRESSION_SHARE', 'MANUAL_CPC'],
            'lifetime_budget' => true,
            'devices' => ['DESKTOP', 'MOBILE', 'TABLET'],
            // No 'languages': Google removed campaign-level language targeting for Search
            // (late September 2026) - new language criteria are rejected there.
            'features' => ['networks', 'radius', 'schedule', 'keywords', 'negative_keywords', 'audiences', 'audience_exclusions', 'demographics', 'devices', 'schedule_bids', 'ip_exclusions', 'rsa'],
            'ad_group_type' => 'SEARCH_STANDARD',
        ],
        'DISPLAY' => [
            'icon' => 'bx-image-alt',
            'bidding' => ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE', 'MAXIMIZE_CLICKS', 'MANUAL_CPC'],
            // Campaign total budgets aren't available for Display.
            'lifetime_budget' => false,
            'devices' => ['DESKTOP', 'MOBILE', 'TABLET', 'CONNECTED_TV'],
            'features' => [
                'languages', 'radius', 'schedule', 'keywords', 'negative_keywords', 'audiences', 'custom_segments', 'audience_exclusions', 'optimized_targeting', 'demographics', 'devices', 'schedule_bids',
                'placements', 'topics', 'excluded_topics', 'content_labels', 'app_categories', 'operating_systems', 'carriers', 'ip_exclusions', 'frequency_cap', 'rda',
            ],
            'ad_group_type' => 'DISPLAY_STANDARD',
        ],
        'PERFORMANCE_MAX' => [
            'icon' => 'bx-rocket',
            'bidding' => ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE'],
            'lifetime_budget' => true,
            'devices' => [],
            // Signals guide Google's AI; they don't restrict who sees the ads.
            'features' => ['languages', 'radius', 'schedule', 'asset_group', 'search_themes', 'audience_signal'],
            'ad_group_type' => null,
        ],
        // YouTube page (Demand Gen - the only API-creatable YouTube video campaign; Video
        // campaigns are read-only). Targeting follows the Demand Gen guides: location and
        // language on the ad group (upgradedTargeting), audiences + demographics through one
        // Audience resource (useAudienceGrouped), channel controls for YouTube surfaces.
        // Devices, ad schedule, placements, topics and keywords aren't Demand Gen targeting.
        'DEMAND_GEN' => [
            'icon' => 'bxl-youtube',
            'youtube' => true,
            'bidding' => ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE', 'TARGET_CPC'],
            'lifetime_budget' => true,
            'devices' => [],
            'features' => ['languages', 'audiences', 'custom_segments', 'audience_exclusions', 'optimized_targeting', 'demographics', 'grouped_audience', 'channels', 'dg_video'],
            'ad_group_type' => null,
        ],
    ];

    /** Marketing goal => suggested campaign type and bidding (the goal itself isn't an API field). */
    public const GOALS = [
        'SALES' => ['icon' => 'bx-cart', 'type' => 'PERFORMANCE_MAX', 'bidding' => 'MAXIMIZE_CONVERSION_VALUE'],
        'LEADS' => ['icon' => 'bx-id-card', 'type' => 'SEARCH', 'bidding' => 'MAXIMIZE_CONVERSIONS'],
        'TRAFFIC' => ['icon' => 'bx-mouse-alt', 'type' => 'SEARCH', 'bidding' => 'MAXIMIZE_CLICKS'],
        'AWARENESS' => ['icon' => 'bx-broadcast', 'type' => 'DISPLAY', 'bidding' => 'MAXIMIZE_CLICKS'],
    ];

    public const MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];
    /** Demand Gen channel controls (selected_channels). */
    public const CHANNELS = ['youtubeInStream', 'youtubeInFeed', 'youtubeShorts', 'discover', 'gmail', 'display', 'maps'];
    public const YOUTUBE_CHANNELS = ['youtubeInStream', 'youtubeInFeed', 'youtubeShorts'];
    /** Call-to-action asset values (CallToActionTypeEnum). */
    public const CTAS = ['LEARN_MORE', 'SHOP_NOW', 'BUY_NOW', 'SIGN_UP', 'SUBSCRIBE', 'BOOK_NOW', 'ORDER_NOW', 'GET_QUOTE', 'APPLY_NOW', 'CONTACT_US', 'DOWNLOAD', 'DONATE_NOW', 'PLAY_NOW', 'SEE_MORE', 'START_NOW', 'VISIT_SITE', 'WATCH_NOW'];
    /** Age ranges as Audience AgeSegment min/max (65+ has no max). */
    public const AGE_SEGMENTS = ['AGE_RANGE_18_24' => [18, 24], 'AGE_RANGE_25_34' => [25, 34], 'AGE_RANGE_35_44' => [35, 44], 'AGE_RANGE_45_54' => [45, 54], 'AGE_RANGE_55_64' => [55, 64], 'AGE_RANGE_65_UP' => [65, null]];
    public const AGES = ['AGE_RANGE_18_24', 'AGE_RANGE_25_34', 'AGE_RANGE_35_44', 'AGE_RANGE_45_54', 'AGE_RANGE_55_64', 'AGE_RANGE_65_UP', 'AGE_RANGE_UNDETERMINED'];
    public const GENDERS = ['MALE', 'FEMALE', 'UNDETERMINED'];
    public const PARENTAL = ['PARENT', 'NOT_A_PARENT', 'UNDETERMINED'];
    public const INCOMES = ['INCOME_RANGE_0_50', 'INCOME_RANGE_50_60', 'INCOME_RANGE_60_70', 'INCOME_RANGE_70_80', 'INCOME_RANGE_80_90', 'INCOME_RANGE_90_UP', 'INCOME_RANGE_UNDETERMINED'];
    public const DAYS = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];
    public const IMPRESSION_SHARE_LOCATIONS = ['ANYWHERE_ON_PAGE', 'TOP_OF_PAGE', 'ABSOLUTE_TOP_OF_PAGE'];
    /** Display content exclusions (campaign-level negative content_label criteria). */
    public const CONTENT_LABELS = ['SEXUALLY_SUGGESTIVE', 'PROFANITY', 'TRAGEDY', 'JUVENILE', 'BELOW_THE_FOLD', 'PARKED_DOMAIN', 'EMBEDDED_VIDEO', 'LIVE_STREAMING_VIDEO'];
    public const GEO_MODES = ['PRESENCE_OR_INTEREST', 'PRESENCE'];
    public const FREQUENCY_UNITS = ['DAY', 'WEEK', 'MONTH'];
    public const MAX_SEARCH_THEMES = 25;

    /** Input key => feature flag in TYPES; anything sent for a type without the flag is rejected. */
    public const FEATURE_KEYS = [
        'negative_keywords' => 'negative_keywords', 'audiences' => 'audiences', 'audience_exclusions' => 'audience_exclusions',
        'ages' => 'demographics', 'genders' => 'demographics', 'parental' => 'demographics', 'incomes' => 'demographics',
        'placements' => 'placements', 'excluded_placements' => 'placements', 'topics' => 'topics', 'excluded_topics' => 'excluded_topics',
        'content_labels' => 'content_labels', 'app_categories' => 'app_categories', 'operating_systems' => 'operating_systems', 'carriers' => 'carriers',
        'ip_exclusions' => 'ip_exclusions', 'frequency_cap' => 'frequency_cap', 'search_themes' => 'search_themes', 'audience_signal' => 'audience_signal',
        'languages' => 'languages', 'radius' => 'radius', 'schedule' => 'schedule', 'channels' => 'channels',
    ];

    /** Lookup lists for the option pickers: GAQL + how to label a row. */
    public const OPTION_TYPES = ['operating_systems', 'carriers', 'app_categories', 'pmax_audiences'];

    /** Asset limits (RSA, responsive display ad, Performance Max asset group). */
    public const LIMITS = [
        'rsa' => ['headlines' => [3, 15, 30], 'descriptions' => [2, 4, 90], 'path' => 15],
        'rda' => ['headlines' => [1, 5, 30], 'long_headlines' => [1, 1, 90], 'descriptions' => [1, 5, 90], 'business_name' => 25, 'marketing' => [1, 15], 'square' => [1, 15], 'logo' => [0, 5]],
        'pmax' => ['headlines' => [3, 15, 30], 'long_headlines' => [1, 5, 90], 'descriptions' => [2, 5, 90], 'business_name' => 25, 'marketing' => [1, 20], 'square' => [1, 20], 'logo' => [1, 5]],
        // Demand Gen video responsive ad (the existing YouTube integration's limits).
        'dg' => ['headlines' => [1, 5, 40], 'long_headlines' => [1, 5, 90], 'descriptions' => [1, 5, 90], 'business_name' => 25, 'logo' => [1, 5], 'videos' => [1, 5], 'breadcrumb' => 15],
    ];

    public static function limitKey(string $type): string
    {
        return ['DISPLAY' => 'rda', 'PERFORMANCE_MAX' => 'pmax', 'DEMAND_GEN' => 'dg'][$type] ?? 'rsa';
    }

    public const MAX_IMAGE_BYTES = 5_242_880;
    public const MIN_DAILY_BUDGET = 1;

    private array $memo = [];

    public function __construct(private GoogleAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = GoogleAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function api(): GoogleAdsApi
    {
        return $this->api;
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public function bootstrap(): array
    {
        $c = $this->customer();

        return [
            'account' => [
                'id' => $this->api->customerId(),
                'local_id' => $this->api->account->id,
                'name' => $c['descriptiveName'] ?? $this->api->account->name,
                'currency' => $c['currencyCode'] ?? 'USD',
                'timezone' => $c['timeZone'] ?? 'UTC',
                'status' => $c['status'] ?? null,
                'active' => ($c['status'] ?? 'ENABLED') === 'ENABLED' && empty($c['manager']),
                'manager' => (bool) ($c['manager'] ?? false),
                'test' => (bool) ($c['testAccount'] ?? false),
            ],
            // Same VAT the Facebook builder shows in its budget summary.
            'vat_rate' => FacebookCampaignBuilder::VAT_RATE,
            'conversion_goals' => $this->conversionGoals(),
            'types' => self::TYPES,
            'goals' => self::GOALS,
            'options' => [
                'match_types' => self::MATCH_TYPES,
                'ages' => self::AGES,
                'genders' => self::GENDERS,
                'parental' => self::PARENTAL,
                'incomes' => self::INCOMES,
                'days' => self::DAYS,
                'impression_share_locations' => self::IMPRESSION_SHARE_LOCATIONS,
                'content_labels' => self::CONTENT_LABELS,
                'geo_modes' => self::GEO_MODES,
                'frequency_units' => self::FREQUENCY_UNITS,
                'max_search_themes' => self::MAX_SEARCH_THEMES,
                'channels' => self::CHANNELS,
                'youtube_channels' => self::YOUTUBE_CHANNELS,
                'ctas' => self::CTAS,
                'limits' => self::LIMITS,
                'min_daily_budget' => self::MIN_DAILY_BUDGET,
                'max_image_mb' => self::MAX_IMAGE_BYTES / 1048576,
            ],
        ];
    }

    private function customer(): array
    {
        return $this->memo['customer'] ??= Cache::remember($this->cacheKey('customer'), 600, fn () => $this->api->search(
            'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status, customer.manager, customer.test_account FROM customer LIMIT 1'
        )[0]['customer'] ?? []);
    }

    /** The account's conversion goals (category x origin) and whether each is biddable by default. */
    public function conversionGoals(): array
    {
        return collect($this->api->search('SELECT customer_conversion_goal.category, customer_conversion_goal.origin, customer_conversion_goal.biddable FROM customer_conversion_goal'))
            ->map(fn ($r) => ['category' => $r['customerConversionGoal']['category'] ?? null, 'origin' => $r['customerConversionGoal']['origin'] ?? null, 'biddable' => (bool) ($r['customerConversionGoal']['biddable'] ?? false)])
            ->filter(fn ($g) => $g['category'] && $g['origin'])
            ->values()->all();
    }

    /** Location suggestions (countries, regions, cities, postal codes...) for a typed name. */
    public function locations(string $query, string $locale): array
    {
        $body = $this->api->post('geoTargetConstants:suggest', ['locale' => $locale, 'locationNames' => ['names' => [$query]]]);

        return collect($body['geoTargetConstantSuggestions'] ?? [])->map(function ($s) {
            $g = $s['geoTargetConstant'] ?? [];

            return ($g['status'] ?? 'ENABLED') === 'ENABLED' && isset($g['id']) ? [
                'id' => (string) $g['id'], 'name' => $g['canonicalName'] ?? $g['name'] ?? '', 'type' => $g['targetType'] ?? '',
                'country' => $g['countryCode'] ?? '', 'reach' => isset($s['reach']) ? (int) $s['reach'] : null,
            ] : null;
        })->filter()->unique('id')->values()->all();
    }

    public function languages(): array
    {
        return Cache::remember('google-builder:languages', 86400, fn () => collect($this->api->search(
            'SELECT language_constant.id, language_constant.name, language_constant.code FROM language_constant WHERE language_constant.targetable = TRUE'
        ))->map(fn ($r) => ['id' => (string) $r['languageConstant']['id'], 'name' => $r['languageConstant']['name'], 'code' => $r['languageConstant']['code'] ?? ''])
            ->sortBy('name')->values()->all());
    }

    /** Remarketing / customer lists and custom segments owned by this customer. */
    public function audiences(): array
    {
        $lists = collect($this->api->search(
            "SELECT user_list.resource_name, user_list.name, user_list.type, user_list.size_for_search, user_list.size_for_display, user_list.eligible_for_search, user_list.eligible_for_display FROM user_list WHERE user_list.membership_status = 'OPEN'"
        ))->map(fn ($r) => [
            'id' => $r['userList']['resourceName'], 'name' => $r['userList']['name'] ?? '', 'kind' => 'user_list', 'type' => $r['userList']['type'] ?? '',
            'search' => (bool) ($r['userList']['eligibleForSearch'] ?? false), 'display' => (bool) ($r['userList']['eligibleForDisplay'] ?? false),
            'size' => (int) ($r['userList']['sizeForDisplay'] ?? $r['userList']['sizeForSearch'] ?? 0),
        ]);
        $custom = collect($this->api->search(
            "SELECT custom_audience.resource_name, custom_audience.name FROM custom_audience WHERE custom_audience.status = 'ENABLED'"
        ))->map(fn ($r) => ['id' => $r['customAudience']['resourceName'], 'name' => $r['customAudience']['name'] ?? '', 'kind' => 'custom_audience', 'type' => 'CUSTOM', 'search' => false, 'display' => true, 'size' => null]);

        return $lists->concat($custom)->values()->all();
    }

    /** Google's audience taxonomy: affinity, in-market, life events and detailed demographics. */
    public function interests(): array
    {
        return Cache::remember('google-builder:interests:' . $this->api->customerId(), 86400, function () {
            $interests = collect($this->api->search(
                "SELECT user_interest.resource_name, user_interest.name, user_interest.taxonomy_type FROM user_interest WHERE user_interest.taxonomy_type IN ('AFFINITY', 'IN_MARKET') AND user_interest.launched_to_all = TRUE"
            ))->map(fn ($r) => ['id' => $r['userInterest']['resourceName'], 'name' => $r['userInterest']['name'] ?? '', 'kind' => 'user_interest', 'type' => $r['userInterest']['taxonomyType'] ?? '']);
            $events = collect($this->api->search('SELECT life_event.resource_name, life_event.name FROM life_event WHERE life_event.launched_to_all = TRUE'))
                ->map(fn ($r) => ['id' => $r['lifeEvent']['resourceName'], 'name' => $r['lifeEvent']['name'] ?? '', 'kind' => 'life_event', 'type' => 'LIFE_EVENT']);
            $detailed = collect($this->api->search('SELECT detailed_demographic.resource_name, detailed_demographic.name FROM detailed_demographic WHERE detailed_demographic.launched_to_all = TRUE'))
                ->map(fn ($r) => ['id' => $r['detailedDemographic']['resourceName'], 'name' => $r['detailedDemographic']['name'] ?? '', 'kind' => 'detailed_demographic', 'type' => 'DETAILED_DEMOGRAPHIC']);

            return $interests->concat($events)->concat($detailed)->filter(fn ($i) => $i['name'] !== '')->sortBy('name')->values()->all();
        });
    }

    /** Operating systems, carriers, app categories (Google constants) and Performance Max audiences (this customer). */
    public function optionList(string $type): array
    {
        if ($type === 'pmax_audiences') {
            return collect($this->api->search("SELECT audience.resource_name, audience.name, audience.description FROM audience WHERE audience.status = 'ENABLED'"))
                ->map(fn ($r) => ['id' => $r['audience']['resourceName'], 'name' => $r['audience']['name'] ?? '', 'description' => $r['audience']['description'] ?? ''])
                ->filter(fn ($a) => $a['name'] !== '')->sortBy('name')->values()->all();
        }

        return Cache::remember('google-builder:options:' . $type, 86400, fn () => (match ($type) {
            'operating_systems' => collect($this->api->search('SELECT operating_system_version_constant.id, operating_system_version_constant.name, operating_system_version_constant.os_major_version, operating_system_version_constant.os_minor_version, operating_system_version_constant.operator_type FROM operating_system_version_constant'))
                ->map(fn ($r) => $r['operatingSystemVersionConstant'])
                ->map(fn ($o) => ['id' => (string) $o['id'], 'name' => trim(($o['name'] ?? '') . (($o['operatorType'] ?? '') === 'GREATER_THAN_EQUALS_TO' ? ' +' : ''))]),
            'carriers' => collect($this->api->search('SELECT carrier_constant.id, carrier_constant.name, carrier_constant.country_code FROM carrier_constant'))
                ->map(fn ($r) => $r['carrierConstant'])
                ->map(fn ($c) => ['id' => (string) $c['id'], 'name' => ($c['name'] ?? '') . (! empty($c['countryCode']) ? ' (' . $c['countryCode'] . ')' : ''), 'country' => $c['countryCode'] ?? '']),
            'app_categories' => collect($this->api->search('SELECT mobile_app_category_constant.id, mobile_app_category_constant.name FROM mobile_app_category_constant'))
                ->map(fn ($r) => ['id' => (string) $r['mobileAppCategoryConstant']['id'], 'name' => $r['mobileAppCategoryConstant']['name'] ?? '']),
        })->filter(fn ($o) => $o['id'] !== '' && trim($o['name']) !== '')->sortBy('name')->values()->all());
    }

    public function topics(): array
    {
        return Cache::remember('google-builder:topics', 86400, fn () => collect($this->api->search('SELECT topic_constant.id, topic_constant.path FROM topic_constant'))
            ->map(fn ($r) => ['id' => (string) ($r['topicConstant']['id'] ?? ''), 'name' => implode(' › ', array_filter($r['topicConstant']['path'] ?? []))])
            ->filter(fn ($t) => $t['id'] !== '' && $t['name'] !== '')->sortBy('name')->values()->all());
    }

    /** Keyword Planner ideas for seed keywords and/or a URL. */
    public function keywordIdeas(array $seeds, ?string $url, array $locationIds, ?string $languageId): array
    {
        $body = array_filter([
            'language' => $languageId ? 'languageConstants/' . $languageId : null,
            'geoTargetConstants' => array_map(fn ($id) => 'geoTargetConstants/' . $id, $locationIds),
            'includeAdultKeywords' => false,
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
            'pageSize' => 50,
        ]);
        $seeds = array_values(array_filter(array_map('trim', $seeds)));
        if ($seeds && $url) {
            $body['keywordAndUrlSeed'] = ['url' => $url, 'keywords' => $seeds];
        } elseif ($seeds) {
            $body['keywordSeed'] = ['keywords' => $seeds];
        } else {
            $body['urlSeed'] = ['url' => $url];
        }

        return collect($this->api->post('customers/' . $this->api->customerId() . ':generateKeywordIdeas', $body)['results'] ?? [])
            ->map(fn ($r) => [
                'text' => $r['text'] ?? '',
                'searches' => isset($r['keywordIdeaMetrics']['avgMonthlySearches']) ? (int) $r['keywordIdeaMetrics']['avgMonthlySearches'] : null,
                'competition' => $r['keywordIdeaMetrics']['competition'] ?? null,
                'low_bid' => $this->unmicro($r['keywordIdeaMetrics']['lowTopOfPageBidMicros'] ?? null),
                'high_bid' => $this->unmicro($r['keywordIdeaMetrics']['highTopOfPageBidMicros'] ?? null),
            ])->filter(fn ($k) => $k['text'] !== '')->take(50)->values()->all();
    }

    private function cacheKey(string $name): string
    {
        return 'google-builder:' . $this->api->account->id . ':' . $name;
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------

    public function submit(array $input, string $key): array
    {
        $userId = (int) $this->api->account->user_id;
        $hash = hash('sha256', json_encode($input));

        $lock = Cache::lock("ad-builder:{$userId}:{$key}", 900);
        if (! $lock->get()) {
            return ['success' => false, 'status' => 409, 'message' => __('google_builder.server.in_progress')];
        }

        try {
            $run = AdBuilderRun::firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => $key],
                ['social_account_id' => $this->api->account->id, 'platform' => 'google', 'payload_hash' => $hash, 'status' => 'running', 'results' => []]
            );

            if ($run->platform !== 'google' || (int) $run->social_account_id !== (int) $this->api->account->id) {
                return ['success' => false, 'status' => 409, 'message' => __('google_builder.server.key_reused')];
            }
            if ($run->status === 'completed') {
                return $this->completedResponse($run, true);
            }
            $run->forceFill(['payload_hash' => $hash, 'status' => 'running', 'failed_step' => null, 'error' => null])->save();
            $step = 'validation';

            try {
                $media = $this->validateSelection($input, $userId);
                $step = 'assets';
                $assets = $this->assetStep($run, $media);
                $step = 'campaign';
                $this->campaignStep($run, $input, $assets);
                $step = 'goals';
                $this->goalStep($run, $input);
            } catch (GoogleAdsApiException|\InvalidArgumentException $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                Log::warning('Google campaign builder step failed.', ['run' => $run->id, 'step' => $step, 'error' => $e->getMessage(), 'code' => $e instanceof GoogleAdsApiException ? $e->errorCode : null]);

                return ['success' => false, 'status' => 422, 'step' => $step, 'message' => $e->getMessage(), 'created' => $this->createdSummary($run), 'retryable' => true, 'reconnect' => $e instanceof GoogleAdsApiException && $e->isAuthError()];
            } catch (\Throwable $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                report($e);

                return ['success' => false, 'status' => 500, 'step' => $step, 'message' => __('google_builder.server.internal_error', ['ref' => $run->id]), 'created' => $this->createdSummary($run), 'retryable' => true];
            }

            $run->forceFill(['status' => 'completed'])->save();

            return $this->completedResponse($run, false);
        } finally {
            $lock->release();
        }
    }

    private function completedResponse(AdBuilderRun $run, bool $replayed): array
    {
        return [
            'success' => true,
            'replayed' => $replayed,
            'message' => $replayed ? __('google_builder.server.replayed') : __('google_builder.server.created'),
            'created' => $this->createdSummary($run),
            'campaign_id' => $run->result('campaign.id'),
            'campaign_local_id' => $run->result('campaign.local'),
            'status' => $run->result('campaign.status', 'PAUSED'),
        ];
    }

    private function createdSummary(AdBuilderRun $run): array
    {
        return array_values(array_filter([
            $run->result('assets') ? ['type' => 'assets', 'id' => (string) count((array) $run->result('assets'))] : null,
            $run->result('campaign.id') ? ['type' => 'campaign', 'id' => $run->result('campaign.id')] : null,
            $run->result('goals.done') ? ['type' => 'goals', 'id' => (string) $run->result('goals.count', 0)] : null,
        ]));
    }

    /**
     * Rules that would otherwise fail inside the atomic mutate are checked
     * up front: features the campaign type doesn't support, audience
     * segments from another customer, gallery images (owner, type, size,
     * aspect ratio).
     */
    private function validateSelection(array $input, int $userId): array
    {
        $type = $input['campaign_type'];
        $spec = self::TYPES[$type];
        $c = $input['campaign'];
        $t = $input['targeting'] ?? [];

        if (! in_array($c['bidding'], $spec['bidding'], true)) {
            throw new \InvalidArgumentException(__('google_builder.server.bidding_unsupported'));
        }
        if (($c['budget_type'] ?? 'daily') === 'lifetime') {
            if (empty($spec['lifetime_budget'])) {
                throw new \InvalidArgumentException(__('google_builder.server.lifetime_unsupported'));
            }
            if (empty($c['start_date']) || empty($c['end_date'])) {
                throw new \InvalidArgumentException(__('google_builder.server.lifetime_dates'));
            }
        }
        if (! empty($c['goals']) && ! in_array($c['bidding'], ['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE'], true)) {
            // Goals only steer conversion-based bidding; harmless otherwise but say so instead of silently ignoring.
            throw new \InvalidArgumentException(__('google_builder.server.goals_need_conversion_bidding'));
        }
        if (! empty($c['goals']) && ! collect($c['goals'])->contains('biddable', true)) {
            throw new \InvalidArgumentException(__('google_builder.server.goals_none'));
        }
        $prefix = 'customers/' . $this->api->customerId() . '/';
        foreach ([...($t['audience_exclusions'] ?? []), ...(isset($t['audience_signal']['id']) ? [$t['audience_signal']] : [])] as $a) {
            if (! str_starts_with($a['id'], $prefix)) {
                throw new \InvalidArgumentException(__('google_builder.server.audience_not_owned', ['name' => $a['name'] ?? $a['id']]));
            }
        }
        foreach ($t['audiences'] ?? [] as $a) {
            if (! str_starts_with($a['id'], $prefix)) {
                throw new \InvalidArgumentException(__('google_builder.server.audience_not_owned', ['name' => $a['name'] ?? $a['id']]));
            }
            if ($a['kind'] === 'custom_audience' && ! in_array('custom_segments', $spec['features'], true)) {
                throw new \InvalidArgumentException(__('google_builder.server.custom_segments_display'));
            }
        }
        foreach (array_keys($t['devices'] ?? []) as $device) {
            if (! in_array($device, $spec['devices'], true)) {
                throw new \InvalidArgumentException(__('google_builder.server.device_unsupported', ['device' => $device]));
            }
        }
        // Every targeting option must be supported by the campaign type (TYPES[...]['features']).
        $unsupported = array_keys(array_filter(self::FEATURE_KEYS, fn ($feature, $key) => ! empty($t[$key]) && ! in_array($feature, $spec['features'], true), ARRAY_FILTER_USE_BOTH));
        if (! empty($input['ad_group']['keywords']) && ! in_array('keywords', $spec['features'], true)) {
            $unsupported[] = 'keywords';
        }
        if ($unsupported) {
            throw new \InvalidArgumentException(__('google_builder.server.feature_unsupported', ['fields' => implode(', ', $unsupported), 'type' => __('google_builder.types.' . $type . '.label')]));
        }
        if ($type === 'SEARCH' && empty($input['ad_group']['keywords'])) {
            throw new \InvalidArgumentException(__('google_builder.server.keywords_required'));
        }

        if ($type === 'DEMAND_GEN') {
            if (empty(array_filter($t['channels'] ?? []))) {
                throw new \InvalidArgumentException(__('google_builder.server.channels_required'));
            }
            $videos = $input['ad']['videos'] ?? [];
            [$vMin, $vMax] = self::LIMITS['dg']['videos'];
            if (count($videos) < $vMin || count($videos) > $vMax || collect($videos)->contains(fn ($v) => ! preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $v))) {
                throw new \InvalidArgumentException(__('google_builder.server.videos_invalid', ['min' => $vMin, 'max' => $vMax]));
            }
            // An Audience needs at least one included value per dimension.
            foreach (['ages' => self::AGES, 'genders' => self::GENDERS, 'parental' => self::PARENTAL, 'incomes' => self::INCOMES] as $key => $all) {
                if (count(array_unique($t[$key] ?? [])) >= count($all)) {
                    throw new \InvalidArgumentException(__('google_builder.server.demographic_all', ['group' => __('google_builder.audience.' . $key)]));
                }
            }
        }

        // Gallery images for Display / Performance Max / Demand Gen (logo).
        $refs = [];
        if ($type !== 'SEARCH') {
            $limits = self::LIMITS[self::limitKey($type)];
            foreach (array_intersect_key(['marketing' => 1.91, 'square' => 1.0, 'logo' => 1.0], $limits) as $slot => $ratio) {
                $ids = array_values(array_unique(array_map('intval', $input['ad']['images'][$slot] ?? [])));
                [$min, $max] = $limits[$slot];
                if (count($ids) < $min || count($ids) > $max) {
                    throw new \InvalidArgumentException(__('google_builder.server.image_count', ['slot' => __('google_builder.ad.images_' . $slot), 'min' => $min, 'max' => $max]));
                }
                $assets = MediaAsset::where('user_id', $userId)->whereIn('id', $ids)->get()->keyBy('id');
                foreach ($ids as $id) {
                    $asset = $assets->get($id);
                    if (! $asset || $asset->type !== 'image') {
                        throw new \InvalidArgumentException(__('google_builder.server.image_not_owned'));
                    }
                    if ($asset->size && $asset->size > self::MAX_IMAGE_BYTES) {
                        throw new \InvalidArgumentException(__('google_builder.server.image_too_big', ['name' => $asset->original_name, 'mb' => 5]));
                    }
                    if ($asset->width && $asset->height && abs($asset->width / $asset->height - $ratio) > $ratio * 0.02) {
                        throw new \InvalidArgumentException(__('google_builder.server.image_ratio', ['name' => $asset->original_name, 'ratio' => $slot === 'marketing' ? '1.91:1' : '1:1']));
                    }
                    $refs[$slot][] = $asset;
                }
            }
        }

        return $refs;
    }

    /** Uploads gallery images as IMAGE assets (each recorded, so retries reuse them). */
    private function assetStep(AdBuilderRun $run, array $media): array
    {
        $out = [];
        foreach ($media as $slot => $assets) {
            foreach ($assets as $asset) {
                $key = 'assets.' . $asset->id;
                if (! $resource = $run->result($key)) {
                    $body = $this->api->post('customers/' . $this->api->customerId() . '/assets:mutate', ['operations' => [['create' => [
                        'name' => mb_substr(pathinfo((string) $asset->original_name, PATHINFO_FILENAME) ?: 'image', 0, 80) . ' #' . $asset->id . ' ' . now()->format('YmdHis'),
                        'type' => 'IMAGE',
                        'imageAsset' => ['data' => base64_encode($this->bytes($asset))],
                    ]]]]);
                    $resource = $body['results'][0]['resourceName'] ?? null;
                    if (! $resource) {
                        throw new GoogleAdsApiException(__('google_builder.server.no_id', ['object' => 'asset']));
                    }
                    $run->remember($key, $resource);
                }
                $out[$slot][] = $resource;
            }
        }

        return $out;
    }

    private function bytes(MediaAsset $asset): string
    {
        try {
            if ($asset->disk && $asset->path && Storage::disk($asset->disk)->exists($asset->path)) {
                return Storage::disk($asset->disk)->get($asset->path);
            }
        } catch (\Throwable) {
            // fall back to the URL
        }
        $response = Http::timeout(120)->get($asset->url);
        if (! $response->successful()) {
            throw new \InvalidArgumentException(__('google_builder.server.unreadable', ['name' => $asset->original_name]));
        }

        return $response->body();
    }

    /** The full atomic mutate: budget -> campaign -> criteria -> ad group / asset group -> ads. */
    private function campaignStep(AdBuilderRun $run, array $input, array $assets): void
    {
        if ($run->result('campaign.id')) {
            return;
        }

        $responses = $this->api->mutate($this->operations($input, $assets));
        $campaign = collect($responses)->pluck('campaignResult.resourceName')->filter()->first();
        if (! $campaign) {
            throw new GoogleAdsApiException(__('google_builder.server.no_id', ['object' => 'campaign']));
        }
        $adGroup = collect($responses)->pluck('adGroupResult.resourceName')->filter()->first()
            ?? collect($responses)->pluck('assetGroupResult.resourceName')->filter()->first();
        $budget = collect($responses)->pluck('campaignBudgetResult.resourceName')->filter()->first();
        $adResource = collect($responses)->pluck('adGroupAdResult.resourceName')->filter()->first();
        $status = ($input['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
        $run->remember('campaign', ['id' => basename($campaign), 'resource' => $campaign, 'ad_group' => $adGroup, 'status' => $status]);

        $this->storeLocal($run, $input, basename($campaign), $budget, $adGroup, $adResource, $status);
    }

    /** Builds the googleAds:mutate operations (public for tests). */
    public function operations(array $input, array $assets = []): array
    {
        $type = $input['campaign_type'];
        $c = $input['campaign'];
        $t = $input['targeting'] ?? [];
        $g = $input['ad_group'] ?? [];
        $ad = $input['ad'] ?? [];
        $budget = $this->api->resource('campaignBudgets', -1);
        $campaign = $this->api->resource('campaigns', -2);
        $adGroup = $this->api->resource('adGroups', -3);
        $assetGroup = $this->api->resource('assetGroups', -4);
        $ops = [];

        $lifetime = ($c['budget_type'] ?? 'daily') === 'lifetime';
        $ops[] = ['campaignBudgetOperation' => ['create' => [
            'resourceName' => $budget,
            'name' => mb_substr($c['name'], 0, 200) . ' · ' . now()->format('Y-m-d H:i:s'),
            // Lifetime = campaign total budget: CUSTOM_PERIOD + totalAmountMicros
            // (needs the campaign's start and end date); daily = average daily amount.
            ...($lifetime
                ? ['period' => 'CUSTOM_PERIOD', 'totalAmountMicros' => $this->micro($c['budget'])]
                : ['amountMicros' => $this->micro($c['budget']), 'deliveryMethod' => 'STANDARD']),
            // Performance Max, smart bidding and total budgets need a campaign-owned budget.
            'explicitlyShared' => false,
        ]]];

        $campaignBody = array_filter([
            'resourceName' => $campaign,
            'name' => mb_substr($c['name'], 0, 255),
            'status' => ($input['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ENABLED' : 'PAUSED',
            'advertisingChannelType' => $type,
            'campaignBudget' => $budget,
            'startDateTime' => ! empty($c['start_date']) ? $c['start_date'] . ' 00:00:00' : null,
            'endDateTime' => ! empty($c['end_date']) ? $c['end_date'] . ' 23:59:59' : null,
            // Demand Gen rejects geoTargetTypeSetting (confirmed live in YoutubeAdService).
            'geoTargetTypeSetting' => $type === 'DEMAND_GEN' ? null : ['positiveGeoTargetType' => $c['geo_mode'] ?? 'PRESENCE_OR_INTEREST', 'negativeGeoTargetType' => 'PRESENCE'],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
            'networkSettings' => match ($type) {
                'SEARCH' => ['targetGoogleSearch' => true, 'targetSearchNetwork' => (bool) ($c['search_partners'] ?? false), 'targetContentNetwork' => (bool) ($c['display_expansion'] ?? false), 'targetPartnerSearchNetwork' => false],
                'DISPLAY' => ['targetGoogleSearch' => false, 'targetSearchNetwork' => false, 'targetContentNetwork' => true, 'targetPartnerSearchNetwork' => false],
                default => null,
            },
        ], fn ($v) => $v !== null);
        if ($type === 'PERFORMANCE_MAX') {
            // Business name and logo go in the asset group, not campaign assets.
            $campaignBody['brandGuidelinesEnabled'] = false;
        }
        if ($type === 'DEMAND_GEN') {
            // Location / language targeting on the ad group instead of the campaign.
            $campaignBody['demandGenCampaignSettings'] = ['upgradedTargeting' => true];
        }
        if ($type === 'DISPLAY' && ! empty($t['frequency_cap']['cap'])) {
            // At most N impressions per person per day / week / month, across the campaign.
            $campaignBody['frequencyCaps'] = [[
                'key' => ['level' => 'CAMPAIGN', 'eventType' => 'IMPRESSION', 'timeUnit' => $t['frequency_cap']['unit'] ?? 'DAY', 'timeLength' => 1],
                'cap' => (int) $t['frequency_cap']['cap'],
            ]];
        }
        $ops[] = ['campaignOperation' => ['create' => $campaignBody + $this->bidding($c, $type)]];

        foreach ($this->campaignCriteria($campaign, $type, $c, $t) as $criterion) {
            $ops[] = ['campaignCriterionOperation' => ['create' => $criterion]];
        }

        if ($type === 'PERFORMANCE_MAX') {
            return array_merge($ops, $this->assetGroupOperations($campaign, $assetGroup, $input, $assets));
        }
        if ($type === 'DEMAND_GEN') {
            return array_merge($ops, $this->demandGenOperations($campaign, $adGroup, $input, $assets));
        }

        $adGroupBody = [
            'resourceName' => $adGroup,
            'campaign' => $campaign,
            'name' => mb_substr(($g['name'] ?? '') ?: $c['name'] . ' · ' . __('google_builder.defaults.ad_group'), 0, 255),
            'status' => 'ENABLED',
            'type' => self::TYPES[$type]['ad_group_type'],
        ];
        if ($c['bidding'] === 'MANUAL_CPC' && ! empty($c['max_cpc'])) {
            $adGroupBody['cpcBidMicros'] = $this->micro($c['max_cpc']);
        }
        if (! empty($t['audiences'])) {
            // Observation (bidOnly) keeps reach and only adjusts reporting / bids; targeting narrows to the segments.
            $adGroupBody['targetingSetting'] = ['targetRestrictions' => [['targetingDimension' => 'AUDIENCE', 'bidOnly' => ($t['audience_mode'] ?? 'observation') === 'observation']]];
        }
        if ($type === 'DISPLAY') {
            // Optimized targeting (the replacement for audience expansion) finds similar converting people.
            $adGroupBody['optimizedTargetingEnabled'] = (bool) ($t['optimized_targeting'] ?? false);
        }
        $ops[] = ['adGroupOperation' => ['create' => $adGroupBody]];

        foreach ($this->adGroupCriteria($adGroup, $type, $g, $t) as $criterion) {
            $ops[] = ['adGroupCriterionOperation' => ['create' => $criterion]];
        }

        $ops[] = ['adGroupAdOperation' => ['create' => ['adGroup' => $adGroup, 'status' => 'ENABLED', 'ad' => $type === 'SEARCH' ? $this->searchAd($ad) : $this->displayAd($ad, $assets)]]];

        return $ops;
    }

    private function bidding(array $c, string $type = ''): array
    {
        // Demand Gen rejects a target inside maximizeConversions /
        // maximizeConversionValue ("operation is not allowed for the given
        // context" on maximize_conversions.target_cpa_micros, seen live) - it
        // takes the standalone Target CPA / Target ROAS strategies instead.
        if ($type === 'DEMAND_GEN' && $c['bidding'] === 'MAXIMIZE_CONVERSIONS' && ! empty($c['target_cpa'])) {
            return ['targetCpa' => ['targetCpaMicros' => $this->micro($c['target_cpa'])]];
        }
        if ($type === 'DEMAND_GEN' && $c['bidding'] === 'MAXIMIZE_CONVERSION_VALUE' && ! empty($c['target_roas'])) {
            return ['targetRoas' => ['targetRoas' => round($c['target_roas'] / 100, 4)]];
        }

        return match ($c['bidding']) {
            // Target CPA / ROAS are the optional targets of these two strategies.
            'MAXIMIZE_CONVERSIONS' => ['maximizeConversions' => ! empty($c['target_cpa']) ? ['targetCpaMicros' => $this->micro($c['target_cpa'])] : (object) []],
            'MAXIMIZE_CONVERSION_VALUE' => ['maximizeConversionValue' => ! empty($c['target_roas']) ? ['targetRoas' => round($c['target_roas'] / 100, 4)] : (object) []],
            'TARGET_CPC' => ['targetCpc' => ['targetCpcMicros' => $this->micro($c['target_cpc'])]],
            'MAXIMIZE_CLICKS' => ['targetSpend' => ! empty($c['cpc_ceiling']) ? ['cpcBidCeilingMicros' => $this->micro($c['cpc_ceiling'])] : (object) []],
            'TARGET_IMPRESSION_SHARE' => ['targetImpressionShare' => [
                'location' => $c['is_location'] ?? 'ANYWHERE_ON_PAGE',
                'locationFractionMicros' => (int) round(((float) ($c['is_fraction'] ?? 50)) * 10000),
                'cpcBidCeilingMicros' => $this->micro($c['cpc_ceiling']),
            ]],
            // Enhanced CPC is gone for Search/Display; plain manual CPC.
            default => ['manualCpc' => (object) []],
        };
    }

    private function campaignCriteria(string $campaign, string $type, array $c, array $t): array
    {
        $out = [];
        if ($type === 'DEMAND_GEN') {
            return $out; // everything sits on the ad group / Audience
        }
        foreach ($t['locations'] ?? [] as $l) {
            $out[] = ['campaign' => $campaign, 'negative' => (bool) ($l['negative'] ?? false), 'location' => ['geoTargetConstant' => 'geoTargetConstants/' . $l['id']]];
        }
        foreach ($t['radius'] ?? [] as $r) {
            $out[] = ['campaign' => $campaign, 'proximity' => [
                'geoPoint' => ['latitudeInMicroDegrees' => (int) round($r['latitude'] * 1_000_000), 'longitudeInMicroDegrees' => (int) round($r['longitude'] * 1_000_000)],
                'radius' => (float) $r['radius'], 'radiusUnits' => $r['unit'] === 'MILES' ? 'MILES' : 'KILOMETERS',
            ]];
        }
        foreach ($t['languages'] ?? [] as $id) {
            $out[] = ['campaign' => $campaign, 'language' => ['languageConstant' => 'languageConstants/' . $id]];
        }
        $bids = $type !== 'PERFORMANCE_MAX';
        foreach ($t['schedule'] ?? [] as $s) {
            $out[] = array_filter(['campaign' => $campaign, 'adSchedule' => [
                'dayOfWeek' => $s['day'], 'startHour' => (int) $s['start_hour'], 'startMinute' => 'ZERO', 'endHour' => (int) $s['end_hour'], 'endMinute' => 'ZERO',
            ], 'bidModifier' => $bids && ! empty($s['bid_adjustment']) ? $this->modifier($s['bid_adjustment']) : null], fn ($v) => $v !== null);
        }
        foreach ($t['devices'] ?? [] as $device => $adjustment) {
            if ($adjustment !== null && $adjustment !== '' && (float) $adjustment !== 0.0) {
                // -100% (modifier 0) excludes the device.
                $out[] = ['campaign' => $campaign, 'device' => ['type' => $device], 'bidModifier' => $this->modifier($adjustment)];
            }
        }
        if ($type !== 'PERFORMANCE_MAX') {
            foreach ($t['negative_keywords'] ?? [] as $k) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'keyword' => ['text' => $k['text'], 'matchType' => $k['match_type']]];
            }
            // Audience exclusions: people on these lists never see the ads.
            foreach ($t['audience_exclusions'] ?? [] as $a) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'userList' => ['userList' => $a['id']]];
            }
            // IP exclusions (e.g. your own office) - Google only accepts these as negatives.
            foreach ($t['ip_exclusions'] ?? [] as $ip) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'ipBlock' => ['ipAddress' => $ip]];
            }
        }
        if ($type === 'DISPLAY') {
            foreach ($t['content_labels'] ?? [] as $label) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'contentLabel' => ['type' => $label]];
            }
            foreach ($t['excluded_placements'] ?? [] as $url) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'placement' => ['url' => $url]];
            }
            foreach ($t['app_categories'] ?? [] as $cat) {
                $out[] = ['campaign' => $campaign, 'negative' => true, 'mobileAppCategory' => ['mobileAppCategoryConstant' => 'mobileAppCategoryConstants/' . $cat['id']]];
            }
            // Operating systems and carriers narrow delivery to the chosen ones (campaign-level only).
            foreach ($t['operating_systems'] ?? [] as $os) {
                $out[] = ['campaign' => $campaign, 'operatingSystemVersion' => ['operatingSystemVersionConstant' => 'operatingSystemVersionConstants/' . $os['id']]];
            }
            foreach ($t['carriers'] ?? [] as $carrier) {
                $out[] = ['campaign' => $campaign, 'carrier' => ['carrierConstant' => 'carrierConstants/' . $carrier['id']]];
            }
        }

        return $out;
    }

    private function adGroupCriteria(string $adGroup, string $type, array $g, array $t): array
    {
        $out = [];
        foreach ($g['keywords'] ?? [] as $k) {
            // Display keywords are contextual - Google only accepts broad match there.
            $out[] = ['adGroup' => $adGroup, 'status' => 'ENABLED', 'keyword' => ['text' => $k['text'], 'matchType' => $type === 'DISPLAY' ? 'BROAD' : $k['match_type']]];
        }
        // Demographics: everything is targeted by default; the unticked groups are excluded.
        foreach (['ages' => 'ageRange', 'genders' => 'gender', 'parental' => 'parentalStatus', 'incomes' => 'incomeRange'] as $key => $field) {
            foreach ($t[$key] ?? [] as $value) {
                $out[] = ['adGroup' => $adGroup, 'negative' => true, $field => ['type' => $value]];
            }
        }
        foreach ($t['audiences'] ?? [] as $a) {
            $out[] = ['adGroup' => $adGroup, 'status' => 'ENABLED'] + match ($a['kind']) {
                'user_list' => ['userList' => ['userList' => $a['id']]],
                'user_interest' => ['userInterest' => ['userInterestCategory' => $a['id']]],
                // Life events / detailed demographics take the taxonomy ID, not the resource name.
                'life_event' => ['lifeEvent' => ['lifeEventId' => basename($a['id'])]],
                'detailed_demographic' => ['extendedDemographic' => ['extendedDemographicId' => basename($a['id'])]],
                default => ['customAudience' => ['customAudience' => $a['id']]],
            };
        }
        if ($type === 'DISPLAY') {
            foreach ($t['placements'] ?? [] as $url) {
                $out[] = ['adGroup' => $adGroup, 'status' => 'ENABLED', 'placement' => ['url' => $url]];
            }
            foreach ($t['topics'] ?? [] as $topic) {
                $out[] = ['adGroup' => $adGroup, 'status' => 'ENABLED', 'topic' => ['topicConstant' => 'topicConstants/' . $topic['id']]];
            }
            foreach ($t['excluded_topics'] ?? [] as $topic) {
                $out[] = ['adGroup' => $adGroup, 'negative' => true, 'topic' => ['topicConstant' => 'topicConstants/' . $topic['id']]];
            }
        }

        return $out;
    }

    private function searchAd(array $ad): array
    {
        $text = fn ($list) => array_map(fn ($v) => ['text' => $v], array_values($list));

        return ['finalUrls' => [$ad['final_url']], 'responsiveSearchAd' => array_filter([
            'headlines' => $text($ad['headlines']),
            'descriptions' => $text($ad['descriptions']),
            'path1' => $ad['path1'] ?? null,
            'path2' => ! empty($ad['path1']) ? ($ad['path2'] ?? null) : null,
        ])];
    }

    private function displayAd(array $ad, array $assets): array
    {
        $text = fn ($list) => array_map(fn ($v) => ['text' => $v], array_values($list));
        $img = fn ($slot) => array_map(fn ($r) => ['asset' => $r], $assets[$slot] ?? []);

        return ['finalUrls' => [$ad['final_url']], 'responsiveDisplayAd' => array_filter([
            'marketingImages' => $img('marketing'),
            'squareMarketingImages' => $img('square'),
            'logoImages' => $img('logo'),
            'headlines' => $text($ad['headlines']),
            'longHeadline' => ['text' => $ad['long_headlines'][0]],
            'descriptions' => $text($ad['descriptions']),
            'businessName' => $ad['business_name'],
        ])];
    }

    /** Text assets + asset group + asset links, in the same request as the campaign (required for Performance Max). */
    private function assetGroupOperations(string $campaign, string $assetGroup, array $input, array $assets): array
    {
        $ad = $input['ad'];
        $ops = [['assetGroupOperation' => ['create' => [
            'resourceName' => $assetGroup,
            'campaign' => $campaign,
            'name' => mb_substr(($input['ad_group']['name'] ?? '') ?: $input['campaign']['name'] . ' · ' . __('google_builder.defaults.asset_group'), 0, 255),
            'finalUrls' => [$ad['final_url']],
            'status' => 'ENABLED',
        ]]]];

        $temp = -10;
        $link = function (string $resource, string $field) use (&$ops, $assetGroup) {
            $ops[] = ['assetGroupAssetOperation' => ['create' => ['assetGroup' => $assetGroup, 'asset' => $resource, 'fieldType' => $field]]];
        };
        $textAsset = function (string $value, string $field) use (&$ops, &$temp, $link) {
            $resource = $this->api->resource('assets', $temp--);
            $ops[] = ['assetOperation' => ['create' => ['resourceName' => $resource, 'textAsset' => ['text' => $value]]]];
            $link($resource, $field);
        };

        foreach ($ad['headlines'] as $v) {
            $textAsset($v, 'HEADLINE');
        }
        foreach ($ad['long_headlines'] as $v) {
            $textAsset($v, 'LONG_HEADLINE');
        }
        foreach ($ad['descriptions'] as $v) {
            $textAsset($v, 'DESCRIPTION');
        }
        $textAsset($ad['business_name'], 'BUSINESS_NAME');
        foreach (['marketing' => 'MARKETING_IMAGE', 'square' => 'SQUARE_MARKETING_IMAGE', 'logo' => 'LOGO'] as $slot => $field) {
            foreach ($assets[$slot] ?? [] as $resource) {
                $link($resource, $field);
            }
        }

        // Signals: search themes and one audience signal steer Google's AI (they don't limit reach).
        foreach ($input['targeting']['search_themes'] ?? [] as $theme) {
            $ops[] = ['assetGroupSignalOperation' => ['create' => ['assetGroup' => $assetGroup, 'searchTheme' => ['text' => $theme]]]];
        }
        if (! empty($input['targeting']['audience_signal']['id'])) {
            $ops[] = ['assetGroupSignalOperation' => ['create' => ['assetGroup' => $assetGroup, 'audience' => ['audience' => $input['targeting']['audience_signal']['id']]]]];
        }

        // The asset group must be created before the links that reference it.
        $groups = array_filter($ops, fn ($o) => isset($o['assetOperation']));
        $rest = array_filter($ops, fn ($o) => ! isset($o['assetOperation']));

        return array_values(array_merge($groups, $rest));
    }

    /**
     * Demand Gen (YouTube): Audience (demographics + segments + exclusions) ->
     * audience-grouped ad group with channel controls -> location / language /
     * audience criteria -> YouTube video (+ CTA) assets -> video responsive ad.
     */
    private function demandGenOperations(string $campaign, string $adGroup, array $input, array $assets): array
    {
        $c = $input['campaign'];
        $t = $input['targeting'] ?? [];
        $ad = $input['ad'];
        $ops = [];

        $audience = $this->audienceBody($t);
        $audienceResource = $this->api->resource('audiences', -5);
        if ($audience) {
            $ops[] = ['audienceOperation' => ['create' => ['resourceName' => $audienceResource, 'name' => mb_substr($c['name'], 0, 200) . ' · ' . now()->format('Y-m-d H:i:s'), 'scope' => 'CUSTOMER'] + $audience]];
        }

        $channels = [];
        foreach (self::CHANNELS as $channel) {
            // Every field is sent explicitly (Google: "explicitly specify all fields").
            $channels[$channel] = in_array($channel, $t['channels'] ?? [], true);
        }
        $ops[] = ['adGroupOperation' => ['create' => [
            'resourceName' => $adGroup,
            'campaign' => $campaign,
            'name' => mb_substr(($input['ad_group']['name'] ?? '') ?: $c['name'] . ' · ' . __('google_builder.defaults.ad_group'), 0, 255),
            'status' => 'ENABLED',
            // No ad_group.type for Demand Gen; the settings below identify it.
            'audienceSetting' => ['useAudienceGrouped' => true],
            'demandGenAdGroupSettings' => ['channelControls' => ['selectedChannels' => $channels]],
            'optimizedTargetingEnabled' => (bool) ($t['optimized_targeting'] ?? false),
        ]]];

        foreach ($t['locations'] ?? [] as $l) {
            $ops[] = ['adGroupCriterionOperation' => ['create' => ['adGroup' => $adGroup, 'negative' => (bool) ($l['negative'] ?? false), 'location' => ['geoTargetConstant' => 'geoTargetConstants/' . $l['id']]]]];
        }
        foreach ($t['languages'] ?? [] as $id) {
            $ops[] = ['adGroupCriterionOperation' => ['create' => ['adGroup' => $adGroup, 'language' => ['languageConstant' => 'languageConstants/' . $id]]]];
        }
        if ($audience) {
            $ops[] = ['adGroupCriterionOperation' => ['create' => ['adGroup' => $adGroup, 'audience' => ['audience' => $audienceResource]]]];
        }

        $temp = -10;
        $videos = [];
        foreach ($ad['videos'] as $videoId) {
            $resource = $this->api->resource('assets', $temp--);
            $ops[] = ['assetOperation' => ['create' => ['resourceName' => $resource, 'name' => 'YouTube ' . $videoId . ' · ' . now()->format('YmdHis'), 'type' => 'YOUTUBE_VIDEO', 'youtubeVideoAsset' => ['youtubeVideoId' => $videoId]]]];
            $videos[] = ['asset' => $resource];
        }
        $ctas = [];
        if (! empty($ad['call_to_action'])) {
            $resource = $this->api->resource('assets', $temp--);
            $ops[] = ['assetOperation' => ['create' => ['resourceName' => $resource, 'callToActionAsset' => ['callToAction' => $ad['call_to_action']]]]];
            $ctas[] = ['asset' => $resource];
        }
        $text = fn ($list) => array_map(fn ($v) => ['text' => $v], array_values($list));

        $ops[] = ['adGroupAdOperation' => ['create' => ['adGroup' => $adGroup, 'status' => 'ENABLED', 'ad' => [
            'name' => mb_substr($c['name'], 0, 200),
            'finalUrls' => [$ad['final_url']],
            'demandGenVideoResponsiveAd' => array_filter([
                'videos' => $videos,
                'logoImages' => array_map(fn ($r) => ['asset' => $r], $assets['logo'] ?? []),
                'headlines' => $text($ad['headlines']),
                'longHeadlines' => $text($ad['long_headlines']),
                'descriptions' => $text($ad['descriptions']),
                'businessName' => ['text' => $ad['business_name']],
                'callToActions' => $ctas,
                'breadcrumb1' => $ad['breadcrumb1'] ?? null,
                'breadcrumb2' => ! empty($ad['breadcrumb1']) ? ($ad['breadcrumb2'] ?? null) : null,
            ]),
        ]]]];

        return $ops;
    }

    /**
     * Audience dimensions from the builder's targeting. Demographic groups are
     * stored as exclusions (everything targeted by default); an Audience lists
     * what's included, so a dimension is only sent when something is excluded.
     */
    public function audienceBody(array $t): ?array
    {
        $dimensions = [];
        $excludedAges = $t['ages'] ?? [];
        if ($excludedAges) {
            $dimensions[] = ['age' => [
                'ageRanges' => array_values(array_map(fn ($r) => array_filter(['minAge' => $r[0], 'maxAge' => $r[1]], fn ($v) => $v !== null), array_diff_key(self::AGE_SEGMENTS, array_flip($excludedAges)))),
                'includeUndetermined' => ! in_array('AGE_RANGE_UNDETERMINED', $excludedAges, true),
            ]];
        }
        foreach (['genders' => ['gender', 'genders', self::GENDERS, 'UNDETERMINED'], 'parental' => ['parentalStatus', 'parentalStatuses', self::PARENTAL, 'UNDETERMINED'], 'incomes' => ['householdIncome', 'incomeRanges', self::INCOMES, 'INCOME_RANGE_UNDETERMINED']] as $key => [$dim, $field, $all, $unknown]) {
            $excluded = $t[$key] ?? [];
            if ($excluded) {
                $dimensions[] = [$dim => [
                    $field => array_values(array_diff($all, $excluded, [$unknown])),
                    'includeUndetermined' => ! in_array($unknown, $excluded, true),
                ]];
            }
        }
        $segments = array_map(fn ($a) => match ($a['kind']) {
            'user_list' => ['userList' => ['userList' => $a['id']]],
            'user_interest' => ['userInterest' => ['userInterestCategory' => $a['id']]],
            'life_event' => ['lifeEvent' => ['lifeEvent' => $a['id']]],
            'detailed_demographic' => ['detailedDemographic' => ['detailedDemographic' => $a['id']]],
            default => ['customAudience' => ['customAudience' => $a['id']]],
        }, $t['audiences'] ?? []);
        if ($segments) {
            $dimensions[] = ['audienceSegments' => ['segments' => $segments]];
        }
        $exclusions = array_map(fn ($a) => ['userList' => ['userList' => $a['id']]], $t['audience_exclusions'] ?? []);

        if (! $dimensions && ! $exclusions) {
            return null;
        }

        return array_filter(['dimensions' => $dimensions, 'exclusionDimension' => $exclusions ? ['exclusions' => $exclusions] : null]);
    }

    /** Campaign conversion goals: only the chosen category/origin pairs stay biddable. */
    private function goalStep(AdBuilderRun $run, array $input): void
    {
        $goals = $input['campaign']['goals'] ?? null;
        if (! $goals || $run->result('goals.done')) {
            return;
        }

        $id = $run->result('campaign.id');
        $rows = $this->api->search('SELECT campaign_conversion_goal.resource_name, campaign_conversion_goal.category, campaign_conversion_goal.origin, campaign_conversion_goal.biddable FROM campaign_conversion_goal WHERE campaign.id = ' . (int) $id);
        $wanted = collect($goals)->filter(fn ($g) => $g['biddable'])->map(fn ($g) => $g['category'] . '~' . $g['origin'])->all();

        $ops = collect($rows)->map(fn ($r) => $r['campaignConversionGoal'])->map(function ($g) use ($wanted) {
            $biddable = in_array($g['category'] . '~' . $g['origin'], $wanted, true);

            return (bool) ($g['biddable'] ?? false) === $biddable ? null : ['update' => ['resourceName' => $g['resourceName'], 'biddable' => $biddable], 'updateMask' => 'biddable'];
        })->filter()->values()->all();

        if ($ops) {
            $this->api->post('customers/' . $this->api->customerId() . '/campaignConversionGoals:mutate', ['operations' => $ops]);
        }
        $run->remember('goals', ['done' => true, 'count' => count($wanted)]);
    }

    private function storeLocal(AdBuilderRun $run, array $input, string $campaignId, ?string $budget, ?string $adGroup, ?string $adResource, string $status): void
    {
        $c = $input['campaign'];
        $t = $input['targeting'] ?? [];
        $platform = $input['campaign_type'] === 'DEMAND_GEN' ? 'youtube' : 'google';
        $local = AdCampaign::updateOrCreate(['ad_campaign_id' => $campaignId, 'platform' => $platform], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $c['name'],
            'objective' => $input['goal'] ?? null, 'advertising_channel_type' => $input['campaign_type'],
            'budget_mode' => ($c['budget_type'] ?? 'daily') === 'lifetime' ? 'total' : 'daily', 'budget' => $c['budget'], 'daily_budget' => ($c['budget_type'] ?? 'daily') === 'lifetime' ? null : $c['budget'], 'budget_resource_id' => $budget,
            'bidding_strategy' => $c['bidding'], 'bidding_amount' => $c['target_cpa'] ?? $c['target_roas'] ?? $c['max_cpc'] ?? $c['cpc_ceiling'] ?? $c['target_cpc'] ?? null,
            'start_time' => $c['start_date'] ?? null, 'end_time' => $c['end_date'] ?? null, 'status' => $status === 'ACTIVE',
        ]);
        $run->remember('campaign.local', $local->id);

        if ($adGroup && $input['campaign_type'] !== 'PERFORMANCE_MAX') {
            $group = AdAdGroup::updateOrCreate(['ad_adgroup_id' => basename($adGroup), 'platform' => $platform], [
                'ad_campaign_id' => $local->id, 'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id,
                'name' => ($input['ad_group']['name'] ?? '') ?: $c['name'], 'status' => $status === 'ACTIVE', 'bid_strategy' => $c['bidding'],
                'bid_price' => $c['max_cpc'] ?? null, 'gender' => json_encode($t['genders'] ?? []), 'age_groups' => json_encode($t['ages'] ?? []),
                'languages' => json_encode($t['languages'] ?? []), 'location_ids' => json_encode(collect($t['locations'] ?? [])->pluck('id')->all()),
                'keywords' => json_encode($input['ad_group']['keywords'] ?? []), 'targeting_criteria' => json_encode($t),
            ]);
            if ($adResource) {
                $adType = ['SEARCH' => 'RESPONSIVE_SEARCH_AD', 'DEMAND_GEN' => 'DEMAND_GEN_VIDEO_RESPONSIVE_AD'][$input['campaign_type']] ?? 'RESPONSIVE_DISPLAY_AD';
                // The edit pages and GoogleAdService / YoutubeAdService::update()
                // read the ad copy from this creative row (same shape the
                // legacy forms stored).
                $creative = AdCreative::updateOrCreate(['ad_creative_id' => $adResource, 'platform' => $platform], [
                    'ad_adgroup_id' => $group->id, 'ad_campaign_id' => $local->id, 'social_account_id' => $this->api->account->id, 'user_id' => $run->user_id,
                    'name' => $c['name'], 'type' => $adType, 'url' => $input['ad']['final_url'], 'final_urls' => json_encode([$input['ad']['final_url']]),
                    'headlines' => json_encode($input['ad']['headlines']), 'descriptions' => json_encode($input['ad']['descriptions']),
                    'long_headlines' => json_encode($input['ad']['long_headlines'] ?? []), 'business_name' => $input['ad']['business_name'] ?? null,
                    'call_to_action' => $input['ad']['call_to_action'] ?? null,
                ]);
                Ad::updateOrCreate(['ad_id' => $adResource, 'platform' => $platform], [
                    'ad_adgroup_id' => $group->id, 'ad_campaign_id' => $local->id, 'ad_creative_id' => $creative->id, 'social_account_id' => $this->api->account->id, 'user_id' => $run->user_id,
                    'name' => $c['name'], 'type' => $adType,
                    'status' => $status === 'ACTIVE', 'final_urls' => json_encode([$input['ad']['final_url']]), 'headlines' => json_encode($input['ad']['headlines']),
                    'text' => implode("\n", $input['ad']['descriptions']),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function micro($amount): int
    {
        return (int) round(((float) $amount) * 1_000_000);
    }

    private function unmicro($micros): ?float
    {
        return $micros === null ? null : round(((int) $micros) / 1_000_000, 2);
    }

    /** A +/- percentage -> Google bid modifier (-100% = 0 = excluded). */
    private function modifier($percent): float
    {
        return round(max(0, 1 + ((float) $percent) / 100), 2);
    }
}
