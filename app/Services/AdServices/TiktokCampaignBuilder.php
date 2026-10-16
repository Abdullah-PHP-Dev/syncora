<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdCreativeMedia;
use App\Models\Admin\AdMedia;
use App\Models\MediaAsset;
use App\Support\Ads\TiktokAdsApi;
use App\Support\Ads\TiktokApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * TikTok campaign builder (ads/tiktok/campaigns/create): campaign (new or
 * existing) -> ad group -> ad, through the TikTok Marketing API v1.3.
 *
 * - Lookups (identities, Spark posts, pixels, audiences, apps, Instant
 *   Forms, locations, languages, interests & behaviors, device models, OS
 *   versions) are read live from TikTok for the seller's own advertiser.
 * - Everything a submission references is checked to belong to that
 *   advertiser (or, for gallery media, the seller) before it is used.
 * - submit() is idempotent per key (AdBuilderRun, platform "tiktok"): each
 *   TikTok object is recorded the moment it exists, so a retry continues
 *   from the failed step instead of creating duplicates.
 * - Field names and the objective -> optimization goal -> billing event
 *   pairs follow TikTok's v1.3 reference and the combinations confirmed
 *   live in TiktokAdService.
 */
class TiktokCampaignBuilder
{
    /**
     * Objectives offered, with their optimization goals (=> billing event)
     * and what each needs. PRODUCT_SALES (catalog / TikTok Shop) and
     * RF_REACH (Reach & Frequency) need separate onboarding and aren't offered.
     */
    public const OBJECTIVES = [
        'REACH' => ['icon' => 'bx-broadcast', 'goals' => ['REACH' => 'CPM'], 'promotion_type' => 'WEBSITE', 'url' => 'optional'],
        'TRAFFIC' => ['icon' => 'bx-pointer', 'goals' => ['CLICK' => 'CPC', 'TRAFFIC_LANDING_PAGE_VIEW' => 'OCPM'], 'promotion_type' => 'WEBSITE', 'url' => 'required'],
        'VIDEO_VIEWS' => ['icon' => 'bx-play-circle', 'goals' => ['ENGAGED_VIEW' => 'CPV', 'ENGAGED_VIEW_FIFTEEN' => 'CPV'], 'promotion_type' => 'WEBSITE', 'url' => 'optional'],
        'ENGAGEMENT' => ['icon' => 'bx-user-plus', 'goals' => ['FOLLOWERS' => 'OCPM', 'PAGE_VISIT' => 'CPC'], 'promotion_type' => null, 'url' => 'none'],
        'LEAD_GENERATION' => ['icon' => 'bx-id-card', 'goals' => ['LEAD_GENERATION' => 'OCPM'], 'promotion_type' => 'LEAD_GENERATION', 'url' => 'none', 'needs_form' => true],
        'WEB_CONVERSIONS' => ['icon' => 'bx-cart-alt', 'goals' => ['CONVERT' => 'OCPM'], 'promotion_type' => 'WEBSITE', 'url' => 'required', 'needs_pixel' => true],
        'APP_PROMOTION' => ['icon' => 'bx-mobile-alt', 'goals' => ['INSTALL' => 'OCPM'], 'promotion_type' => 'APP', 'url' => 'none', 'needs_app' => true],
    ];

    public const APP_PROMOTION_TYPES = ['APP_INSTALL', 'APP_RETARGETING'];

    public const SPECIAL_INDUSTRIES = ['HOUSING', 'EMPLOYMENT', 'CREDIT'];

    public const AGE_GROUPS = ['AGE_13_17', 'AGE_18_24', 'AGE_25_34', 'AGE_35_44', 'AGE_45_54', 'AGE_55_100'];

    public const GENDERS = ['GENDER_UNLIMITED', 'GENDER_MALE', 'GENDER_FEMALE'];

    public const PLACEMENTS = ['PLACEMENT_TIKTOK', 'PLACEMENT_PANGLE', 'PLACEMENT_GLOBAL_APP_BUNDLE'];

    public const NETWORK_TYPES = ['WIFI', '2G', '3G', '4G', '5G'];

    public const HOUSEHOLD_INCOME = ['TOP5', 'TOP10', 'TOP10_25', 'TOP25_50'];

    public const BRAND_SAFETY = ['NO_BRAND_SAFETY', 'EXPANDED_INVENTORY', 'STANDARD_INVENTORY', 'LIMITED_INVENTORY'];

    /** Consolidated interest & behavior search groups (targeting/search/). */
    public const INTEREST_TYPES = ['GENERAL_INTEREST', 'ADDITIONAL_INTEREST', 'PURCHASE_INTENTION', 'VIDEO_INTERACTION', 'CREATOR_INTERACTION', 'HASHTAG_INTERACTION'];

    public const VIDEO_ACTIONS = ['WATCHED_TO_END', 'LIKED', 'COMMENTED', 'SHARED'];

    public const ACTION_PERIODS = [0, 7, 15];

    /** Objectives where TikTok accepts purchase-intention categories. */
    public const PURCHASE_INTENT_OBJECTIVES = ['APP_PROMOTION', 'WEB_CONVERSIONS', 'LEAD_GENERATION', 'TRAFFIC'];

    public const CTAS = ['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'CONTACT_US', 'APPLY_NOW', 'BOOK_NOW', 'ORDER_NOW', 'GET_QUOTE', 'SUBSCRIBE', 'DOWNLOAD_NOW', 'INSTALL_NOW', 'WATCH_NOW', 'VIEW_NOW', 'READ_MORE', 'PLAY_GAME', 'GET_TICKETS_NOW', 'PREORDER_NOW', 'VISIT_STORE', 'INTERESTED', 'EXPERIENCE_NOW', 'LISTEN_NOW', 'GET_SHOWTIMES', 'CALL_NOW', 'SEND_MESSAGE', 'VIEW_PROFILE', 'CHECK_AVAILABLILITY'];

    /** Local ad_media ids used by the ad being built. */
    private array $creativeMedia = [];

    public function __construct(private TiktokAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = TiktokAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function api(): TiktokAdsApi
    {
        return $this->api;
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public function bootstrap(): array
    {
        $info = $this->advertiser();

        return [
            'account' => [
                'id' => $this->api->advertiserId(),
                'local_id' => $this->api->account->id,
                'name' => $info['name'] ?? $this->api->account->name,
                'currency' => $info['currency'] ?? 'USD',
                'timezone' => $info['display_timezone'] ?? $info['timezone'] ?? 'UTC',
                'country' => $info['country'] ?? null,
                'active' => ($info['status'] ?? 'STATUS_ENABLE') === 'STATUS_ENABLE',
            ],
            'objectives' => $this->localizedObjectives(),
            'options' => [
                'app_promotion_types' => self::APP_PROMOTION_TYPES,
                'special_industries' => self::SPECIAL_INDUSTRIES,
                'age_groups' => self::AGE_GROUPS,
                'genders' => self::GENDERS,
                'placements' => self::PLACEMENTS,
                'network_types' => self::NETWORK_TYPES,
                'household_income' => self::HOUSEHOLD_INCOME,
                'brand_safety' => self::BRAND_SAFETY,
                'interest_types' => self::INTEREST_TYPES,
                'video_actions' => self::VIDEO_ACTIONS,
                'action_periods' => self::ACTION_PERIODS,
                'purchase_intent_objectives' => self::PURCHASE_INTENT_OBJECTIVES,
                'ctas' => self::CTAS,
            ],
            'identities' => $this->identities(),
        ];
    }

    /** advertiser/info/ for the selected advertiser (cached briefly). */
    private function advertiser(): array
    {
        return Cache::remember($this->cacheKey('info'), 600, function () {
            $data = $this->api->get('advertiser/info/', [
                'advertiser_ids' => [$this->api->advertiserId()],
                'fields' => ['name', 'currency', 'timezone', 'display_timezone', 'status', 'country'],
            ]);

            return $data['list'][0] ?? [];
        });
    }

    public function localizedObjectives(): array
    {
        $out = [];
        foreach (self::OBJECTIVES as $key => $o) {
            $out[$key] = [
                'icon' => $o['icon'],
                'label' => __("tiktok_builder.objectives.{$key}.label"),
                'summary' => __("tiktok_builder.objectives.{$key}.summary"),
                'goals' => array_map(fn ($billing) => ['billing' => $billing], $o['goals']),
                'url' => $o['url'],
                'needs_pixel' => (bool) ($o['needs_pixel'] ?? false),
                'needs_app' => (bool) ($o['needs_app'] ?? false),
                'needs_form' => (bool) ($o['needs_form'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Identities the ads can run as. Custom identities are left out: TikTok
     * no longer accepts them for ads on TikTok placements (Jan 2026).
     */
    public function identities(): array
    {
        $data = $this->api->get('identity/get/', ['page_size' => 100]);

        return collect($data['identity_list'] ?? [])
            ->reject(fn ($i) => ($i['identity_type'] ?? '') === 'CUSTOMIZED_USER')
            ->map(fn ($i) => [
                'id' => (string) $i['identity_id'],
                'type' => $i['identity_type'] ?? 'TT_USER',
                'bc_id' => $i['identity_authorized_bc_id'] ?? null,
                'name' => $i['display_name'] ?? $i['username'] ?? $i['identity_id'],
                'username' => $i['username'] ?? null,
                'picture' => $i['profile_image'] ?? null,
                'available' => ($i['available_status'] ?? 'AVAILABLE') === 'AVAILABLE',
                'can_pull_video' => (bool) ($i['can_pull_video'] ?? false),
                'can_push_video' => (bool) ($i['can_push_video'] ?? true),
            ])
            ->values()->all();
    }

    /** The identity's own TikTok posts, for Spark Ads. */
    public function sparkPosts(string $identityId, string $identityType, ?string $bcId = null, ?string $cursor = null): array
    {
        $identity = $this->ownedIdentity($identityId, $identityType);

        $data = $this->api->get('identity/video/get/', array_filter([
            'identity_type' => $identity['type'],
            'identity_id' => $identity['id'],
            'identity_authorized_bc_id' => $identity['bc_id'] ?? $bcId,
            'count' => 20,
            'cursor' => $cursor,
        ], fn ($v) => $v !== null && $v !== ''));

        return [
            'posts' => collect($data['video_list'] ?? [])
                ->filter(fn ($v) => ($v['item_type'] ?? 'VIDEO') === 'VIDEO')
                ->map(fn ($v) => [
                    'id' => (string) $v['item_id'],
                    'text' => $v['text'] ?? '',
                    'poster' => $v['video_info']['poster_url'] ?? null,
                    'duration' => isset($v['video_info']['duration']) ? (float) $v['video_info']['duration'] : null,
                ])->values()->all(),
            'cursor' => isset($data['cursor']) ? (string) $data['cursor'] : null,
            'has_more' => (bool) ($data['has_more'] ?? false),
        ];
    }

    public function campaigns(?string $search = null): array
    {
        $data = $this->api->get('campaign/get/', array_filter([
            'page_size' => 100,
            'fields' => ['campaign_id', 'campaign_name', 'objective_type', 'budget', 'budget_mode', 'operation_status', 'budget_optimize_on', 'app_promotion_type'],
            'filtering' => $search ? ['campaign_name' => $search] : null,
        ]));

        return collect($data['list'] ?? [])->map(fn ($c) => [
            'id' => (string) $c['campaign_id'],
            'name' => $c['campaign_name'] ?? $c['campaign_id'],
            'objective' => $c['objective_type'] ?? null,
            'supported' => isset(self::OBJECTIVES[$c['objective_type'] ?? '']),
            'status' => $c['operation_status'] ?? null,
            'budget_mode' => $c['budget_mode'] ?? null,
            'budget' => isset($c['budget']) ? (float) $c['budget'] : null,
            'cbo' => (bool) ($c['budget_optimize_on'] ?? false),
            'app_promotion_type' => $c['app_promotion_type'] ?? null,
        ])->values()->all();
    }

    public function pixels(): array
    {
        $data = $this->api->get('pixel/list/', ['page_size' => 20]);

        return collect($data['pixels'] ?? [])->map(fn ($p) => [
            'id' => (string) $p['pixel_id'],
            'name' => $p['pixel_name'] ?? $p['pixel_code'] ?? $p['pixel_id'],
            'events' => collect($p['events'] ?? [])
                ->map(fn ($e) => ['code' => $e['optimization_event'] ?? $e['event_type'] ?? null, 'name' => $e['name'] ?? $e['optimization_event'] ?? $e['event_type'] ?? null])
                ->filter(fn ($e) => $e['code'])->unique('code')->values()->all(),
        ])->values()->all();
    }

    /** Custom and lookalike audiences (owned and shared). */
    public function audiences(): array
    {
        $data = $this->api->get('dmp/custom_audience/list/', ['page_size' => 100]);

        return collect($data['list'] ?? [])->map(fn ($a) => [
            'id' => (string) $a['audience_id'],
            'name' => $a['name'] ?? $a['audience_id'],
            'type' => $a['audience_type'] ?? null,
            'size' => $a['cover_num'] ?? null,
            'usable' => ($a['is_valid'] ?? true) && ! ($a['is_expired'] ?? false),
        ])->values()->all();
    }

    public function apps(): array
    {
        $data = $this->api->get('app/list/');

        return collect($data['apps'] ?? [])->map(fn ($a) => [
            'id' => (string) $a['app_id'],
            'name' => $a['app_name'] ?? $a['app_id'],
            'platform' => strtoupper((string) ($a['platform'] ?? '')),
        ])->values()->all();
    }

    /** Instant Forms (lead generation pages). */
    public function leadForms(): array
    {
        $data = $this->api->get('page/get/', ['business_type' => 'LEAD_GEN', 'page_size' => 100]);

        return collect($data['list'] ?? [])->map(fn ($p) => [
            'id' => (string) $p['page_id'],
            'name' => $p['title'] ?? $p['page_name'] ?? $p['name'] ?? $p['page_id'],
            'status' => $p['status'] ?? null,
        ])->values()->all();
    }

    /** Countries, regions, cities, DMAs and US zip codes available for this objective. */
    public function locations(string $query, string $objective): array
    {
        $objective = isset(self::OBJECTIVES[$objective]) ? $objective : 'TRAFFIC';
        $promotion = $this->promotionType($objective, null);

        $data = $this->api->post('tool/targeting/search/', array_filter([
            'objective_type' => $objective,
            'placements' => ['PLACEMENT_TIKTOK'],
            'promotion_type' => in_array($objective, ['REACH', 'VIDEO_VIEWS', 'ENGAGEMENT'], true) ? null : $promotion,
            'search_type' => 'FUZZY_SEARCH',
            'keywords' => [$query],
        ]));

        $parents = collect($data['parent_tags'] ?? [])->mapWithKeys(fn ($t) => [(string) ($t['geo']['geo_id'] ?? '') => $t['name'] ?? '']);

        return collect($data['targeting_tag_list'] ?? [])
            ->filter(fn ($t) => isset($t['geo']['geo_id']))
            ->map(function ($t) use ($parents) {
                $parent = $parents[(string) ($t['geo']['parent_id'] ?? '')] ?? null;

                return [
                    'id' => (string) $t['geo']['geo_id'],
                    'name' => $t['name'],
                    'type' => $t['geo']['geo_type'] ?? 'COUNTRY',
                    'region_code' => $t['geo']['region_code'] ?? null,
                    'parent' => $parent ?: null,
                    'enabled' => ($t['status_info']['status'] ?? 'ENABLED') === 'ENABLED',
                    'reason' => $t['status_info']['reason'] ?? null,
                ];
            })->values()->all();
    }

    public function languages(): array
    {
        return Cache::remember($this->cacheKey('languages'), 86400, function () {
            $data = $this->api->get('tool/language/');

            return collect($data['languages'] ?? [])->map(fn ($l) => ['code' => (string) $l['code'], 'name' => trim((string) ($l['name'] ?? $l['code']))])
                ->sortBy('name')->values()->all();
        });
    }

    /**
     * Interests & behaviors (targeting/search/): general and additional
     * interests, purchase intention, video / creator / hashtag interactions.
     */
    public function interests(string $query, array $types = []): array
    {
        $types = array_values(array_intersect($types ?: self::INTEREST_TYPES, self::INTEREST_TYPES));
        $body = ['targeting_type' => 'INTEREST_AND_BEHAVIOR', 'search_keywords' => [$query], 'sub_targeting_types' => $types];

        // GET (POST answers 404), unlike tool/targeting/search/ which is POST-only.
        $data = $this->api->get('targeting/search/', $body);

        $items = [];
        foreach ($types as $type) {
            foreach ($data[strtolower($type)]['search_result'] ?? [] as $results) {
                foreach ((array) $results as $r) {
                    if (! empty($r['id'])) {
                        $items[] = ['id' => (string) $r['id'], 'name' => $r['name'] ?? $r['id'], 'type' => $type, 'key' => $type . ':' . $r['id']];
                    }
                }
            }
        }

        return collect($items)->unique('key')->values()->all();
    }

    public function deviceModels(string $query): array
    {
        $all = Cache::remember($this->cacheKey('device_models'), 86400, function () {
            $data = $this->api->get('tool/device_model/');

            return collect($data['device_models'] ?? [])
                ->filter(fn ($d) => ($d['is_active'] ?? true) && ! empty($d['device_model_id']))
                ->map(fn ($d) => ['id' => (string) $d['device_model_id'], 'name' => (string) ($d['device_model_name'] ?? $d['device_model_id']), 'os' => $d['os_type'] ?? null])
                ->values()->all();
        });
        $needle = mb_strtolower($query);

        return collect($all)->filter(fn ($d) => str_contains(mb_strtolower($d['name']), $needle))->take(40)->values()->all();
    }

    public function osVersions(string $os): array
    {
        $os = $os === 'IOS' ? 'IOS' : 'ANDROID';

        return Cache::remember($this->cacheKey("os_{$os}"), 86400, function () use ($os) {
            $data = $this->api->get('tool/os_version/', ['os_type' => $os]);

            return collect($data['os_versions'] ?? [])->map(fn ($v) => ['version' => (string) $v['version'], 'name' => $v['name'] ?? $v['version']])->values()->all();
        });
    }

    private function cacheKey(string $name): string
    {
        return "tiktok-builder:{$this->api->advertiserId()}:{$name}";
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------

    public function submit(array $input, string $key): array
    {
        $userId = (int) $this->api->account->user_id;
        $hash = hash('sha256', json_encode($input));

        $lock = Cache::lock("ad-builder:{$userId}:{$key}", 300);
        if (! $lock->get()) {
            return ['success' => false, 'status' => 409, 'message' => __('tiktok_builder.server.in_progress')];
        }

        try {
            $run = AdBuilderRun::firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => $key],
                ['social_account_id' => $this->api->account->id, 'platform' => 'tiktok', 'payload_hash' => $hash, 'status' => 'running', 'results' => []]
            );

            if ($run->platform !== 'tiktok' || (int) $run->social_account_id !== (int) $this->api->account->id) {
                return ['success' => false, 'status' => 409, 'message' => __('tiktok_builder.server.key_reused')];
            }
            if ($run->status === 'completed') {
                return $this->completedResponse($run, true);
            }
            if ($run->payload_hash !== $hash) {
                $run->forceFill(['payload_hash' => $hash])->save();
            }
            $run->forceFill(['status' => 'running', 'failed_step' => null, 'error' => null])->save();
            $step = 'validation';

            try {
                $refs = $this->validateSelection($input);
                // An app objective targets the promoted app's own OS.
                $input['_app_platform'] = in_array($refs['app']['platform'] ?? null, ['ANDROID', 'IOS'], true) ? $refs['app']['platform'] : null;
                $step = 'campaign';
                $campaign = $this->campaignStep($run, $input);
                $step = 'adgroup';
                $adGroup = $this->adGroupStep($run, $input, $campaign);
                $step = 'ad';
                $this->adStep($run, $input, $campaign, $adGroup, $refs);
            } catch (TiktokApiException|\InvalidArgumentException $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                Log::warning('TikTok campaign builder step failed.', ['run' => $run->id, 'step' => $step, 'error' => $e->getMessage(), 'request_id' => $e instanceof TiktokApiException ? $e->requestId : null]);

                return ['success' => false, 'status' => 422, 'step' => $step, 'message' => $e->getMessage(), 'created' => $this->createdSummary($run), 'retryable' => true];
            } catch (\Throwable $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                report($e);

                return ['success' => false, 'status' => 500, 'step' => $step, 'message' => __('tiktok_builder.server.internal_error', ['ref' => $run->id]), 'created' => $this->createdSummary($run), 'retryable' => true];
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
            'message' => $replayed ? __('tiktok_builder.server.replayed') : __('tiktok_builder.server.created'),
            'created' => $this->createdSummary($run),
            'ad_id' => $run->result('ad.remote'),
            'campaign_local_id' => $run->result('campaign.local'),
            'status' => $run->result('ad.status', 'DISABLE'),
        ];
    }

    private function createdSummary(AdBuilderRun $run): array
    {
        return array_values(array_filter([
            $run->result('campaign.remote') ? ['type' => 'campaign', 'id' => $run->result('campaign.remote'), 'reused' => (bool) $run->result('campaign.reused')] : null,
            $run->result('adgroup.remote') ? ['type' => 'adgroup', 'id' => $run->result('adgroup.remote'), 'reused' => false] : null,
            $run->result('ad.remote') ? ['type' => 'ad', 'id' => $run->result('ad.remote'), 'reused' => false] : null,
        ]));
    }

    /**
     * Everything referenced must belong to this advertiser: identity, pixel,
     * audiences, app, Instant Form, existing campaign. Also enforces the
     * TikTok rules that would otherwise only fail at the last call.
     * Returns the resolved identity (and app) for the ad step.
     */
    private function validateSelection(array $input): array
    {
        $objective = $this->objectiveOf($input);
        $spec = self::OBJECTIVES[$objective];
        $group = $input['adgroup'];
        $targeting = $group['targeting'] ?? [];
        $ad = $input['ad'];

        if (! array_key_exists($group['optimization_goal'] ?? '', $spec['goals'])) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.goal_mismatch'));
        }

        $identity = $this->ownedIdentity((string) ($ad['identity_id'] ?? ''), (string) ($ad['identity_type'] ?? ''));
        if (! $identity['available']) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.identity_unavailable', ['name' => $identity['name']]));
        }

        if (! empty($group['pixel_id']) && ! collect($this->pixels())->pluck('id')->contains((string) $group['pixel_id'])) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.pixel_not_owned'));
        }
        if (! empty($ad['tracking_pixel_id']) && ! collect($this->pixels())->pluck('id')->contains((string) $ad['tracking_pixel_id'])) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.pixel_not_owned'));
        }

        $audienceIds = array_merge($targeting['audience_ids'] ?? [], $targeting['excluded_audience_ids'] ?? []);
        if ($audienceIds) {
            $owned = collect($this->audiences())->pluck('id');
            foreach ($audienceIds as $id) {
                if (! $owned->contains((string) $id)) {
                    throw new \InvalidArgumentException(__('tiktok_builder.server.audience_not_owned'));
                }
            }
        }

        $app = null;
        if (! empty($spec['needs_app'])) {
            $app = collect($this->apps())->firstWhere('id', (string) ($group['app_id'] ?? ''));
            if (! $app) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.app_not_owned'));
            }
        }

        if (! empty($spec['needs_form']) && ! collect($this->leadForms())->pluck('id')->contains((string) ($ad['lead_form_id'] ?? ''))) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.form_not_owned'));
        }

        // Behaviors (video / creator / hashtag interactions) only work when TikTok is the only placement.
        $hasActions = collect($targeting['interests'] ?? [])->contains(fn ($i) => in_array($i['type'] ?? '', ['VIDEO_INTERACTION', 'CREATOR_INTERACTION', 'HASHTAG_INTERACTION'], true));
        if ($hasActions && $this->placements($group) !== ['PLACEMENT_TIKTOK']) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.actions_tiktok_only'));
        }

        $types = collect($targeting['interests'] ?? [])->pluck('type');
        if ($types->contains('PURCHASE_INTENTION')) {
            if (! in_array($objective, self::PURCHASE_INTENT_OBJECTIVES, true)) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.purchase_objective'));
            }
            if ($types->contains('ADDITIONAL_INTEREST')) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.purchase_conflict'));
            }
        }

        if (! empty($targeting['device_model_ids']) && ! empty($targeting['device_price'])) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.device_conflict'));
        }

        if (($ad['source'] ?? 'upload') === 'spark') {
            if ($identity['type'] === 'TT_USER' && ! $identity['can_pull_video']) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.spark_not_allowed'));
            }
        } else {
            // Gallery media must be the seller's - checked before anything exists on TikTok.
            $this->asset($ad['media_id'] ?? null, ($ad['format'] ?? 'SINGLE_VIDEO') === 'SINGLE_IMAGE' ? 'image' : 'video');
            $this->asset($ad['cover_id'] ?? null, 'image', false);
        }

        return ['identity' => $identity, 'app' => $app];
    }

    private function objectiveOf(array $input): string
    {
        $objective = (string) ($input['objective'] ?? '');
        if (! isset(self::OBJECTIVES[$objective])) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.objective_unsupported'));
        }

        return $objective;
    }

    // ---- campaign -----------------------------------------------------

    private function campaignStep(AdBuilderRun $run, array $input): array
    {
        if ($remote = $run->result('campaign.remote')) {
            return ['remote' => $remote, 'local' => $run->result('campaign.local'), 'cbo' => (bool) $run->result('campaign.cbo')];
        }

        $objective = $this->objectiveOf($input);
        $spec = $input['campaign'];

        if (($spec['mode'] ?? 'new') === 'existing') {
            $existing = collect($this->campaigns())->firstWhere('id', (string) ($spec['id'] ?? ''));
            if (! $existing) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.campaign_not_owned'));
            }
            if ($existing['objective'] !== $objective) {
                throw new \InvalidArgumentException(__('tiktok_builder.server.campaign_objective', ['objective' => $existing['objective']]));
            }

            $local = AdCampaign::firstOrCreate(
                ['ad_campaign_id' => $existing['id'], 'platform' => 'tiktok'],
                ['user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $existing['name'], 'objective' => $objective, 'budget_mode' => $existing['budget_mode'], 'budget' => $existing['budget'], 'status' => false]
            );
            $run->remember('campaign', ['remote' => $existing['id'], 'local' => $local->id, 'reused' => true, 'cbo' => $existing['cbo']]);

            return ['remote' => $existing['id'], 'local' => $local->id, 'cbo' => $existing['cbo']];
        }

        $cbo = (bool) ($spec['budget_optimize_on'] ?? false);
        $budgetMode = $cbo ? (($spec['budget_mode'] ?? '') === 'BUDGET_MODE_TOTAL' ? 'BUDGET_MODE_TOTAL' : 'BUDGET_MODE_DAY') : 'BUDGET_MODE_INFINITE';
        $name = ($spec['name'] ?? null) ?: __('tiktok_builder.objectives.' . $objective . '.label') . ' - ' . now()->format('Y-m-d');

        $payload = array_filter([
            'campaign_name' => $name,
            'objective_type' => $objective,
            'budget_mode' => $budgetMode,
            'budget' => $cbo ? (float) ($spec['budget'] ?? 0) : null,
            'budget_optimize_on' => $cbo ?: null,
            'app_promotion_type' => $objective === 'APP_PROMOTION' ? ($spec['app_promotion_type'] ?? 'APP_INSTALL') : null,
            'special_industries' => ! empty($spec['special_industries']) ? array_values($spec['special_industries']) : null,
            'operation_status' => $this->status($input),
            // TikTok's own duplicate guard for a double submit within 10s.
            'request_id' => (string) (hexdec(substr(hash('crc32b', $run->idempotency_key . ':campaign'), 0, 8)) + 1),
        ], fn ($v) => $v !== null);

        $data = $this->api->post('campaign/create/', $payload);
        $id = (string) ($data['campaign_id'] ?? '');
        if ($id === '') {
            throw new TiktokApiException(__('tiktok_builder.server.no_id', ['object' => 'campaign']));
        }
        $run->remember('campaign', ['remote' => $id, 'reused' => false, 'cbo' => $cbo]);

        $local = AdCampaign::updateOrCreate(['ad_campaign_id' => $id, 'platform' => 'tiktok'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $name,
            'objective' => $objective, 'budget_mode' => $budgetMode, 'budget' => $cbo ? (float) ($spec['budget'] ?? 0) : null,
            'app_promotion_type' => $payload['app_promotion_type'] ?? null,
            'start_time' => $this->utc($input['adgroup']['schedule_start'] ?? null),
            'end_time' => $this->utc($input['adgroup']['schedule_end'] ?? null),
            'status' => false,
        ]);
        $run->remember('campaign.local', $local->id);

        return ['remote' => $id, 'local' => $local->id, 'cbo' => $cbo];
    }

    // ---- ad group -----------------------------------------------------

    private function adGroupStep(AdBuilderRun $run, array $input, array $campaign): array
    {
        if ($remote = $run->result('adgroup.remote')) {
            return ['remote' => $remote, 'local' => $run->result('adgroup.local')];
        }

        $payload = $this->adGroupPayload($input, $campaign);

        // A brand-new campaign can take a moment to propagate on TikTok's
        // side (seen live in TiktokAdService) - one short retry. An error
        // envelope means nothing was created, so this can't duplicate.
        try {
            $data = $this->api->post('adgroup/create/', $payload);
        } catch (TiktokApiException $e) {
            if ($run->result('campaign.reused') || $e->isAuthError() || $e->apiCode === 0) {
                throw $e;
            }
            sleep(2);
            $data = $this->api->post('adgroup/create/', $payload);
        }
        $id = (string) ($data['adgroup_id'] ?? '');
        if ($id === '') {
            throw new TiktokApiException(__('tiktok_builder.server.no_id', ['object' => 'ad group']));
        }
        $run->remember('adgroup', ['remote' => $id]);

        $local = AdAdGroup::updateOrCreate(['ad_adgroup_id' => $id, 'platform' => 'tiktok'], [
            'user_id' => $run->user_id,
            'social_account_id' => $this->api->account->id,
            'ad_campaign_id' => $campaign['local'],
            'name' => $payload['adgroup_name'],
            'objective' => $input['objective'],
            'promotion_type' => $payload['promotion_type'] ?? null,
            'promotion_target_type' => $payload['promotion_target_type'] ?? null,
            'placement_type' => $payload['placement_type'],
            'placements' => isset($payload['placements']) ? json_encode($payload['placements']) : null,
            'location_ids' => json_encode($payload['location_ids'] ?? []),
            'gender' => $payload['gender'] ?? 'GENDER_UNLIMITED',
            'languages' => json_encode($payload['languages'] ?? []),
            'age_groups' => json_encode($payload['age_groups'] ?? []),
            'budget_mode' => $payload['budget_mode'],
            'budget' => $payload['budget'] ?? null,
            'schedule_type' => $payload['schedule_type'],
            'schedule_start_time' => $payload['schedule_start_time'],
            'schedule_end_time' => $payload['schedule_end_time'] ?? null,
            'optimization_goal' => $payload['optimization_goal'],
            'billing_event' => $payload['billing_event'],
            'bid_type' => $payload['bid_type'],
            'bid_price' => $payload['bid_price'] ?? null,
            'conversion_bid_price' => $payload['conversion_bid_price'] ?? null,
            'pacing' => $payload['pacing'],
            'targeting_criteria' => json_encode(['targeting' => array_intersect_key($payload, array_flip(self::TARGETING_FIELDS)), 'local' => $input['adgroup']['targeting'] ?? []]),
            'status' => false,
        ]);
        $run->remember('adgroup.local', $local->id);

        return ['remote' => $id, 'local' => $local->id];
    }

    /** Targeting fields stored with the local ad group. */
    private const TARGETING_FIELDS = ['location_ids', 'zipcode_ids', 'languages', 'gender', 'age_groups', 'spending_power', 'household_income', 'audience_ids', 'excluded_audience_ids', 'interest_category_ids', 'interest_keyword_ids', 'purchase_intention_keyword_ids', 'actions', 'operating_systems', 'min_android_version', 'min_ios_version', 'device_model_ids', 'network_types', 'device_price_ranges', 'smart_audience_enabled', 'smart_interest_behavior_enabled'];

    /** The adgroup/create/ body (public for tests and for the review step). */
    public function adGroupPayload(array $input, array $campaign): array
    {
        $objective = $this->objectiveOf($input);
        $spec = self::OBJECTIVES[$objective];
        $g = $input['adgroup'];
        $goal = $g['optimization_goal'];
        $billing = $spec['goals'][$goal];
        $placements = $this->placements($g);

        $payload = [
            'campaign_id' => $campaign['remote'],
            'adgroup_name' => ($g['name'] ?? null) ?: __('tiktok_builder.defaults.adgroup', ['date' => now()->format('Y-m-d')]),
            'placement_type' => $placements ? 'PLACEMENT_TYPE_NORMAL' : 'PLACEMENT_TYPE_AUTOMATIC',
            'optimization_goal' => $goal,
            'billing_event' => $billing,
            'operation_status' => $this->status($input),
        ];
        if ($placements) {
            $payload['placements'] = $placements;
        }
        if ($promotion = $this->promotionType($objective, $input['_app_platform'] ?? null)) {
            $payload['promotion_type'] = $promotion;
        }
        if ($objective === 'LEAD_GENERATION') {
            $payload['promotion_target_type'] = 'INSTANT_PAGE';
        }

        // Budget: ignored by TikTok under CBO, so only sent without it.
        if (! $campaign['cbo']) {
            $payload['budget_mode'] = ($g['budget_mode'] ?? '') === 'BUDGET_MODE_TOTAL' ? 'BUDGET_MODE_TOTAL' : 'BUDGET_MODE_DAY';
            $payload['budget'] = (float) ($g['budget'] ?? 0);
        } else {
            $payload['budget_mode'] = 'BUDGET_MODE_DAY';
        }

        // Schedule (entered in the advertiser's time zone, sent as UTC).
        $payload['schedule_start_time'] = $this->utc($g['schedule_start'] ?? null) ?? now('UTC')->format('Y-m-d H:i:s');
        if (! empty($g['schedule_end']) || $payload['budget_mode'] === 'BUDGET_MODE_TOTAL') {
            $payload['schedule_type'] = 'SCHEDULE_START_END';
            $payload['schedule_end_time'] = $this->utc($g['schedule_end'] ?? null);
        } else {
            $payload['schedule_type'] = 'SCHEDULE_FROM_NOW';
        }
        if ($dayparting = $this->dayparting($g['dayparting'] ?? null)) {
            $payload['dayparting'] = $dayparting;
        }

        // Bidding: maximum delivery (no bid) or a cost cap / bid cap.
        $custom = ($g['bid_type'] ?? 'BID_TYPE_NO_BID') === 'BID_TYPE_CUSTOM' && (float) ($g['bid'] ?? 0) > 0;
        $payload['bid_type'] = $custom ? 'BID_TYPE_CUSTOM' : 'BID_TYPE_NO_BID';
        if ($custom) {
            $payload[$billing === 'OCPM' ? 'conversion_bid_price' : 'bid_price'] = (float) $g['bid'];
            if ($billing === 'CPV') {
                $payload['bid_display_mode'] = 'CPV';
            }
        }
        // No-bid doesn't support accelerated delivery.
        $payload['pacing'] = $custom && ($g['pacing'] ?? '') === 'PACING_MODE_FAST' ? 'PACING_MODE_FAST' : 'PACING_MODE_SMOOTH';

        if ($objective === 'WEB_CONVERSIONS') {
            $payload['pixel_id'] = (string) $g['pixel_id'];
            $payload['optimization_event'] = (string) $g['optimization_event'];
        }
        if ($objective === 'APP_PROMOTION') {
            $payload['app_id'] = (string) $g['app_id'];
        }
        if ($objective === 'REACH') {
            $payload['frequency'] = (int) ($g['frequency'] ?? 3);
            $payload['frequency_schedule'] = (int) ($g['frequency_schedule'] ?? 7);
        }
        if (in_array('PLACEMENT_TIKTOK', $placements ?: ['PLACEMENT_TIKTOK'], true)) {
            if (! empty($g['brand_safety_type']) && in_array($g['brand_safety_type'], self::BRAND_SAFETY, true)) {
                $payload['brand_safety_type'] = $g['brand_safety_type'];
            }
            foreach (['comment_disabled', 'video_download_disabled'] as $flag) {
                if (! empty($g[$flag])) {
                    $payload[$flag] = true;
                }
            }
            if (! empty($g['share_disabled'])) {
                $payload['share_disabled'] = true;
            }
        }
        if (array_key_exists('search_result_enabled', $g) && in_array($objective, ['APP_PROMOTION', 'WEB_CONVERSIONS', 'TRAFFIC', 'LEAD_GENERATION'], true)) {
            $payload['search_result_enabled'] = (bool) $g['search_result_enabled'];
        }

        return $payload + $this->targeting($g['targeting'] ?? [], $objective, $input['_app_platform'] ?? null);
    }

    /** adgroup targeting fields, from the wizard's targeting block. */
    public function targeting(array $t, string $objective, ?string $appPlatform = null): array
    {
        $out = [];

        foreach ($t['locations'] ?? [] as $loc) {
            $key = ($loc['type'] ?? '') === 'ZIP_CODE' ? 'zipcode_ids' : 'location_ids';
            $out[$key][] = (string) $loc['id'];
        }
        if (! empty($t['languages'])) {
            $out['languages'] = array_values(array_map('strval', $t['languages']));
        }
        $out['gender'] = in_array($t['gender'] ?? '', self::GENDERS, true) ? $t['gender'] : 'GENDER_UNLIMITED';
        if (! empty($t['age_groups'])) {
            $out['age_groups'] = array_values(array_intersect($t['age_groups'], self::AGE_GROUPS));
        }
        if (($t['spending_power'] ?? 'ALL') === 'HIGH') {
            $out['spending_power'] = 'HIGH';
        }
        if (! empty($t['household_income'])) {
            $out['household_income'] = array_values(array_intersect($t['household_income'], self::HOUSEHOLD_INCOME));
        }
        foreach (['audience_ids', 'excluded_audience_ids'] as $field) {
            if (! empty($t[$field])) {
                $out[$field] = array_values(array_map('strval', $t[$field]));
            }
        }

        // Interests & behaviors -> the field each group maps to.
        $byType = collect($t['interests'] ?? [])->groupBy('type')->map(fn ($items) => $items->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all());
        $fieldFor = ['GENERAL_INTEREST' => 'interest_category_ids', 'ADDITIONAL_INTEREST' => 'interest_keyword_ids', 'PURCHASE_INTENTION' => 'purchase_intention_keyword_ids'];
        foreach ($fieldFor as $type => $field) {
            if (! empty($byType[$type])) {
                $out[$field] = $byType[$type];
            }
        }
        $period = in_array((int) ($t['action_period'] ?? 15), self::ACTION_PERIODS, true) ? (int) ($t['action_period'] ?? 15) : 15;
        $videoActions = array_values(array_intersect($t['video_actions'] ?? self::VIDEO_ACTIONS, self::VIDEO_ACTIONS)) ?: self::VIDEO_ACTIONS;
        $scenes = [
            'VIDEO_INTERACTION' => ['VIDEO_RELATED', $videoActions, $period],
            'CREATOR_INTERACTION' => ['CREATOR_RELATED', ['FOLLOWING', 'VIEW_HOMEPAGE'], 0],
            'HASHTAG_INTERACTION' => ['HASHTAG_RELATED', ['VIEW_HASHTAG'], 0],
        ];
        foreach ($scenes as $type => [$scene, $actions, $days]) {
            if (! empty($byType[$type])) {
                $out['actions'][] = ['action_scene' => $scene, 'action_period' => $days, 'video_user_actions' => $actions, 'action_category_ids' => $byType[$type]];
            }
        }

        // Device. An app objective must target the app's own OS.
        $os = $appPlatform ? [$appPlatform] : array_values(array_intersect($t['operating_systems'] ?? [], ['ANDROID', 'IOS']));
        if (count($os) === 1) {
            $out['operating_systems'] = $os;
            if ($os[0] === 'ANDROID' && ! empty($t['min_android_version'])) {
                $out['min_android_version'] = (string) $t['min_android_version'];
            }
            if ($os[0] === 'IOS' && ! empty($t['min_ios_version'])) {
                $out['min_ios_version'] = (string) $t['min_ios_version'];
            }
        }
        if (! empty($t['device_model_ids'])) {
            $out['device_model_ids'] = array_values(array_map(fn ($d) => (string) (is_array($d) ? $d['id'] : $d), $t['device_model_ids']));
        } elseif (! empty($t['device_price'])) {
            // Multiples of 50; 10000 = "1000+".
            $min = max(0, (int) (floor(((int) $t['device_price']['min']) / 50) * 50));
            $max = (int) $t['device_price']['max'] >= 1000 ? 10000 : (int) (ceil(((int) $t['device_price']['max']) / 50) * 50);
            if ($max > $min) {
                $out['device_price_ranges'] = [$min, $max];
            }
        }
        if (! empty($t['network_types'])) {
            $out['network_types'] = array_values(array_intersect($t['network_types'], self::NETWORK_TYPES));
        }

        // Audience expansion.
        if (! empty($t['smart_audience'])) {
            $out['smart_audience_enabled'] = true;
        }
        if (! empty($t['smart_interest_behavior'])) {
            $out['smart_interest_behavior_enabled'] = true;
        }

        return $out;
    }

    /** Manual placements, or [] for automatic. */
    private function placements(array $group): array
    {
        if (($group['placement_type'] ?? 'AUTOMATIC') !== 'NORMAL') {
            return [];
        }

        return array_values(array_intersect(self::PLACEMENTS, $group['placements'] ?? [])) ?: ['PLACEMENT_TIKTOK'];
    }

    private function promotionType(string $objective, ?string $appPlatform): ?string
    {
        $type = self::OBJECTIVES[$objective]['promotion_type'];

        return $type === 'APP' ? ($appPlatform === 'IOS' ? 'APP_IOS' : 'APP_ANDROID') : $type;
    }

    /** Days (0 = Monday) + hour window -> TikTok's 336-slot string; null = all day, every day. */
    private function dayparting(?array $spec): ?string
    {
        if (! $spec || empty($spec['enabled'])) {
            return null;
        }
        $days = array_map('intval', $spec['days'] ?? []);
        $from = max(0, min(24, (int) ($spec['from'] ?? 0)));
        $to = max(0, min(24, (int) ($spec['to'] ?? 24)));
        if (! $days || $to <= $from) {
            return null;
        }

        $out = '';
        for ($d = 0; $d < 7; $d++) {
            for ($slot = 0; $slot < 48; $slot++) {
                $out .= in_array($d, $days, true) && $slot >= $from * 2 && $slot < $to * 2 ? '1' : '0';
            }
        }

        return str_contains($out, '0') ? $out : null;
    }

    private function status(array $input): string
    {
        return ($input['ad']['status'] ?? 'DISABLE') === 'ENABLE' ? 'ENABLE' : 'DISABLE';
    }

    /** "Y-m-d H:i" in the advertiser's time zone -> UTC "Y-m-d H:i:s". */
    private function utc(?string $local): ?string
    {
        if (! $local) {
            return null;
        }
        $tz = rescue(fn () => $this->advertiser()['display_timezone'] ?? $this->advertiser()['timezone'] ?? 'UTC', 'UTC', false) ?: 'UTC';

        return Carbon::parse($local, $tz)->utc()->format('Y-m-d H:i:s');
    }

    // ---- ad -----------------------------------------------------------

    private function adStep(AdBuilderRun $run, array $input, array $campaign, array $adGroup, array $refs): void
    {
        if ($run->result('ad.remote')) {
            return;
        }

        $creative = $this->creative($run, $input, $refs['identity']);
        $status = $this->status($input);
        $creative['operation_status'] = $status;

        $data = $this->api->post('ad/create/', ['adgroup_id' => $adGroup['remote'], 'creatives' => [$creative]]);
        $adId = (string) ($data['ad_ids'][0] ?? $data['ad_id'] ?? '');
        if ($adId === '') {
            throw new TiktokApiException(__('tiktok_builder.server.no_id', ['object' => 'ad']));
        }
        $run->remember('ad', ['remote' => $adId, 'status' => $status]);

        // TikTok has no separate creative object: the local creative row records what was sent.
        $localCreative = AdCreative::updateOrCreate(['ad_creative_id' => $adId, 'platform' => 'tiktok'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id,
            'ad_campaign_id' => $campaign['local'], 'ad_adgroup_id' => $adGroup['local'],
            'name' => $creative['ad_name'], 'ad_format' => $creative['ad_format'], 'message' => $creative['ad_text'] ?? null,
            'page_id' => $creative['identity_id'], 'call_to_action' => $creative['call_to_action'] ?? null,
            'url' => $creative['landing_page_url'] ?? null, 'type' => $creative['ad_format'] === 'SINGLE_IMAGE' ? 'IMAGE' : 'VIDEO',
        ]);
        foreach (array_unique($this->creativeMedia) as $mediaId) {
            AdCreativeMedia::firstOrCreate(['ad_media_id' => $mediaId, 'ad_creative_id' => $localCreative->id]);
        }

        $local = Ad::updateOrCreate(['ad_id' => $adId, 'platform' => 'tiktok'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id,
            'ad_campaign_id' => $campaign['local'], 'ad_adgroup_id' => $adGroup['local'], 'ad_creative_id' => $localCreative->id,
            'name' => $creative['ad_name'], 'status' => $status === 'ENABLE',
            'call_to_action' => $creative['call_to_action'] ?? null, 'ad_format' => $creative['ad_format'],
        ]);
        $run->remember('ad.local', $local->id);
    }

    /** One ad/create/ creative: Spark Ad (existing TikTok post) or uploaded video / image. */
    public function creative(AdBuilderRun $run, array $input, array $identity): array
    {
        $objective = $this->objectiveOf($input);
        $ad = $input['ad'];
        $spec = self::OBJECTIVES[$objective];

        $creative = [
            'ad_name' => ($ad['name'] ?? null) ?: ($input['adgroup']['name'] ?? 'TikTok') . ' - Ad',
            'identity_id' => $identity['id'],
            'identity_type' => $identity['type'],
        ];
        if ($identity['type'] === 'BC_AUTH_TT' && $identity['bc_id']) {
            $creative['identity_authorized_bc_id'] = $identity['bc_id'];
        }

        if (($ad['source'] ?? 'upload') === 'spark') {
            $creative['ad_format'] = 'SINGLE_VIDEO';
            $creative['tiktok_item_id'] = (string) $ad['tiktok_item_id'];
            if (! empty($ad['text'])) {
                $creative['ad_text'] = $ad['text'];
            }
        } else {
            $creative['ad_text'] = (string) ($ad['text'] ?? '');
            $creative['display_name'] = mb_substr((string) $identity['name'], 0, 40);
            if (($ad['format'] ?? 'SINGLE_VIDEO') === 'SINGLE_IMAGE') {
                $asset = $this->asset($ad['media_id'] ?? null, 'image');
                $creative['ad_format'] = 'SINGLE_IMAGE';
                $creative['image_ids'] = [$this->uploadImage($run, $asset)];
                // TikTok turns a static image into a post and needs music for it (confirmed live).
                if ($music = $this->defaultMusicId()) {
                    $creative['music_id'] = $music;
                }
            } else {
                $video = $this->asset($ad['media_id'] ?? null, 'video');
                $creative['ad_format'] = 'SINGLE_VIDEO';
                $creative['video_id'] = $this->uploadVideo($run, $video);
                $cover = $this->asset($ad['cover_id'] ?? null, 'image', false);
                $coverId = $cover ? $this->uploadImage($run, $cover) : $this->autoCover($run, $creative['video_id']);
                if ($coverId) {
                    $creative['image_ids'] = [$coverId];
                }
            }
        }

        if ($objective === 'ENGAGEMENT' && $input['adgroup']['optimization_goal'] === 'PAGE_VISIT') {
            $creative['tiktok_page_category'] = 'PROFILE_PAGE';
        } elseif (! empty($spec['needs_form'])) {
            $creative['page_id'] = (int) $ad['lead_form_id'];
            $creative['call_to_action'] = ($ad['cta'] ?? null) ?: 'SIGN_UP';
        } elseif ($spec['url'] !== 'none' && ! empty($ad['url'])) {
            $creative['landing_page_url'] = $ad['url'];
            $creative['call_to_action'] = ($ad['cta'] ?? null) ?: 'LEARN_MORE';
        } elseif ($objective === 'APP_PROMOTION') {
            $creative['call_to_action'] = ($ad['cta'] ?? null) ?: 'DOWNLOAD_NOW';
        }

        if (! empty($ad['tracking_pixel_id'])) {
            $creative['tracking_pixel_id'] = (int) $ad['tracking_pixel_id'];
        } elseif (! empty($input['adgroup']['pixel_id'])) {
            $creative['tracking_pixel_id'] = (int) $input['adgroup']['pixel_id'];
        }
        foreach (['impression_tracking_url', 'click_tracking_url'] as $field) {
            if (! empty($ad[$field])) {
                $creative[$field] = $ad[$field];
            }
        }

        return $creative;
    }

    private function asset($assetId, string $type, bool $required = true): ?MediaAsset
    {
        $asset = $assetId ? MediaAsset::ownedBy((int) $this->api->account->user_id)->find((int) $assetId) : null;
        if (! $asset) {
            if ($required) {
                throw new \InvalidArgumentException(__($type === 'video' ? 'tiktok_builder.server.choose_video' : 'tiktok_builder.server.choose_image'));
            }

            return null;
        }
        if ($asset->type !== $type) {
            throw new \InvalidArgumentException(__($type === 'video' ? 'tiktok_builder.server.not_video' : 'tiktok_builder.server.not_image', ['name' => $asset->original_name]));
        }

        return $asset;
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
            throw new \InvalidArgumentException(__('tiktok_builder.server.unreadable', ['name' => $asset->original_name]));
        }

        return $response->body();
    }

    private function fileName(MediaAsset $asset): string
    {
        $ext = $asset->extension ?: pathinfo((string) $asset->original_name, PATHINFO_EXTENSION) ?: 'bin';

        return 'syncora_' . $asset->id . '_' . substr(md5((string) $asset->path . $asset->id), 0, 8) . '.' . strtolower($ext);
    }

    private function uploadImage(AdBuilderRun $run, MediaAsset $asset): string
    {
        if ($id = $run->result("media.image_{$asset->id}")) {
            $this->rememberMedia($run, $id, $asset, 'IMAGE');

            return $id;
        }
        $bytes = $this->bytes($asset);
        $data = $this->api->upload('file/image/ad/upload/', [
            'upload_type' => 'UPLOAD_BY_FILE', 'image_signature' => md5($bytes), 'file_name' => $this->fileName($asset),
        ], 'image_file', $bytes, $this->fileName($asset));
        $id = (string) ($data['image_id'] ?? $data[0]['image_id'] ?? '');
        if ($id === '') {
            throw new TiktokApiException(__('tiktok_builder.server.image_rejected', ['name' => $asset->original_name]));
        }
        $run->remember("media.image_{$asset->id}", $id);
        $this->rememberMedia($run, $id, $asset, 'IMAGE');

        return $id;
    }

    private function uploadVideo(AdBuilderRun $run, MediaAsset $asset): string
    {
        if ($id = $run->result("media.video_{$asset->id}")) {
            $this->rememberMedia($run, $id, $asset, 'VIDEO');

            return $id;
        }
        $bytes = $this->bytes($asset);
        $data = $this->api->upload('file/video/ad/upload/', [
            'upload_type' => 'UPLOAD_BY_FILE', 'video_signature' => md5($bytes), 'file_name' => $this->fileName($asset),
        ], 'video_file', $bytes, $this->fileName($asset));
        // file/video/ad/upload/ answers with a list (confirmed live).
        $id = (string) ($data[0]['video_id'] ?? $data['video_id'] ?? '');
        if ($id === '') {
            throw new TiktokApiException(__('tiktok_builder.server.video_rejected', ['name' => $asset->original_name]));
        }
        $run->remember("media.video_{$asset->id}", $id);
        $this->rememberMedia($run, $id, $asset, 'VIDEO');

        return $id;
    }

    /**
     * A video ad needs a cover image_id. Without one picked, TikTok's own
     * preview frame is downloaded and uploaded as an image (what
     * TiktokAdService does, confirmed live). Null if that isn't possible:
     * TikTok then says the cover is missing.
     */
    private function autoCover(AdBuilderRun $run, string $videoId): ?string
    {
        if ($id = $run->result("media.cover_{$videoId}")) {
            return $id;
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $info = $this->api->get('file/video/ad/info/', ['video_ids' => [$videoId]]);
                $url = $info['list'][0]['video_cover_url'] ?? null;
                if ($url && ($image = Http::timeout(60)->get($url)) && $image->successful()) {
                    $data = $this->api->upload('file/image/ad/upload/', [
                        'upload_type' => 'UPLOAD_BY_FILE', 'image_signature' => md5($image->body()), 'file_name' => "cover_{$videoId}.jpg",
                    ], 'image_file', $image->body(), "cover_{$videoId}.jpg");
                    if ($id = (string) ($data['image_id'] ?? '')) {
                        $run->remember("media.cover_{$videoId}", $id);

                        return $id;
                    }
                }
            } catch (TiktokApiException) {
                // retried once below
            }
            usleep(1_500_000);
        }

        return null;
    }

    private function defaultMusicId(): ?string
    {
        try {
            $data = $this->api->get('file/music/get/', ['page_size' => 1]);

            return isset($data['musics'][0]['music_id']) ? (string) $data['musics'][0]['music_id'] : null;
        } catch (TiktokApiException) {
            return null;
        }
    }

    private function rememberMedia(AdBuilderRun $run, string $tiktokId, MediaAsset $asset, string $type): void
    {
        $media = AdMedia::updateOrCreate(
            ['ad_media_id' => $tiktokId, 'platform' => 'tiktok', 'ad_campaign_id' => $run->result('campaign.local')],
            [
                'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id,
                'name' => $asset->original_name, 'file_name' => $asset->original_name, 'url' => $asset->url, 'download_link' => $asset->url,
                'type' => $type, 'status' => false, 'signature' => $tiktokId, 'file_id' => $tiktokId, 'upload_by_type' => 'UPLOAD_BY_FILE',
                'ad_format' => $type === 'VIDEO' ? 'SINGLE_VIDEO' : 'SINGLE_IMAGE', 'thumbnail_url' => $type === 'VIDEO' ? $asset->thumbnail_url : null,
            ]
        );
        $this->creativeMedia[] = $media->id;
    }

    // ------------------------------------------------------------------
    // Ownership
    // ------------------------------------------------------------------

    private function ownedIdentity(string $id, string $type): array
    {
        $identity = collect($this->identities())->first(fn ($i) => $i['id'] === $id && (! $type || $i['type'] === $type));
        if (! $identity) {
            throw new \InvalidArgumentException(__('tiktok_builder.server.identity_not_owned'));
        }

        return $identity;
    }
}
