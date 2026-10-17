<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\MediaAsset;
use App\Support\Ads\SnapchatAdsApi;
use App\Support\Ads\SnapchatApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Snapchat campaign builder (ads/snapchat/campaigns/create): campaign (new
 * or existing) -> ad squad with targeting -> media -> creative(s) -> ad(s),
 * through the Snapchat Marketing API v1.
 *
 * - Lookups (ad account, pixels, Public Profiles, campaigns, audience
 *   segments and every targeting option) are read live from Snap.
 * - Everything is created PAUSED and only switched on (JSON Patch) once the
 *   whole chain exists, so a half-finished submission never spends.
 * - submit() is idempotent per key (AdBuilderRun, platform "snapchat"):
 *   each Snap object is recorded the moment it exists, so a retry continues
 *   from the failed step instead of creating duplicates.
 * - Objectives and their optimization goals follow the Campaigns reference
 *   ("objective_v2_type" business-logic table); creative / ad types and
 *   calls to action follow the Creatives and Ads references
 *   (developers.snap.com/api/marketing-api/Ads-API).
 */
class SnapchatCampaignBuilder
{
    /**
     * objective_v2_type => destinations => optimization goals. Lens, Story,
     * Collection, Call/Text and Lead Form ads are not offered (they need
     * assets this builder can't create), so their goals aren't either.
     */
    public const OBJECTIVES = [
        'AWARENESS_AND_ENGAGEMENT' => ['icon' => 'bx-broadcast', 'destinations' => [
            'none' => ['IMPRESSIONS', 'VIDEO_VIEWS', 'VIDEO_VIEWS_15_SEC'],
            'web' => ['IMPRESSIONS', 'SWIPES', 'VIDEO_VIEWS', 'VIDEO_VIEWS_15_SEC'],
        ]],
        'TRAFFIC' => ['icon' => 'bx-mouse-alt', 'destinations' => [
            'web' => ['SWIPES', 'LANDING_PAGE_VIEW', 'PIXEL_PAGE_VIEW'],
        ]],
        'SALES' => ['icon' => 'bx-cart', 'destinations' => [
            'web' => ['PIXEL_PURCHASE', 'PIXEL_ADD_TO_CART', 'PIXEL_SIGNUP', 'PIXEL_PAGE_VIEW', 'LANDING_PAGE_VIEW', 'SWIPES'],
        ]],
        'LEADS' => ['icon' => 'bx-id-card', 'destinations' => [
            'web' => ['PIXEL_SIGNUP', 'LANDING_PAGE_VIEW', 'SWIPES'],
        ]],
        'APP_PROMOTION' => ['icon' => 'bx-mobile-alt', 'destinations' => [
            'app_install' => ['APP_INSTALLS', 'SWIPES', 'IMPRESSIONS'],
            'deep_link' => ['SWIPES'],
        ]],
    ];

    /** Destination => creative type, ad type and (APP_PROMOTION) promotion_type (Ads reference: ad type <-> creative type). */
    public const DESTINATIONS = [
        'none' => ['creative' => 'SNAP_AD', 'ad' => 'SNAP_AD'],
        'web' => ['creative' => 'WEB_VIEW', 'ad' => 'REMOTE_WEBPAGE'],
        'app_install' => ['creative' => 'APP_INSTALL', 'ad' => 'APP_INSTALL', 'promotion' => 'APP_INSTALL'],
        'deep_link' => ['creative' => 'DEEP_LINK', 'ad' => 'DEEP_LINK', 'promotion' => 'APP_REENGAGEMENT'],
    ];

    /** Calls to action per creative attachment (Creatives reference). A plain Snap Ad has none. */
    public const CTAS = [
        'web' => ['SHOP_NOW', 'SIGN_UP', 'APPLY_NOW', 'BOOK_NOW', 'ORDER_NOW', 'GET_NOW', 'MORE', 'VIEW', 'WATCH', 'READ', 'LISTEN', 'PLAY', 'PLAY_GAME', 'DOWNLOAD', 'TRY', 'DONATE', 'RESPOND', 'BUY_TICKETS', 'SHOWTIMES', 'VIEW_MENU', 'PRE_REGISTER', 'VOTE', 'SHOW'],
        'app_install' => ['INSTALL_NOW', 'DOWNLOAD', 'GET_NOW', 'PLAY', 'PLAY_GAME', 'SHOP_NOW', 'ORDER_NOW', 'BOOK_NOW', 'SIGN_UP', 'TRY', 'USE_APP', 'WATCH', 'DONATE', 'VOTE', 'DIRECTIONS'],
        'deep_link' => ['OPEN_APP', 'USE_APP', 'SHOP_NOW', 'SIGN_UP', 'PLAY', 'PLAY_GAME', 'TRY', 'WATCH', 'MORE', 'VIEW_PROFILE', 'DOWNLOAD', 'DONATE', 'VOTE', 'DIRECTIONS', 'PRE_REGISTER'],
    ];

    /** Goals that optimize for Snap Pixel events and therefore need a pixel. */
    public const PIXEL_GOALS = ['PIXEL_PURCHASE', 'PIXEL_ADD_TO_CART', 'PIXEL_SIGNUP', 'PIXEL_PAGE_VIEW', 'LANDING_PAGE_VIEW'];

    public const VIDEO_GOALS = ['VIDEO_VIEWS', 'VIDEO_VIEWS_15_SEC'];

    /** frequency_cap_type accepts these goals only (Ad Squads: cap_and_exclusion_config). */
    public const FREQUENCY_GOALS = ['IMPRESSIONS', 'SWIPES', 'VIDEO_VIEWS', 'VIDEO_VIEWS_15_SEC'];

    public const BID_STRATEGIES = ['AUTO_BID', 'LOWEST_COST_WITH_MAX_BID', 'TARGET_COST'];

    /** Snapchat positions offered for custom placement (placement_v2.snapchat_positions). */
    public const POSITIONS = ['INTERSTITIAL_USER', 'INTERSTITIAL_CONTENT', 'INTERSTITIAL_SPOTLIGHT', 'INSTREAM', 'FEED', 'CHAT_FEED'];

    /** Content categories for in-stream inclusion / exclusion (placement_v2.inclusion|exclusion.content_types). */
    public const CONTENT_TYPES = ['NEWS', 'ENTERTAINMENT', 'SCIENCE_TECHNOLOGY', 'BEAUTY_FASHION', 'MENS_LIFESTYLE', 'WOMENS_LIFESTYLE', 'GAMING', 'GENERAL_LIFESTYLE', 'FOOD', 'SPORTS', 'YOUNG_BOLD'];

    public const CONVERSION_WINDOWS = ['SWIPE_28DAY_VIEW_1DAY', 'SWIPE_7DAY'];

    public const GENDERS = ['ALL', 'MALE', 'FEMALE'];

    public const FREQUENCY_UNITS = ['HOURS', 'DAYS'];

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** min_age 13-35, max_age 13-55 (Targeting reference). */
    public const MIN_AGE = [13, 35];

    public const MAX_AGE = [13, 55];

    /** Minimum budgets, in account currency units (5,000,000 / 20,000,000 micro). */
    public const MIN_SQUAD_DAILY = 5;

    public const MIN_CAMPAIGN_CAP = 20;

    /** Top Snap videos above this need Snap's chunked upload, which isn't used here. */
    public const MAX_VIDEO_BYTES = 32 * 1024 * 1024;

    public const MAX_ADS = 5;

    private array $memo = [];

    public function __construct(private SnapchatAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = SnapchatAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function api(): SnapchatAdsApi
    {
        return $this->api;
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public function bootstrap(): array
    {
        $info = $this->info();
        [$profiles, $profilesError] = $this->profilesWithError();

        return [
            'account' => [
                'id' => $this->api->adAccountId(),
                'local_id' => $this->api->account->id,
                'name' => $info['name'] ?? $this->api->account->name,
                'currency' => $info['currency'] ?? $this->api->account->metadata['currency'] ?? 'USD',
                'timezone' => $this->timezone(),
                'status' => $info['status'] ?? null,
                'active' => ($info['status'] ?? 'ACTIVE') === 'ACTIVE',
            ],
            'pixels' => $this->pixels(),
            'profiles' => $profiles,
            'profiles_error' => $profilesError,
            'objectives' => self::OBJECTIVES,
            'options' => [
                'destinations' => self::DESTINATIONS,
                'ctas' => self::CTAS,
                'pixel_goals' => self::PIXEL_GOALS,
                'video_goals' => self::VIDEO_GOALS,
                'frequency_goals' => self::FREQUENCY_GOALS,
                'bid_strategies' => self::BID_STRATEGIES,
                'positions' => self::POSITIONS,
                'content_types' => self::CONTENT_TYPES,
                'conversion_windows' => self::CONVERSION_WINDOWS,
                'genders' => self::GENDERS,
                'frequency_units' => self::FREQUENCY_UNITS,
                'days' => self::DAYS,
                'min_age' => self::MIN_AGE,
                'max_age' => self::MAX_AGE,
                'min_squad_daily' => self::MIN_SQUAD_DAILY,
                'min_campaign_cap' => self::MIN_CAMPAIGN_CAP,
                'max_ads' => self::MAX_ADS,
                'max_video_mb' => self::MAX_VIDEO_BYTES / 1048576,
            ],
        ];
    }

    /** GET adaccounts/{id}: name, currency, time zone, status. */
    private function info(): array
    {
        return $this->memo['info'] ??= Cache::remember($this->cacheKey('info'), 600, fn () => $this->api->get('adaccounts/' . $this->api->adAccountId())['adaccounts'][0]['adaccount'] ?? []);
    }

    public function pixels(): array
    {
        return $this->memo['pixels'] ??= Cache::remember($this->cacheKey('pixels'), 600, fn () => collect($this->api->all('adaccounts/' . $this->api->adAccountId() . '/pixels', 'pixels', 'pixel'))
            ->map(fn ($p) => ['id' => (string) $p['id'], 'name' => $p['name'] ?? $p['id'], 'status' => $p['status'] ?? null])
            ->values()->all());
    }

    /** The organization's Public Profiles (creatives must carry one). */
    public function profiles(): array
    {
        return $this->profilesWithError()[0];
    }

    /** @return array{0: array, 1: ?string} profiles, and why they couldn't be listed */
    private function profilesWithError(): array
    {
        if (isset($this->memo['profiles'])) {
            return $this->memo['profiles'];
        }
        // 1. Saved at connect time (SnapchatAdService::storePublicProfiles).
        $saved = array_values(array_filter($this->api->account->metadata['public_profiles'] ?? [], fn ($p) => ! empty($p['id'])));
        if ($saved) {
            return $this->memo['profiles'] = [$saved, null];
        }

        $error = null;
        $profiles = Cache::remember($this->cacheKey('profiles'), 600, function () use (&$error) {
            $present = fn ($p) => [
                'id' => (string) $p['id'],
                'name' => $p['display_name'] ?? $p['id'],
                'logo' => $p['logo_urls']['discover_feed_logo_url'] ?? $p['logo_urls']['original_logo_url'] ?? null,
            ];

            // 1. Public Profiles linked to this ad account (sharing policies).
            $linked = [];
            try {
                $linked = collect($this->api->business('adaccounts/' . $this->api->adAccountId() . '/sharing_policies', ['shared_resource_types' => 'public_profiles'])['sharing_policies'] ?? [])
                    ->filter(fn ($s) => ($s['source']['resource_type'] ?? null) === 'public_profiles')
                    ->pluck('source.resource_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            } catch (SnapchatApiException $e) {
                if ($e->isAuthError()) {
                    throw $e;
                }
                $error = $e->getMessage();
            }

            // 2. The organization's profiles: names/logos, and the fallback list.
            $org = [];
            if ($orgId = $this->api->organizationId()) {
                try {
                    $org = collect($this->api->business('organizations/' . $orgId . '/public_profiles', ['limit' => 50])['public_profiles'] ?? [])
                        ->pluck('public_profile')->filter()->map($present)->keyBy('id')->all();
                } catch (SnapchatApiException $e) {
                    if ($e->isAuthError()) {
                        throw $e;
                    }
                    $error ??= $e->getMessage();
                }
            }

            if (! $linked && ! $org) {
                // 3. Without Profile API access: profiles the account's own Snap ads already use.
                try {
                    $used = collect($this->api->all('adaccounts/' . $this->api->adAccountId() . '/creatives', 'creatives', 'creative', [], 3))
                        ->pluck('profile_properties.profile_id')->filter()->unique()->values();
                    if ($used->isNotEmpty()) {
                        $error = null;

                        return $used->map(fn ($id) => ['id' => (string) $id, 'name' => $this->api->account->name ?: (string) $id, 'logo' => null])->all();
                    }
                } catch (SnapchatApiException $e) {
                    if ($e->isAuthError()) {
                        throw $e;
                    }
                }
                if ($error && str_contains($error, '403')) {
                    $error = __('snapchat_builder.server.profile_scope');
                }

                return [];
            }
            if (! $linked) {
                return array_values($org);
            }

            return collect($linked)->map(function ($id) use ($org, $present) {
                if (isset($org[$id])) {
                    return $org[$id];
                }
                try {
                    $p = $this->api->business('public_profiles/' . $id)['public_profiles'][0]['public_profile'] ?? null;

                    return $p ? $present($p) : ['id' => $id, 'name' => $id, 'logo' => null];
                } catch (SnapchatApiException) {
                    return ['id' => $id, 'name' => $id, 'logo' => null];
                }
            })->values()->all();
        });

        if (! $profiles) {
            Cache::forget($this->cacheKey('profiles'));
        }

        return $this->memo['profiles'] = [$profiles, $profiles ? null : ($error ?? ($this->api->organizationId() ? null : __('snapchat_builder.server.no_organization')))];
    }

    /** Campaigns of this ad account, with their objective (a campaign has one). */
    public function campaigns(): array
    {
        return $this->memo['campaigns'] ??= collect($this->api->all('adaccounts/' . $this->api->adAccountId() . '/campaigns', 'campaigns', 'campaign', [], 5))
            ->reject(fn ($c) => $c['deleted'] ?? false)
            ->map(fn ($c) => [
                'id' => (string) $c['id'],
                'name' => $c['name'] ?? $c['id'],
                'status' => $c['status'] ?? null,
                'objective' => $c['objective_v2_properties']['objective_v2_type'] ?? null,
                'promotion_type' => $c['objective_v2_properties']['promotion_type'] ?? null,
                'daily_budget' => isset($c['daily_budget_micro']) ? $c['daily_budget_micro'] / 1e6 : null,
                'lifetime_cap' => isset($c['lifetime_spend_cap_micro']) ? $c['lifetime_spend_cap_micro'] / 1e6 : null,
                'buy_model' => $c['buy_model'] ?? 'AUCTION',
                'start_time' => $c['start_time'] ?? null,
                'end_time' => $c['end_time'] ?? null,
            ])
            ->map(fn ($c) => $c + ['supported' => $c['buy_model'] === 'AUCTION' && isset(self::OBJECTIVES[$c['objective']]) && ! ($c['end_time'] && Carbon::parse($c['end_time'])->isPast())])
            ->sortByDesc(fn ($c) => [$c['supported'], $c['status'] === 'ACTIVE'])->values()->all();
    }

    /** Audience segments: customer lists, pixel (website) audiences, app, engagement, lookalikes. */
    public function audiences(): array
    {
        return $this->memo['audiences'] ??= collect($this->api->all('adaccounts/' . $this->api->adAccountId() . '/segments', 'segments', 'segment', [], 5))
            ->reject(fn ($s) => ($s['status'] ?? 'ACTIVE') !== 'ACTIVE')
            ->map(fn ($s) => [
                'id' => (string) $s['id'],
                'name' => $s['name'] ?? $s['id'],
                'type' => $s['source_type'] ?? null,
                'size' => $s['approximate_number_users'] ?? null,
                'targetable' => $s['targetable_status'] ?? null,
                'usable' => ($s['targetable_status'] ?? 'READY') === 'READY',
            ])->sortByDesc('usable')->values()->all();
    }

    public function countries(): array
    {
        return Cache::remember('snapchat-builder:countries', 86400, fn () => collect($this->api->all('targeting/geo/country', 'targeting_dimensions', 'country'))
            ->map(fn ($c) => $c['country'] ?? $c)
            ->filter(fn ($c) => ! empty($c['code2'] ?? $c['code'] ?? null))
            ->map(fn ($c) => ['id' => strtolower($c['code2'] ?? $c['code']), 'name' => ucwords((string) ($c['name'] ?? $c['code']))])
            ->unique('id')->sortBy('name')->values()->all());
    }

    public function regions(string $countryCode): array
    {
        $cc = strtolower($countryCode);

        return Cache::remember('snapchat-builder:regions:' . $cc, 86400, fn () => collect($this->api->all('targeting/geo/' . $cc . '/region', 'targeting_dimensions', 'region'))
            ->map(fn ($r) => $r['region'] ?? $r)
            ->filter(fn ($r) => isset($r['id']))
            ->map(fn ($r) => ['id' => (string) $r['id'], 'name' => ucwords((string) ($r['name'] ?? $r['code'] ?? $r['id'])), 'country' => $cc])
            ->sortBy('name')->values()->all());
    }

    /** Extra targeting lists, by type: [path, item key, country-scoped?, query]. */
    public const OPTION_TYPES = [
        'metros' => ['targeting/geo/{cc}/metro', 'metro', true, []],
        'carriers' => ['targeting/device/carrier', 'carrier', false, []],
        'device_makes' => ['targeting/device/marketing_name', 'marketing_name', false, []],
        'visitation' => ['targeting/v1/interests/vac', 'vac', true, ['country_code' => '{cc}']],
        'nielsen' => ['targeting/interests/nln', 'nln', false, ['limit' => 1000]],
        'location_categories' => ['targeting/location/categories_loi', 'categories_loi', false, []],
    ];

    /** One of OPTION_TYPES, normalized to {id, name, path?, country?}. */
    public function optionList(string $type, ?string $countryCode = null): array
    {
        [$path, $item, $scoped, $query] = self::OPTION_TYPES[$type];
        $cc = strtolower((string) $countryCode);
        if ($scoped && strlen($cc) !== 2) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.country_needed'));
        }
        $fill = fn ($v) => str_replace('{cc}', $cc, $v);

        return Cache::remember('snapchat-builder:opt:' . $type . ':' . ($scoped ? $cc : 'all'), 86400, fn () => collect($this->api->all($fill($path), 'targeting_dimensions', $item, array_map($fill, $query), 20))
            ->map(fn ($o) => is_array($o[$item] ?? null) ? $o[$item] : $o)
            ->filter(fn ($o) => isset($o['id']))
            ->map(fn ($o) => array_filter([
                'id' => (string) $o['id'],
                'name' => ucwords((string) ($o['name'] ?? $o['id'])),
                'path' => isset($o['path']) ? trim((string) $o['path'], '/') : null,
                'country' => $scoped ? $cc : (isset($o['valid_country']) ? strtolower((string) $o['valid_country']) : null),
            ], fn ($v) => $v !== null && $v !== ''))
            ->unique('id')->values()->all());
    }

    public function languages(): array
    {
        return Cache::remember('snapchat-builder:languages', 86400, fn () => $this->options('targeting/demographics/languages', 'languages'));
    }

    public function advancedDemographics(): array
    {
        return Cache::remember('snapchat-builder:advanced_demographics', 86400, fn () => $this->options('targeting/demographics/advanced_demographics', 'advanced_demographics'));
    }

    /** Snap Lifestyle Categories, per country. */
    public function interests(string $countryCode): array
    {
        $cc = strtolower($countryCode);

        return Cache::remember('snapchat-builder:scls:' . $cc, 86400, fn () => collect($this->api->all('targeting/v1/interests/scls', 'targeting_dimensions', 'scls', ['country_code' => $cc]))
            ->map(fn ($i) => ['id' => (string) $i['id'], 'name' => $i['name'] ?? $i['id'], 'path' => trim((string) ($i['path'] ?? ''), '/')])
            ->unique('id')->sortBy('path')->values()->all());
    }

    public function osVersions(string $os): array
    {
        return Cache::remember('snapchat-builder:os_versions:' . $os, 86400, fn () => collect($this->api->all('targeting/device/' . $os . '/os_version', 'targeting_dimensions', 'os_version'))
            ->pluck('name')->filter()->unique()
            ->sort(fn ($a, $b) => version_compare((string) $a, (string) $b))->values()->all());
    }

    public function connectionTypes(): array
    {
        return Cache::remember('snapchat-builder:connection_types', 86400, fn () => $this->options('targeting/device/connection_type', 'connection_type'));
    }

    /** Estimated reach for the current ad squad spec (audience_size_v2). */
    public function estimate(array $targeting, array $squad): array
    {
        $spec = $this->squadSpec($squad, $targeting) + ['name' => 'Estimate', 'status' => 'PAUSED', 'type' => 'SNAP_ADS'];
        $size = $this->api->post('adaccounts/' . $this->api->adAccountId() . '/audience_size_v2', $spec)['audience_size'] ?? null;

        return ['available' => (bool) $size, 'min' => $size['audience_size_minimum'] ?? null, 'max' => $size['audience_size_maximum'] ?? null];
    }

    /** Suggested bid range for the goal and targeting (bid_estimate by targeting spec). */
    public function bidEstimate(array $targeting, string $goal): array
    {
        $estimate = $this->api->post('adaccounts/' . $this->api->adAccountId() . '/bid_estimate', ['optimization_goal' => $goal, 'targeting' => $this->targetingSpec($targeting)])['bid_estimate'] ?? null;

        return [
            'available' => (bool) $estimate,
            'min' => isset($estimate['bid_estimate_minimum']) ? $estimate['bid_estimate_minimum'] / 1e6 : null,
            'max' => isset($estimate['bid_estimate_maximum']) ? $estimate['bid_estimate_maximum'] / 1e6 : null,
        ];
    }

    private function options(string $path, string $item): array
    {
        return collect($this->api->all($path, 'targeting_dimensions', $item))
            ->map(fn ($o) => ['id' => (string) $o['id'], 'name' => ucfirst((string) ($o['name'] ?? $o['id']))])
            ->unique('id')->values()->all();
    }

    private function cacheKey(string $name): string
    {
        return 'snapchat-builder:' . $this->api->account->id . ':' . $name;
    }

    // ------------------------------------------------------------------
    // Targeting & ad squad spec
    // ------------------------------------------------------------------

    /**
     * The Snap targeting spec (expanded format). Within one object every
     * dimension ANDs, array items OR; EXCLUDE entries are applied last.
     */
    public function targetingSpec(array $t): array
    {
        $spec = [];
        $regions = collect($t['regions'] ?? [])->groupBy('country');
        $excludedRegions = collect($t['excluded_regions'] ?? [])->groupBy('country');
        $postal = collect($t['postal_codes'] ?? [])->groupBy('country');

        foreach (array_values(array_unique($t['countries'] ?? [])) as $cc) {
            $narrowed = false;
            if ($regions->has($cc)) {
                $spec['geos'][] = ['country_code' => $cc, 'region_id' => $regions[$cc]->pluck('id')->unique()->values()->all(), 'operation' => 'INCLUDE'];
                $narrowed = true;
            }
            if (collect($t['metros'] ?? [])->where('country', $cc)->isNotEmpty()) {
                $narrowed = true;
            }
            if ($postal->has($cc)) {
                $spec['geos'][] = ['country_code' => $cc, 'postal_code' => $postal[$cc]->pluck('code')->unique()->values()->all(), 'operation' => 'INCLUDE'];
                $narrowed = true;
            }
            if (! $narrowed) {
                $spec['geos'][] = ['country_code' => $cc, 'operation' => 'INCLUDE'];
            }
            $metros = collect($t['metros'] ?? [])->where('country', $cc);
            if ($metros->isNotEmpty()) {
                $spec['geos'][] = ['country_code' => $cc, 'metro_id' => $metros->pluck('id')->unique()->values()->all(), 'operation' => 'INCLUDE'];
            }
            $excludedMetros = collect($t['excluded_metros'] ?? [])->where('country', $cc);
            if ($excludedMetros->isNotEmpty()) {
                $spec['geos'][] = ['country_code' => $cc, 'metro_id' => $excludedMetros->pluck('id')->unique()->values()->all(), 'operation' => 'EXCLUDE'];
            }
            if ($excludedRegions->has($cc)) {
                $spec['geos'][] = ['country_code' => $cc, 'region_id' => $excludedRegions[$cc]->pluck('id')->unique()->values()->all(), 'operation' => 'EXCLUDE'];
            }
        }

        $demo = array_filter([
            'gender' => in_array($t['gender'] ?? 'ALL', ['MALE', 'FEMALE'], true) ? $t['gender'] : null,
            'min_age' => ! empty($t['min_age']) && (int) $t['min_age'] > self::MIN_AGE[0] ? (string) (int) $t['min_age'] : null,
            'max_age' => ! empty($t['max_age']) ? (string) (int) $t['max_age'] : null,
            'languages' => array_values(array_unique($t['languages'] ?? [])) ?: null,
            'advanced_demographics' => array_values(array_unique($t['advanced_demographics'] ?? [])) ?: null,
        ], fn ($v) => $v !== null);
        if ($demo) {
            $spec['demographics'] = [$demo + ['operation' => 'INCLUDE']];
        }

        // SLC, Snap visitation (VAC) and Nielsen (NLN) categories; only SLC can be excluded.
        $interests = array_values(array_unique(array_merge($t['interests'] ?? [], $t['visitation'] ?? [], $t['nielsen'] ?? [])));
        if ($interests) {
            $spec['interests'][] = ['category_id' => $interests, 'operation' => 'INCLUDE'];
        }
        if (! empty($t['excluded_interests'])) {
            $spec['interests'][] = ['category_id' => array_values(array_unique($t['excluded_interests'])), 'operation' => 'EXCLUDE'];
        }

        // One device entry per OS (entries OR); carrier, make and connection AND within each.
        $shared = array_filter([
            'connection_type' => $t['connection_type'] ?? null,
            'carrier_id' => array_values(array_unique($t['carriers'] ?? [])) ?: null,
            'marketing_name' => array_values(array_unique($t['device_makes'] ?? [])) ?: null,
        ], fn ($v) => $v !== null && $v !== '');
        foreach (array_values(array_unique($t['os_types'] ?? [])) as $os) {
            $versions = $t['os_versions'][$os] ?? [];
            $spec['devices'][] = array_filter([
                'os_type' => $os,
                'os_version_min' => $versions['min'] ?? null,
                'os_version_max' => $versions['max'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') + $shared + ['operation' => 'INCLUDE'];
        }
        if (empty($spec['devices']) && $shared) {
            $spec['devices'][] = $shared + ['operation' => 'INCLUDE'];
        }

        if (! empty($t['audiences'])) {
            $spec['segments'][] = ['segment_id' => array_values(array_unique($t['audiences'])), 'operation' => 'INCLUDE'];
        }
        if (! empty($t['excluded_audiences'])) {
            $spec['segments'][] = ['segment_id' => array_values(array_unique($t['excluded_audiences'])), 'operation' => 'EXCLUDE'];
        }

        if (! empty($t['circles'])) {
            $spec['locations'][] = ['circles' => array_map(fn ($c) => [
                'latitude' => (float) $c['latitude'], 'longitude' => (float) $c['longitude'], 'radius' => (float) $c['radius'], 'unit' => 'KILOMETERS',
            ], $t['circles']), 'operation' => 'INCLUDE'];
        }

        if (! empty($t['location_categories'])) {
            $spec['locations'][] = ['categories_loi' => array_values(array_unique($t['location_categories'])), 'operation' => 'INCLUDE'];
        }

        $spec['regulated_content'] = (bool) ($t['regulated_content'] ?? false);

        $interestExp = (bool) ($t['interest_expansion'] ?? false);
        $audienceExp = (bool) ($t['audience_expansion'] ?? false);
        $spec['enable_targeting_expansion'] = $interestExp || $audienceExp;
        $spec['auto_expansion_options'] = [
            'interest_expansion_option' => ['enabled' => $interestExp],
            'custom_audience_expansion_option' => ['enabled' => $audienceExp],
        ];

        return $spec;
    }

    /** Placement, bidding, budget and goal fields of the ad squad (shared with the estimate). */
    private function squadSpec(array $sq, array $targeting): array
    {
        $bid = $sq['bid_strategy'] ?? 'AUTO_BID';
        $lifetime = ($sq['budget_type'] ?? 'daily') === 'lifetime';
        $budget = $this->micro($sq['budget'] ?? null) ?? self::MIN_SQUAD_DAILY * 1_000_000;

        return array_filter([
            'targeting' => $this->targetingSpec($targeting),
            'placement_v2' => $this->placement($sq),
            'billing_event' => 'IMPRESSION',
            'optimization_goal' => $sq['goal'] ?? null,
            'bid_strategy' => $bid,
            'bid_micro' => $bid !== 'AUTO_BID' ? $this->micro($sq['bid_amount'] ?? null) : null,
            'daily_budget_micro' => $lifetime ? null : $budget,
            'lifetime_budget_micro' => $lifetime ? $budget : null,
            'delivery_constraint' => $lifetime ? 'LIFETIME_BUDGET' : 'DAILY_BUDGET',
        ], fn ($v) => $v !== null);
    }

    private function placement(array $sq): array
    {
        if (($sq['placement_mode'] ?? 'auto') !== 'custom') {
            return ['config' => 'AUTOMATIC'];
        }
        $positions = array_values(array_intersect(self::POSITIONS, $sq['positions'] ?? []));
        $placement = ['config' => 'CUSTOM', 'platforms' => ['SNAPCHAT'], 'snapchat_positions' => $positions];
        $content = array_values(array_intersect(self::CONTENT_TYPES, $sq['content_types'] ?? []));
        if ($content && in_array('INSTREAM', $positions, true)) {
            $placement[($sq['content_mode'] ?? 'exclude') === 'include' ? 'inclusion' : 'exclusion'] = ['content_types' => $content];
        }

        return $placement;
    }

    public function adSquadParams(array $input, string $campaignId): array
    {
        $sq = $input['adsquad'];
        $goal = $sq['goal'];
        $lifetime = ($sq['budget_type'] ?? 'daily') === 'lifetime';
        $schedule = $lifetime && ! empty($sq['schedule']) ? $this->schedule($sq['schedule']) : null;

        return array_filter($this->squadSpec($sq, $sq['targeting'] ?? []) + [
            'campaign_id' => $campaignId,
            'name' => mb_substr(($sq['name'] ?? null) ?: __('snapchat_builder.defaults.adsquad', ['date' => now()->format('Y-m-d')]), 0, 375),
            'type' => 'SNAP_ADS',
            'status' => 'PAUSED',
            'start_time' => $this->utc($sq['start_time'] ?? null) ?? now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_time' => $this->utc($sq['end_time'] ?? null),
            'pacing_type' => ($sq['bid_strategy'] ?? '') === 'LOWEST_COST_WITH_MAX_BID' && ($sq['pacing'] ?? 'STANDARD') === 'ACCELERATED' ? 'ACCELERATED' : null,
            'pixel_id' => $sq['pixel_id'] ?? null,
            'conversion_window' => in_array($goal, self::PIXEL_GOALS, true) ? ($sq['conversion_window'] ?? null) : null,
            'cap_and_exclusion_config' => in_array($goal, self::FREQUENCY_GOALS, true) && ! empty($sq['frequency_cap']) ? ['frequency_cap_config' => [[
                'frequency_cap_count' => (int) $sq['frequency_cap'],
                'frequency_cap_type' => $goal,
                'time_interval' => (int) ($sq['frequency_interval'] ?? 1),
                'frequency_cap_interval' => $sq['frequency_unit'] ?? 'DAYS',
            ]]] : null,
            'ad_scheduling_config' => $schedule ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Day-parting: day => hours (0-23) -> ad_scheduling_config. */
    private function schedule(array $days): array
    {
        $out = [];
        foreach (self::DAYS as $day) {
            $hours = array_values(array_unique(array_filter(array_map('intval', $days[$day] ?? []), fn ($h) => $h >= 0 && $h <= 23)));
            sort($hours);
            if ($hours) {
                $out[$day] = ['hour_of_day' => $hours];
            }
        }

        return $out;
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
            return ['success' => false, 'status' => 409, 'message' => __('snapchat_builder.server.in_progress')];
        }

        try {
            $run = AdBuilderRun::firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => $key],
                ['social_account_id' => $this->api->account->id, 'platform' => 'snapchat', 'payload_hash' => $hash, 'status' => 'running', 'results' => []]
            );

            if ($run->platform !== 'snapchat' || (int) $run->social_account_id !== (int) $this->api->account->id) {
                return ['success' => false, 'status' => 409, 'message' => __('snapchat_builder.server.key_reused')];
            }
            if ($run->status === 'completed') {
                return $this->completedResponse($run, true);
            }
            $run->forceFill(['payload_hash' => $hash, 'status' => 'running', 'failed_step' => null, 'error' => null])->save();
            $step = 'validation';

            try {
                $refs = $this->validateSelection($input);
                $step = 'campaign';
                $campaign = $this->campaignStep($run, $input);
                $step = 'adsquad';
                $squad = $this->adSquadStep($run, $input, $campaign);
                $step = 'media';
                $media = $this->mediaStep($run, $refs, $campaign);
                $step = 'creative';
                $creatives = $this->creativeStep($run, $input, $campaign, $squad, $refs, $media);
                $step = 'ad';
                $this->adStep($run, $input, $campaign, $squad, $creatives);
                $step = 'activate';
                $this->activateStep($run, $input, $campaign, $squad);
            } catch (SnapchatApiException|\InvalidArgumentException $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                Log::warning('Snapchat campaign builder step failed.', ['run' => $run->id, 'step' => $step, 'error' => $e->getMessage(), 'code' => $e instanceof SnapchatApiException ? $e->errorCode : null]);

                return ['success' => false, 'status' => 422, 'step' => $step, 'message' => $e->getMessage(), 'created' => $this->createdSummary($run), 'retryable' => true, 'reconnect' => $e instanceof SnapchatApiException && $e->isAuthError()];
            } catch (\Throwable $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                report($e);

                return ['success' => false, 'status' => 500, 'step' => $step, 'message' => __('snapchat_builder.server.internal_error', ['ref' => $run->id]), 'created' => $this->createdSummary($run), 'retryable' => true];
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
            'message' => $replayed ? __('snapchat_builder.server.replayed') : __('snapchat_builder.server.created'),
            'created' => $this->createdSummary($run),
            'adsquad_id' => $run->result('adsquad.remote'),
            'campaign_local_id' => $run->result('campaign.local'),
            'status' => $run->result('activate.status', 'PAUSED'),
        ];
    }

    private function createdSummary(AdBuilderRun $run): array
    {
        return array_values(array_filter([
            $run->result('campaign.remote') ? ['type' => 'campaign', 'id' => $run->result('campaign.remote'), 'reused' => (bool) $run->result('campaign.reused')] : null,
            $run->result('adsquad.remote') ? ['type' => 'adsquad', 'id' => $run->result('adsquad.remote'), 'reused' => false] : null,
            $run->result('creative.ids') ? ['type' => 'creative', 'id' => implode(', ', (array) $run->result('creative.ids')), 'reused' => false] : null,
            $run->result('ad.ids') ? ['type' => 'ad', 'id' => implode(', ', (array) $run->result('ad.ids')), 'reused' => false] : null,
        ]));
    }

    /**
     * Everything referenced must belong to this ad account (pixel, Public
     * Profile, segments, campaign, gallery media), and Snap's rules that
     * would otherwise only fail on a later call are checked up front.
     */
    private function validateSelection(array $input): array
    {
        $objective = $input['objective'];
        $destination = $input['destination'];
        $sq = $input['adsquad'];
        $t = $sq['targeting'] ?? [];
        $ad = $input['ad'];
        $goals = self::OBJECTIVES[$objective]['destinations'][$destination] ?? null;

        if ($goals === null) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.destination_mismatch'));
        }
        if (! in_array($sq['goal'], $goals, true)) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.goal_mismatch'));
        }
        if ($sq['bid_strategy'] === 'TARGET_COST' && $sq['goal'] === 'IMPRESSIONS') {
            throw new \InvalidArgumentException(__('snapchat_builder.server.target_cost_impressions'));
        }

        if (in_array($sq['goal'], self::PIXEL_GOALS, true) && empty($sq['pixel_id'])) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.pixel_required'));
        }
        if (! empty($sq['pixel_id']) && ! collect($this->pixels())->contains('id', (string) $sq['pixel_id'])) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.pixel_not_owned'));
        }

        if (($sq['placement_mode'] ?? 'auto') === 'custom') {
            $positions = array_values(array_intersect(self::POSITIONS, $sq['positions'] ?? []));
            if (! $positions || $positions === ['CHAT_FEED']) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.positions'));
            }
            if (! empty($sq['content_types']) && ! in_array('INSTREAM', $positions, true)) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.content_needs_instream'));
            }
        }

        if (($sq['budget_type'] ?? 'daily') === 'lifetime' && empty($sq['end_time'])) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.lifetime_needs_end'));
        }

        if (count(array_unique($t['countries'] ?? [])) > 1 && empty($t['languages'])) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.multi_country_language'));
        }
        if (! empty($t['min_age']) && ! empty($t['max_age']) && (int) $t['max_age'] < (int) $t['min_age']) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.age_range'));
        }
        foreach (['regions', 'excluded_regions', 'postal_codes', 'metros', 'excluded_metros'] as $k) {
            foreach ($t[$k] ?? [] as $g) {
                if (! in_array($g['country'], $t['countries'] ?? [], true)) {
                    throw new \InvalidArgumentException(__('snapchat_builder.server.geo_country'));
                }
            }
        }

        $segmentIds = array_merge($t['audiences'] ?? [], $t['excluded_audiences'] ?? []);
        if ($segmentIds) {
            $usable = collect($this->audiences())->where('usable', true)->pluck('id');
            foreach ($segmentIds as $id) {
                if (! $usable->contains((string) $id)) {
                    throw new \InvalidArgumentException(__('snapchat_builder.server.audience_not_owned'));
                }
            }
        }

        $profiles = $this->profiles();
        if ($profiles && ! collect($profiles)->contains('id', (string) $ad['profile_id'])) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.profile_not_owned'));
        }

        $refs = ['media' => [], 'icon' => null];
        foreach ($ad['media_ids'] as $assetId) {
            $asset = MediaAsset::ownedBy((int) $this->api->account->user_id)->find((int) $assetId);
            if (! $asset || ! in_array($asset->type, ['image', 'video'], true)) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.media_not_owned'));
            }
            if ($asset->type === 'video' && (int) $asset->size > self::MAX_VIDEO_BYTES) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.video_too_large', ['name' => $asset->original_name, 'mb' => self::MAX_VIDEO_BYTES / 1048576]));
            }
            $refs['media'][] = $asset;
        }
        if (in_array($sq['goal'], self::VIDEO_GOALS, true) && collect($refs['media'])->contains(fn ($m) => $m->type !== 'video')) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.needs_video'));
        }

        if (in_array($destination, ['app_install', 'deep_link'], true)) {
            $icon = MediaAsset::ownedBy((int) $this->api->account->user_id)->find((int) ($input['app']['icon_media_id'] ?? 0));
            if (! $icon || $icon->type !== 'image') {
                throw new \InvalidArgumentException(__('snapchat_builder.server.icon_required'));
            }
            if ($icon->width && $icon->height && ($icon->width !== $icon->height || $icon->width < 200 || $icon->width > 2000)) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.icon_square'));
            }
            $refs['icon'] = $icon;
        }

        if (($ad['cta'] ?? null) && ! in_array($ad['cta'], self::CTAS[$destination] ?? [], true)) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.cta_mismatch'));
        }

        return $refs;
    }

    // ---- campaign -----------------------------------------------------

    private function campaignStep(AdBuilderRun $run, array $input): array
    {
        if ($remote = $run->result('campaign.remote')) {
            return ['remote' => $remote, 'local' => $run->result('campaign.local'), 'reused' => (bool) $run->result('campaign.reused')];
        }

        $spec = $input['campaign'];
        $objective = $input['objective'];
        $promotion = self::DESTINATIONS[$input['destination']]['promotion'] ?? null;

        if (($spec['mode'] ?? 'new') === 'existing') {
            $existing = collect($this->campaigns())->firstWhere('id', (string) ($spec['id'] ?? ''));
            if (! $existing) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.campaign_not_owned'));
            }
            if (! $existing['supported'] || $existing['objective'] !== $objective || ($promotion && $existing['promotion_type'] && $existing['promotion_type'] !== $promotion)) {
                throw new \InvalidArgumentException(__('snapchat_builder.server.campaign_objective', ['objective' => $existing['objective'] ?? '-']));
            }

            $local = AdCampaign::firstOrCreate(
                ['ad_campaign_id' => $existing['id'], 'platform' => 'snapchat'],
                ['user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $existing['name'], 'objective' => $objective, 'app_promotion_type' => $existing['promotion_type'], 'budget_mode' => $existing['daily_budget'] ? 'daily' : 'total', 'budget' => $existing['lifetime_cap'], 'daily_budget' => $existing['daily_budget'], 'status' => $existing['status'] === 'ACTIVE']
            );
            $run->remember('campaign', ['remote' => $existing['id'], 'local' => $local->id, 'reused' => true]);

            return ['remote' => $existing['id'], 'local' => $local->id, 'reused' => true];
        }

        $name = ($spec['name'] ?? null) ?: __('snapchat_builder.objectives.' . $objective . '.label') . ' - ' . now()->format('Y-m-d');
        $app = $input['app'] ?? [];
        $params = array_filter([
            'ad_account_id' => $this->api->adAccountId(),
            'name' => mb_substr($name, 0, 375),
            // Activated at the very end, once the whole chain exists.
            'status' => 'PAUSED',
            'start_time' => $this->utc($input['adsquad']['start_time'] ?? null) ?? now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'buy_model' => 'AUCTION',
            'creation_state' => 'PUBLISHED',
            'objective_v2_properties' => array_filter(['objective_v2_type' => $objective, 'promotion_type' => $promotion]),
            'daily_budget_micro' => $this->micro($spec['daily_cap'] ?? null),
            'lifetime_spend_cap_micro' => $this->micro($spec['lifetime_cap'] ?? null),
            'measurement_spec' => $promotion ? array_filter(['ios_app_id' => $app['ios_app_id'] ?? null, 'android_app_url' => $app['android_app_url'] ?? null]) : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        $created = $this->api->post('adaccounts/' . $this->api->adAccountId() . '/campaigns', ['campaigns' => [$params]]);
        $id = (string) ($created['campaigns'][0]['campaign']['id'] ?? '');
        if ($id === '') {
            throw new SnapchatApiException(__('snapchat_builder.server.no_id', ['object' => 'campaign']));
        }
        $run->remember('campaign', ['remote' => $id, 'reused' => false]);

        $local = AdCampaign::updateOrCreate(['ad_campaign_id' => $id, 'platform' => 'snapchat'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $params['name'], 'objective' => $objective,
            'app_promotion_type' => $promotion,
            'budget_mode' => ($input['adsquad']['budget_type'] ?? 'daily') === 'lifetime' ? 'total' : 'daily',
            'budget' => $spec['lifetime_cap'] ?? $input['adsquad']['budget'] ?? null,
            'daily_budget' => $spec['daily_cap'] ?? null,
            'bidding_strategy' => $input['adsquad']['bid_strategy'],
            'start_time' => $params['start_time'],
            'end_time' => $this->utc($input['adsquad']['end_time'] ?? null),
            'app_id' => $app['ios_app_id'] ?? $app['android_app_url'] ?? null,
            'status' => false,
        ]);
        $run->remember('campaign.local', $local->id);

        return ['remote' => $id, 'local' => $local->id, 'reused' => false];
    }

    // ---- ad squad -----------------------------------------------------

    private function adSquadStep(AdBuilderRun $run, array $input, array $campaign): array
    {
        if ($remote = $run->result('adsquad.remote')) {
            return ['remote' => $remote, 'local' => $run->result('adsquad.local')];
        }

        $params = $this->adSquadParams($input, $campaign['remote']);
        $created = $this->api->post('campaigns/' . $campaign['remote'] . '/adsquads', ['adsquads' => [$params]]);
        $id = (string) ($created['adsquads'][0]['adsquad']['id'] ?? '');
        if ($id === '') {
            throw new SnapchatApiException(__('snapchat_builder.server.no_id', ['object' => 'ad squad']));
        }
        $run->remember('adsquad', ['remote' => $id]);

        $sq = $input['adsquad'];
        $t = $sq['targeting'] ?? [];
        $local = AdAdGroup::updateOrCreate(['ad_adgroup_id' => $id, 'platform' => 'snapchat'], [
            'user_id' => $run->user_id,
            'social_account_id' => $this->api->account->id,
            'ad_campaign_id' => $campaign['local'],
            'name' => $params['name'],
            'objective' => $input['objective'],
            'destination_type' => $input['destination'],
            'placement_type' => $params['placement_v2']['config'],
            'placements' => json_encode($params['placement_v2']['snapchat_positions'] ?? []),
            'optimization_goal' => $params['optimization_goal'],
            'bid_strategy' => $params['bid_strategy'],
            'bid_type' => $params['bid_strategy'],
            'bid_price' => $sq['bid_amount'] ?? null,
            'billing_event' => 'IMPRESSION',
            'budget_mode' => ($sq['budget_type'] ?? 'daily') === 'lifetime' ? 'total' : 'daily',
            'budget' => $sq['budget'] ?? null,
            'schedule_start_time' => $params['start_time'],
            'schedule_end_time' => $params['end_time'] ?? null,
            'pacing' => $params['pacing_type'] ?? 'STANDARD',
            'primary_web_event_tag' => $params['pixel_id'] ?? null,
            'ios' => $input['app']['ios_app_id'] ?? null,
            'android' => $input['app']['android_app_url'] ?? null,
            'location_ids' => json_encode($t['countries'] ?? []),
            'languages' => json_encode($t['languages'] ?? []),
            'gender' => $t['gender'] ?? 'ALL',
            'age_groups' => json_encode(array_filter(['min' => $t['min_age'] ?? null, 'max' => $t['max_age'] ?? null])),
            'operating_systems' => json_encode($t['os_types'] ?? []),
            'targeting_criteria' => json_encode(['spec' => $params['targeting'], 'local' => $t]),
            'status' => false,
        ]);
        $run->remember('adsquad.local', $local->id);

        return ['remote' => $id, 'local' => $local->id];
    }

    // ---- media --------------------------------------------------------

    /** @return array{top: string[], icon: ?string} Snap media ids */
    private function mediaStep(AdBuilderRun $run, array $refs, array $campaign): array
    {
        $top = [];
        foreach ($refs['media'] as $asset) {
            $top[] = $this->uploadMedia($run, $asset, $campaign);
        }

        return ['top' => $top, 'icon' => $refs['icon'] ? $this->uploadMedia($run, $refs['icon'], $campaign) : null];
    }

    /** Media container -> file upload -> wait for READY (Media reference). */
    private function uploadMedia(AdBuilderRun $run, MediaAsset $asset, array $campaign): string
    {
        if ($id = $run->result("media.asset_{$asset->id}.ready")) {
            return $id;
        }

        $type = $asset->type === 'video' ? 'VIDEO' : 'IMAGE';
        $mediaId = $run->result("media.asset_{$asset->id}.id");
        if (! $mediaId) {
            $created = $this->api->post('adaccounts/' . $this->api->adAccountId() . '/media', ['media' => [[
                'name' => mb_substr((string) ($asset->original_name ?: 'Syncora ' . strtolower($type)), 0, 375),
                'type' => $type,
                'ad_account_id' => $this->api->adAccountId(),
            ]]]);
            $mediaId = (string) ($created['media'][0]['media']['id'] ?? '');
            if ($mediaId === '') {
                throw new SnapchatApiException(__('snapchat_builder.server.no_id', ['object' => 'media']));
            }
            $run->remember("media.asset_{$asset->id}.id", $mediaId);
        }

        if (! $run->result("media.asset_{$asset->id}.uploaded")) {
            $extension = $asset->extension ?: ($type === 'VIDEO' ? 'mp4' : 'jpg');
            $this->api->upload('media/' . $mediaId . '/upload', $this->bytes($asset), 'syncora-' . $asset->id . '.' . strtolower($extension));
            $run->remember("media.asset_{$asset->id}.uploaded", true);
        }

        for ($i = 0; $i < 30; $i++) {
            $status = $this->api->get('media/' . $mediaId)['media'][0]['media']['media_status'] ?? null;
            if ($status === 'READY') {
                break;
            }
            sleep(2);
        }
        if (($status ?? null) !== 'READY') {
            throw new SnapchatApiException(__('snapchat_builder.server.still_processing'));
        }
        $run->remember("media.asset_{$asset->id}.ready", $mediaId);

        AdMedia::updateOrCreate(['ad_media_id' => $mediaId, 'platform' => 'snapchat', 'ad_campaign_id' => $campaign['local']], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $asset->original_name, 'file_name' => $asset->original_name,
            'url' => $asset->url, 'download_link' => $asset->url, 'type' => $type, 'image_category' => $type, 'file_id' => $mediaId, 'status' => true, 'thumbnail_url' => $asset->thumbnail_url,
        ]);

        return $mediaId;
    }

    /** The gallery file's bytes: from its disk when available, else its URL. */
    private function bytes(MediaAsset $asset): string
    {
        try {
            if ($asset->disk && $asset->path && Storage::disk($asset->disk)->exists($asset->path)) {
                return Storage::disk($asset->disk)->get($asset->path);
            }
        } catch (\Throwable) {
            // fall back to the URL
        }
        $response = Http::timeout(180)->get($asset->url);
        if (! $response->successful()) {
            throw new \InvalidArgumentException(__('snapchat_builder.server.unreadable', ['name' => $asset->original_name]));
        }

        return $response->body();
    }

    // ---- creatives & ads ----------------------------------------------

    /** The creative payload for one Top Snap (shared headline, CTA and attachment). */
    public function creativeParams(array $input, string $topSnapMediaId, ?string $iconMediaId, int $index): array
    {
        $ad = $input['ad'];
        $destination = $input['destination'];
        $app = $input['app'] ?? [];
        $base = ($ad['name'] ?? null) ?: ($ad['headline'] . ' - ' . now()->format('Y-m-d'));
        $count = count($input['ad']['media_ids']);

        $params = [
            'ad_account_id' => $this->api->adAccountId(),
            'name' => mb_substr($count > 1 ? $base . ' #' . ($index + 1) : $base, 0, 375),
            'type' => self::DESTINATIONS[$destination]['creative'],
            'headline' => mb_substr((string) $ad['headline'], 0, 34),
            'brand_name' => ! empty($ad['brand_name']) ? mb_substr((string) $ad['brand_name'], 0, 32) : null,
            'shareable' => (bool) ($ad['shareable'] ?? true),
            'top_snap_media_id' => $topSnapMediaId,
            'profile_properties' => ['profile_id' => (string) $ad['profile_id']],
            'call_to_action' => $destination !== 'none' ? ($ad['cta'] ?? null) : null,
        ];

        if ($destination === 'web') {
            $params['web_view_properties'] = ['url' => $ad['url'], 'block_preload' => (bool) ($ad['block_preload'] ?? false)];
        } elseif ($destination === 'app_install') {
            $params['app_install_properties'] = array_filter([
                'app_name' => mb_substr((string) $app['app_name'], 0, 30),
                'ios_app_id' => $app['ios_app_id'] ?? null,
                'android_app_url' => $app['android_app_url'] ?? null,
                'icon_media_id' => $iconMediaId,
            ]);
        } elseif ($destination === 'deep_link') {
            $params['deep_link_properties'] = array_filter([
                'deep_link_uri' => $app['deep_link_uri'] ?? null,
                'app_name' => mb_substr((string) $app['app_name'], 0, 30),
                'ios_app_id' => $app['ios_app_id'] ?? null,
                'android_app_url' => $app['android_app_url'] ?? null,
                'icon_media_id' => $iconMediaId,
                'fallback_type' => ! empty($app['web_fallback_url']) ? 'WEB_SITE' : 'APP_INSTALL',
                'web_view_fallback_url' => $app['web_fallback_url'] ?? null,
            ]);
        }

        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    }

    /** @return string[] creative ids, one per Top Snap */
    private function creativeStep(AdBuilderRun $run, array $input, array $campaign, array $squad, array $refs, array $media): array
    {
        $ids = (array) $run->result('creative.ids', []);

        foreach ($media['top'] as $i => $mediaId) {
            if (isset($ids[$i])) {
                continue;
            }
            $params = $this->creativeParams($input, $mediaId, $media['icon'], $i);
            $created = $this->api->post('adaccounts/' . $this->api->adAccountId() . '/creatives', ['creatives' => [$params]]);
            $id = (string) ($created['creatives'][0]['creative']['id'] ?? '');
            if ($id === '') {
                throw new SnapchatApiException(__('snapchat_builder.server.no_id', ['object' => 'creative']));
            }
            $ids[$i] = $id;
            $run->remember('creative.ids', $ids);

            AdCreative::updateOrCreate(['ad_creative_id' => $id, 'platform' => 'snapchat'], [
                'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'ad_campaign_id' => $campaign['local'], 'ad_adgroup_id' => $squad['local'],
                'name' => $params['name'], 'type' => $params['type'], 'ad_format' => strtoupper($refs['media'][$i]->type ?? 'IMAGE'),
                'headline' => $params['headline'], 'brand_name' => $params['brand_name'] ?? null, 'profile_id' => $input['ad']['profile_id'],
                'top_snap_media_id' => $mediaId, 'call_to_action' => $params['call_to_action'] ?? null, 'url' => $input['ad']['url'] ?? null,
                'web_view_properties' => isset($params['web_view_properties']) ? json_encode($params['web_view_properties']) : null,
                'app_install_properties' => isset($params['app_install_properties']) ? json_encode($params['app_install_properties']) : null,
                'deep_link_properties' => isset($params['deep_link_properties']) ? json_encode($params['deep_link_properties']) : null,
            ]);
        }

        return array_values($ids);
    }

    private function adStep(AdBuilderRun $run, array $input, array $campaign, array $squad, array $creativeIds): void
    {
        $ids = (array) $run->result('ad.ids', []);
        $type = self::DESTINATIONS[$input['destination']]['ad'];
        $base = ($input['ad']['name'] ?? null) ?: ($input['ad']['headline'] . ' - ' . now()->format('Y-m-d'));

        foreach ($creativeIds as $i => $creativeId) {
            if (isset($ids[$i])) {
                continue;
            }
            $name = mb_substr(count($creativeIds) > 1 ? $base . ' #' . ($i + 1) : $base, 0, 375);
            $created = $this->api->post('adsquads/' . $squad['remote'] . '/ads', ['ads' => [[
                'ad_squad_id' => $squad['remote'], 'creative_id' => $creativeId, 'name' => $name, 'type' => $type, 'status' => 'PAUSED',
            ]]]);
            $id = (string) ($created['ads'][0]['ad']['id'] ?? '');
            if ($id === '') {
                throw new SnapchatApiException(__('snapchat_builder.server.no_id', ['object' => 'ad']));
            }
            $ids[$i] = $id;
            $run->remember('ad.ids', $ids);

            $creative = AdCreative::where('platform', 'snapchat')->where('ad_creative_id', $creativeId)->first();
            Ad::updateOrCreate(['ad_id' => $id, 'platform' => 'snapchat'], [
                'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'ad_campaign_id' => $campaign['local'],
                'ad_adgroup_id' => $squad['local'], 'ad_creative_id' => $creative?->id, 'name' => $name, 'type' => $type,
                'call_to_action' => $creative?->call_to_action, 'status' => false,
            ]);
        }
    }

    /** Launch: ads -> ad squad -> (new) campaign, each switched on with a JSON Patch. */
    private function activateStep(AdBuilderRun $run, array $input, array $campaign, array $squad): void
    {
        $status = ($input['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
        $on = [['op' => 'replace', 'path' => '/status', 'value' => 'ACTIVE']];

        if ($status === 'ACTIVE') {
            foreach ((array) $run->result('ad.ids', []) as $i => $adId) {
                if (! $run->result("activate.ad_{$i}")) {
                    $this->api->patch('adsquads/' . $squad['remote'] . '/ads/' . $adId, $on);
                    $run->remember("activate.ad_{$i}", true);
                }
            }
            if (! $run->result('activate.adsquad')) {
                $this->api->patch('campaigns/' . $campaign['remote'] . '/adsquads/' . $squad['remote'], $on);
                $run->remember('activate.adsquad', true);
            }
            if (! $campaign['reused'] && ! $run->result('activate.campaign')) {
                $this->api->patch('adaccounts/' . $this->api->adAccountId() . '/campaigns/' . $campaign['remote'], $on);
                $run->remember('activate.campaign', true);
            }
            AdAdGroup::whereKey($squad['local'])->update(['status' => true]);
            if (! $campaign['reused']) {
                AdCampaign::whereKey($campaign['local'])->update(['status' => true]);
            }
            Ad::where('platform', 'snapchat')->whereIn('ad_id', array_values((array) $run->result('ad.ids', [])))->update(['status' => true]);
        }

        $run->remember('activate.status', $status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** The ad account's time zone (Snap reports it on GET adaccounts/{id}). */
    private function timezone(): string
    {
        $tz = $this->info()['timezone'] ?? $this->api->account->adDetails?->timezone ?? 'UTC';

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    private function micro($amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round(((float) $amount) * 1_000_000);
    }

    /** A datetime-local value in the ad account's time zone -> Snap's UTC format. */
    private function utc(?string $local): ?string
    {
        if (! $local) {
            return null;
        }
        try {
            return Carbon::parse($local, $this->timezone())->utc()->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return Carbon::parse($local, 'UTC')->format('Y-m-d\TH:i:s.v\Z');
        }
    }
}
