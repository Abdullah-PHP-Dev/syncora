<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\MediaAsset;
use App\Support\Ads\XAdsApi;
use App\Support\Ads\XApiException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * X campaign builder (ads/x/campaigns/create): campaign (new or existing)
 * -> line item -> targeting criteria -> Promoted Tweet(s), through the X
 * Ads API v12.
 *
 * - Lookups (funding instruments, promotable users, campaigns, Tweets,
 *   custom audiences, placements and every targeting option) are read
 *   live from X for the seller's own ad account.
 * - The line item is always created PAUSED and only activated after its
 *   targeting and Promoted Tweets exist - X serves a line item without
 *   targeting criteria to everyone worldwide.
 * - submit() is idempotent per key (AdBuilderRun, platform "x"): each X
 *   object is recorded the moment it exists, so a retry continues from the
 *   failed step instead of creating duplicates.
 * - Enum values follow the v12 reference (docs.x.com/x-ads-api); age
 *   buckets follow X's official Ads SDK (twitter_ads.enum.AGE_BUCKET).
 */
class XCampaignBuilder
{
    /**
     * Objectives offered, with their optimization goals and bid strategies
     * (MAX is not available for REACH/FOLLOWERS; TARGET only for REACH,
     * FOLLOWERS and WEBSITE_CLICKS - POST line_items reference).
     */
    public const OBJECTIVES = [
        'REACH' => ['icon' => 'bx-broadcast', 'goals' => ['MAX_REACH', 'REACH_WITH_ENGAGEMENT'], 'bids' => ['AUTO', 'TARGET'], 'frequency' => true],
        'ENGAGEMENTS' => ['icon' => 'bx-like', 'goals' => ['ENGAGEMENT'], 'bids' => ['AUTO', 'MAX'], 'frequency' => true],
        'VIDEO_VIEWS' => ['icon' => 'bx-play-circle', 'goals' => ['VIDEO_VIEW', 'VIEW_3S_100PCT', 'VIEW_6S', 'VIEW_15S'], 'bids' => ['AUTO', 'MAX'], 'frequency' => true, 'needs_video' => true],
        'WEBSITE_CLICKS' => ['icon' => 'bx-mouse-alt', 'goals' => ['LINK_CLICKS', 'SITE_VISITS', 'WEBSITE_CONVERSIONS'], 'bids' => ['AUTO', 'MAX', 'TARGET'], 'needs_url' => true],
        'FOLLOWERS' => ['icon' => 'bx-user-plus', 'goals' => ['FOLLOWERS'], 'bids' => ['AUTO', 'TARGET']],
        'APP_INSTALLS' => ['icon' => 'bx-download', 'goals' => ['APP_INSTALLS', 'APP_CLICKS', 'APP_PURCHASES'], 'bids' => ['AUTO', 'MAX'], 'needs_app' => true],
        'APP_ENGAGEMENTS' => ['icon' => 'bx-mobile-alt', 'goals' => ['APP_CLICKS', 'APP_INSTALLS'], 'bids' => ['AUTO', 'MAX'], 'needs_app' => true],
    ];

    /** Goals whose billing unit can be chosen (pay_by). */
    public const PAY_BY = ['LINK_CLICKS' => ['LINK_CLICK', 'IMPRESSION'], 'APP_INSTALLS' => ['APP_CLICK', 'IMPRESSION']];

    /** X placements for Promoted Tweets (Audience Network / TAP placements need IAB categories and aren't offered). */
    public const PLACEMENTS = ['ALL_ON_TWITTER', 'TWITTER_TIMELINE', 'TWITTER_SEARCH', 'TWITTER_PROFILE', 'TWITTER_REPLIES'];

    public const AGE_BUCKETS = ['AGE_13_TO_24', 'AGE_13_TO_34', 'AGE_13_TO_49', 'AGE_13_TO_54', 'AGE_OVER_13', 'AGE_18_TO_34', 'AGE_18_TO_49', 'AGE_18_TO_54', 'AGE_OVER_18', 'AGE_21_TO_34', 'AGE_21_TO_49', 'AGE_21_TO_54', 'AGE_OVER_21', 'AGE_25_TO_49', 'AGE_25_TO_54', 'AGE_OVER_25', 'AGE_35_TO_49', 'AGE_35_TO_54', 'AGE_OVER_35', 'AGE_OVER_50'];

    public const GENDERS = ['ANY', 'MALE', 'FEMALE'];

    public const KEYWORD_TYPES = ['BROAD_KEYWORD', 'PHRASE_KEYWORD', 'EXACT_KEYWORD'];

    public const ENGAGEMENT_TYPES = ['IMPRESSION', 'ENGAGEMENT', 'VIDEO_VIEW', 'VIDEO_VIEW_PARTIAL', 'VIDEO_VIEW_COMPLETE'];

    public const AUDIENCE_EXPANSION = ['BROAD', 'DEFINED', 'EXPANDED'];

    public const EVENT_TYPES = ['CONFERENCE', 'HOLIDAY', 'MUSIC_AND_ENTERTAINMENT', 'OTHER', 'POLITICS', 'RECURRING', 'SPORTS'];

    public const CONVERSATION_TYPES = ['ACTORS', 'ATHLETES', 'BOOK_GENRES', 'BOOKS', 'BRAND_CATEGORIES', 'BRANDS', 'CELEBRITIES', 'COACHES', 'DIGITAL_CREATORS', 'ENTERTAINMENT_BRANDS', 'ENTERTAINMENT_PERSONALITIES', 'FICTIONAL_CHARACTERS', 'JOURNALISTS', 'LIFESTYLES', 'MOVIE_GENRES', 'MOVIES', 'MUSIC_GENRES', 'MUSICIANS', 'NEWS_STORIES', 'NEWS', 'PERSONS', 'PLACES', 'PODCASTS', 'POLITICAL_AFFILIATIONS', 'POLITICIANS', 'PRODUCTS', 'RADIO_STATIONS', 'SPORTS_LEAGUES', 'SPORTS_PERSONALITIES', 'SPORTS_TEAMS', 'SPORTS', 'TRENDS', 'TV_SHOWS', 'VIDEO_GAME_PLATFORMS', 'VIDEO_GAME_PUBLISHERS', 'VIDEO_GAMES'];

    public const CONVERSATION_SETTINGS = ['EVERYONE', 'FOLLOWING', 'MENTIONED_USERS'];

    /** BUTTON label values for app cards (POST cards reference). */
    public const APP_CARD_LABELS = ['INSTALL', 'OPEN', 'PLAY', 'SHOP', 'BOOK', 'CONNECT', 'ORDER'];

    public const FREQUENCY_DAYS = [1, 7, 30];

    /** Targeting types X Search can't apply (Targeting options by placement). */
    public const NOT_ON_SEARCH = ['interests', 'conversations', 'followers', 'similar_followers', 'engagement'];

    /** Primary targeting types (audience_estimate needs at least one). */
    private const PRIMARY = ['FOLLOWERS_OF_USER', 'SIMILAR_TO_FOLLOWERS_OF_USER', 'CUSTOM_AUDIENCE', 'CUSTOM_AUDIENCE_EXPANDED', 'INTEREST', 'BROAD_KEYWORD', 'PHRASE_KEYWORD', 'EXACT_KEYWORD', 'CONVERSATION', 'EVENT'];

    private array $memo = [];

    public function __construct(private XAdsApi $api)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $api = XAdsApi::forUser($userId);

        return $api ? new self($api) : null;
    }

    public function api(): XAdsApi
    {
        return $this->api;
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public function bootstrap(): array
    {
        $info = $this->info();
        $funding = $this->fundingInstruments();

        return [
            'account' => [
                'id' => $this->api->accountId(),
                'local_id' => $this->api->account->id,
                'name' => $info['name'] ?? $this->api->account->name,
                'timezone' => $info['timezone'] ?? $this->api->account->metadata['timezone'] ?? 'UTC',
                'approval_status' => $info['approval_status'] ?? null,
                'active' => ($info['approval_status'] ?? 'ACCEPTED') === 'ACCEPTED' && ! ($info['deleted'] ?? false),
                'currency' => collect($funding)->firstWhere('usable', true)['currency'] ?? ($funding[0]['currency'] ?? 'USD'),
            ],
            'funding_instruments' => $funding,
            'promotable_users' => $this->promotableUsers(),
            'objectives' => self::OBJECTIVES,
            'options' => [
                'placements' => $this->placements(),
                'pay_by' => self::PAY_BY,
                'age_buckets' => self::AGE_BUCKETS,
                'genders' => self::GENDERS,
                'keyword_types' => self::KEYWORD_TYPES,
                'engagement_types' => self::ENGAGEMENT_TYPES,
                'audience_expansion' => self::AUDIENCE_EXPANSION,
                'event_types' => self::EVENT_TYPES,
                'conversation_types' => self::CONVERSATION_TYPES,
                'conversation_settings' => self::CONVERSATION_SETTINGS,
                'app_card_labels' => self::APP_CARD_LABELS,
                'frequency_days' => self::FREQUENCY_DAYS,
                'not_on_search' => self::NOT_ON_SEARCH,
            ],
        ];
    }

    /** GET accounts/:id (name, time zone, approval status), cached briefly. */
    private function info(): array
    {
        return $this->memo['info'] ??= Cache::remember($this->cacheKey('info'), 600, fn () => $this->api->get($this->api->path())['data'] ?? []);
    }

    /** Funding instruments; only ACTIVE, fundable ones are usable for a new campaign. */
    public function fundingInstruments(): array
    {
        return $this->memo['funding'] ??= Cache::remember($this->cacheKey('funding'), 300, fn () => collect($this->api->all($this->api->path('funding_instruments'), ['count' => 200], 3))
            ->reject(fn ($f) => $f['deleted'] ?? false)
            ->map(fn ($f) => [
                'id' => (string) $f['id'],
                'name' => $f['description'] ?? $f['name'] ?? $f['type'] ?? $f['id'],
                'type' => $f['type'] ?? null,
                'currency' => $f['currency'] ?? 'USD',
                'usable' => ($f['entity_status'] ?? 'ACTIVE') === 'ACTIVE' && ($f['able_to_fund'] ?? true) !== false,
                'reasons' => array_values($f['reasons_not_able_to_fund'] ?? []),
                'end_time' => $f['end_time'] ?? null,
            ])->values()->all());
    }

    /** Users whose Tweets this account can promote (FULL = the account holder). */
    public function promotableUsers(): array
    {
        return $this->memo['users'] ??= Cache::remember($this->cacheKey('promotable_users'), 600, function () {
            $ownId = $this->api->userId();
            $handle = $this->api->account->metadata['screen_name'] ?? null;

            return collect($this->api->all($this->api->path('promotable_users'), ['count' => 200], 2))
                ->reject(fn ($u) => $u['deleted'] ?? false)
                ->map(fn ($u) => [
                    'id' => (string) $u['user_id'],
                    'type' => $u['promotable_user_type'] ?? 'FULL',
                    'handle' => (string) $u['user_id'] === $ownId ? $handle : null,
                ])->values()->all();
        });
    }

    /** Valid PROMOTED_TWEETS placement combinations, read from X (GET line_items/placements). */
    public function placements(): array
    {
        return Cache::remember('x-builder:placements', 86400, function () {
            $combos = collect($this->api->get('line_items/placements', ['product_type' => 'PROMOTED_TWEETS'])['data'][0]['placements'] ?? [])
                ->map(fn ($c) => array_values((array) $c))
                ->filter(fn ($c) => ! array_diff($c, self::PLACEMENTS))
                ->map(function ($c) {
                    sort($c);

                    return $c;
                })->values();

            return [
                'combinations' => $combos->all(),
                'single' => $combos->flatten()->unique()->reject(fn ($p) => $p === 'ALL_ON_TWITTER')->values()->all(),
            ];
        });
    }

    /** Campaigns of this account, with the objective their line items use (a campaign has one objective). */
    public function campaigns(?string $search = null): array
    {
        $lineItems = collect($this->api->all($this->api->path('line_items'), ['count' => 1000], 5));
        $objectives = $lineItems->groupBy('campaign_id')->map(fn ($items) => $items->first()['objective'] ?? null);
        $products = $lineItems->groupBy('campaign_id')->map(fn ($items) => $items->first()['product_type'] ?? null);

        return collect($this->api->all($this->api->path('campaigns'), array_filter(['count' => 1000, 'q' => $search]), 5))
            ->reject(fn ($c) => $c['deleted'] ?? false)
            ->map(fn ($c) => [
                'id' => (string) $c['id'],
                'name' => $c['name'] ?? $c['id'],
                'status' => $c['entity_status'] ?? null,
                'funding_instrument_id' => $c['funding_instrument_id'] ?? null,
                'daily_budget' => isset($c['daily_budget_amount_local_micro']) ? $c['daily_budget_amount_local_micro'] / 1e6 : null,
                'total_budget' => isset($c['total_budget_amount_local_micro']) ? $c['total_budget_amount_local_micro'] / 1e6 : null,
                'currency' => $c['currency'] ?? null,
                'objective' => $objectives[$c['id']] ?? null,
                'supported' => ! isset($products[$c['id']]) || $products[$c['id']] === 'PROMOTED_TWEETS',
            ])
            ->filter(fn ($c) => $c['objective'] === null || isset(self::OBJECTIVES[$c['objective']]))
            ->sortByDesc(fn ($c) => $c['status'] === 'ACTIVE')->values()->all();
    }

    /** Published Tweets (organic and promoted-only) of a promotable user, newest first. */
    public function tweets(string $userId, ?string $cursor = null): array
    {
        $this->ownedUser($userId);
        $envelope = $this->api->get($this->api->path('tweets'), array_filter([
            'tweet_type' => 'PUBLISHED', 'timeline_type' => 'ALL', 'user_id' => $userId, 'count' => 40, 'cursor' => $cursor, 'trim_user' => true,
        ]));

        return [
            'tweets' => collect($envelope['data'] ?? [])->map(fn ($t) => $this->presentTweet($t))->values()->all(),
            'cursor' => $envelope['next_cursor'] ?? null,
        ];
    }

    private function presentTweet(array $t): array
    {
        $media = $t['extended_entities']['media'] ?? $t['entities']['media'] ?? [];
        $first = $media[0] ?? [];

        return [
            'id' => (string) ($t['id_str'] ?? $t['id']),
            'text' => $t['full_text'] ?? $t['text'] ?? '',
            'name' => $t['name'] ?? null,
            'created_at' => $t['created_at'] ?? null,
            'nullcast' => ($t['scopes']['followers'] ?? true) === false,
            'media_type' => $first['type'] ?? null,
            'thumbnail' => $first['media_url_https'] ?? null,
            'card' => $t['card_uri'] ?? null,
        ];
    }

    /** Custom (tailored) audiences: lists, website visitors, app users, engagers... */
    public function audiences(): array
    {
        return $this->memo['audiences'] ??= collect($this->api->all($this->api->path('custom_audiences'), ['count' => 1000], 3))
            ->reject(fn ($a) => $a['deleted'] ?? false)
            ->map(fn ($a) => [
                'id' => (string) $a['id'],
                'name' => $a['name'] ?? $a['id'],
                'type' => $a['audience_type'] ?? null,
                'size' => $a['audience_size'] ?? null,
                'usable' => (bool) ($a['targetable'] ?? false),
                'reasons' => array_values($a['reasons_not_targetable'] ?? []),
            ])->sortByDesc('usable')->values()->all();
    }

    public function locations(string $query, ?string $type = null, ?string $countryCode = null): array
    {
        $data = $this->api->get('targeting_criteria/locations', array_filter(['q' => $query, 'location_type' => $type, 'country_code' => $countryCode, 'count' => 50]))['data'] ?? [];

        return collect($data)->map(fn ($l) => [
            'id' => (string) $l['targeting_value'],
            'name' => $l['name'],
            'type' => $l['location_type'] ?? null,
            'country_code' => $l['country_code'] ?? null,
        ])->values()->all();
    }

    public function languages(): array
    {
        return Cache::remember('x-builder:languages', 86400, fn () => $this->options('targeting_criteria/languages'));
    }

    public function interests(string $query): array
    {
        $all = Cache::remember('x-builder:interests', 86400, fn () => $this->options('targeting_criteria/interests', ['count' => 1000]));

        return $this->filter($all, $query);
    }

    public function conversations(string $query, ?string $type = null): array
    {
        $all = Cache::remember('x-builder:conversations', 86400, fn () => collect($this->api->all('targeting_criteria/conversations', ['count' => 1000], 20))
            ->map(fn ($c) => ['id' => (string) $c['targeting_value'], 'name' => $c['name'], 'type' => $c['conversation_type'] ?? null])->values()->all());

        return $this->filter(array_values(array_filter($all, fn ($c) => ! $type || $c['type'] === $type)), $query);
    }

    public function events(string $type, ?string $countryCode = null): array
    {
        $data = $this->api->all('targeting_criteria/events', array_filter([
            'event_types' => $type, 'country_codes' => $countryCode, 'count' => 200,
            'start_time' => now()->startOfDay()->toIso8601ZuluString(),
        ]), 2);

        return collect($data)->map(fn ($e) => [
            'id' => (string) $e['targeting_value'],
            'name' => $e['name'],
            'type' => $e['event_type'] ?? $type,
            'start_time' => $e['start_time'] ?? null,
            'end_time' => $e['end_time'] ?? null,
            'country_code' => $e['country_code'] ?? null,
            'global' => (bool) ($e['is_global'] ?? false),
        ])->values()->all();
    }

    public function platforms(): array
    {
        return Cache::remember('x-builder:platforms', 86400, fn () => $this->options('targeting_criteria/platforms'));
    }

    public function platformVersions(): array
    {
        return Cache::remember('x-builder:platform_versions', 86400, fn () => collect($this->api->get('targeting_criteria/platform_versions')['data'] ?? [])
            ->map(fn ($v) => ['id' => (string) $v['targeting_value'], 'name' => trim(($v['os_type'] ?? '') . ' ' . ($v['number'] ?? '') . (! empty($v['name']) ? ' (' . $v['name'] . ')' : '')), 'os' => $v['os_type'] ?? null, 'number' => $v['number'] ?? null])
            ->values()->all());
    }

    public function devices(string $query): array
    {
        return collect($this->api->get('targeting_criteria/devices', ['q' => $query, 'count' => 50])['data'] ?? [])
            ->map(fn ($d) => ['id' => (string) $d['targeting_value'], 'name' => $d['name'], 'manufacturer' => $d['manufacturer'] ?? null, 'os' => $d['os_type'] ?? null])
            ->values()->all();
    }

    public function networkOperators(string $countryCode, ?string $query = null): array
    {
        $all = Cache::remember('x-builder:operators:' . $countryCode, 86400, fn () => $this->options('targeting_criteria/network_operators', ['country_code' => $countryCode, 'count' => 1000]));

        return $query ? $this->filter($all, $query) : array_slice($all, 0, 100);
    }

    public function appStoreCategories(string $query, ?string $os = null): array
    {
        return collect($this->api->get('targeting_criteria/app_store_categories', array_filter(['q' => $query, 'os_type' => $os]))['data'] ?? [])
            ->map(fn ($c) => ['id' => (string) $c['targeting_value'], 'name' => $c['name'], 'os' => $c['os_type'] ?? null])
            ->values()->all();
    }

    /** @handles -> X users (for follower look-alike targeting). */
    public function users(array $handles): array
    {
        $handles = collect($handles)->map(fn ($h) => ltrim(trim((string) $h), '@'))->filter(fn ($h) => preg_match('/^[A-Za-z0-9_]{1,15}$/', $h))->unique()->take(100)->values();
        if ($handles->isEmpty()) {
            return ['users' => [], 'missing' => []];
        }

        $found = collect($this->api->usersByHandle($handles->all())['data'] ?? [])->map(fn ($u) => [
            'id' => (string) $u['id'], 'name' => $u['name'] ?? $u['username'], 'handle' => $u['username'],
            'picture' => $u['profile_image_url'] ?? null, 'followers' => $u['public_metrics']['followers_count'] ?? null,
        ]);
        $lower = $found->pluck('handle')->map(fn ($h) => strtolower($h));

        return ['users' => $found->values()->all(), 'missing' => $handles->reject(fn ($h) => $lower->contains(strtolower($h)))->values()->all()];
    }

    /** Approximate audience size for the current targeting (needs one primary criterion). */
    public function estimate(array $targeting, string $objective): array
    {
        $criteria = collect($this->criteria($targeting))->map(fn ($c) => array_filter($c, fn ($v) => $v !== null))->values();
        if (! $criteria->contains(fn ($c) => in_array($c['targeting_type'], self::PRIMARY, true))) {
            return ['available' => false, 'reason' => 'primary'];
        }
        $size = $this->api->postJson($this->api->path('audience_estimate'), ['targeting_criteria' => $criteria->all()])['data']['audience_size'] ?? null;

        return ['available' => (bool) $size, 'min' => $size['min'] ?? null, 'max' => $size['max'] ?? null];
    }

    private function options(string $path, array $query = ['count' => 1000]): array
    {
        return collect($this->api->all($path, $query, 10))
            ->map(fn ($o) => ['id' => (string) $o['targeting_value'], 'name' => $o['name']])
            ->unique('id')->values()->all();
    }

    private function filter(array $items, string $query): array
    {
        $q = mb_strtolower(trim($query));

        return array_values(array_slice(array_filter($items, fn ($i) => $q === '' || str_contains(mb_strtolower($i['name']), $q)), 0, 50));
    }

    private function cacheKey(string $name): string
    {
        return 'x-builder:' . $this->api->account->id . ':' . $name;
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------

    public function submit(array $input, string $key): array
    {
        $userId = (int) $this->api->account->user_id;
        $hash = hash('sha256', json_encode($input));

        $lock = Cache::lock("ad-builder:{$userId}:{$key}", 600);
        if (! $lock->get()) {
            return ['success' => false, 'status' => 409, 'message' => __('x_builder.server.in_progress')];
        }

        try {
            $run = AdBuilderRun::firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => $key],
                ['social_account_id' => $this->api->account->id, 'platform' => 'x', 'payload_hash' => $hash, 'status' => 'running', 'results' => []]
            );

            if ($run->platform !== 'x' || (int) $run->social_account_id !== (int) $this->api->account->id) {
                return ['success' => false, 'status' => 409, 'message' => __('x_builder.server.key_reused')];
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
                $step = 'lineitem';
                $lineItem = $this->lineItemStep($run, $input, $campaign);
                $step = 'targeting';
                $this->targetingStep($run, $input, $lineItem);
                $step = 'creative';
                $tweetIds = $this->creativeStep($run, $input, $campaign, $lineItem, $refs);
                $step = 'promoted';
                $this->promotedStep($run, $input, $campaign, $lineItem, $tweetIds);
                $step = 'tracking';
                $this->trackingStep($run, $input, $lineItem);
                $step = 'activate';
                $this->activateStep($run, $input, $campaign, $lineItem);
            } catch (XApiException|\InvalidArgumentException $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                Log::warning('X campaign builder step failed.', ['run' => $run->id, 'step' => $step, 'error' => $e->getMessage(), 'code' => $e instanceof XApiException ? $e->errorCode : null]);

                return ['success' => false, 'status' => 422, 'step' => $step, 'message' => $e->getMessage(), 'created' => $this->createdSummary($run), 'retryable' => true, 'reconnect' => $e instanceof XApiException && $e->isAuthError()];
            } catch (\Throwable $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                report($e);

                return ['success' => false, 'status' => 500, 'step' => $step, 'message' => __('x_builder.server.internal_error', ['ref' => $run->id]), 'created' => $this->createdSummary($run), 'retryable' => true];
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
            'message' => $replayed ? __('x_builder.server.replayed') : __('x_builder.server.created'),
            'created' => $this->createdSummary($run),
            'line_item_id' => $run->result('lineitem.remote'),
            'campaign_local_id' => $run->result('campaign.local'),
            'status' => $run->result('activate.status', 'PAUSED'),
        ];
    }

    private function createdSummary(AdBuilderRun $run): array
    {
        return array_values(array_filter([
            $run->result('campaign.remote') ? ['type' => 'campaign', 'id' => $run->result('campaign.remote'), 'reused' => (bool) $run->result('campaign.reused')] : null,
            $run->result('lineitem.remote') ? ['type' => 'lineitem', 'id' => $run->result('lineitem.remote'), 'reused' => false] : null,
            $run->result('creative.tweets') ? ['type' => 'creative', 'id' => implode(', ', (array) $run->result('creative.tweets')), 'reused' => (bool) $run->result('creative.existing')] : null,
            $run->result('promoted.ids') ? ['type' => 'promoted', 'id' => implode(', ', (array) $run->result('promoted.ids')), 'reused' => false] : null,
        ]));
    }

    /**
     * Everything referenced must belong to this ad account: funding
     * instrument, promotable user, audiences, campaigns, Tweets, gallery
     * media. Also enforces X's rules that would otherwise only fail on the
     * last call.
     */
    private function validateSelection(array $input): array
    {
        $objective = $input['objective'];
        $spec = self::OBJECTIVES[$objective];
        $li = $input['lineitem'];
        $t = $li['targeting'] ?? [];
        $ad = $input['ad'];

        if (! in_array($li['goal'], $spec['goals'], true)) {
            throw new \InvalidArgumentException(__('x_builder.server.goal_mismatch'));
        }
        if (! in_array($li['bid_strategy'], $spec['bids'], true)) {
            throw new \InvalidArgumentException(__('x_builder.server.bid_mismatch'));
        }
        if (($li['bid_strategy'] === 'TARGET') && $li['goal'] === 'LINK_CLICKS' && ($li['pay_by'] ?? null) === 'IMPRESSION') {
            throw new \InvalidArgumentException(__('x_builder.server.target_impression'));
        }

        if (($input['campaign']['mode'] ?? 'new') === 'new') {
            $fi = collect($this->fundingInstruments())->firstWhere('id', (string) ($input['campaign']['funding_instrument_id'] ?? ''));
            if (! $fi) {
                throw new \InvalidArgumentException(__('x_builder.server.funding_not_owned'));
            }
            if (! $fi['usable']) {
                throw new \InvalidArgumentException(__('x_builder.server.funding_unusable', ['name' => $fi['name']]));
            }
        }

        $placements = $this->placementList($li);
        $combos = $this->placements()['combinations'];
        $sorted = $placements;
        sort($sorted);
        if ($combos && ! in_array($sorted, $combos, true)) {
            throw new \InvalidArgumentException(__('x_builder.server.placement_combo'));
        }
        if ($placements === ['TWITTER_SEARCH'] && collect(self::NOT_ON_SEARCH)->contains(fn ($k) => $this->hasTargeting($t, $k))) {
            throw new \InvalidArgumentException(__('x_builder.server.search_targeting'));
        }

        $this->ownedUser((string) ($ad['as_user_id'] ?? ''));

        $audienceIds = array_merge($t['audiences'] ?? [], $t['excluded_audiences'] ?? []);
        if ($audienceIds) {
            $usable = collect($this->audiences())->where('usable', true)->pluck('id');
            foreach ($audienceIds as $id) {
                if (! $usable->contains((string) $id)) {
                    throw new \InvalidArgumentException(__('x_builder.server.audience_not_owned'));
                }
            }
        }

        if (! empty($t['engagement']['campaign_ids'])) {
            $owned = collect($this->api->all($this->api->path('campaigns'), ['count' => 1000, 'with_deleted' => true], 5))->pluck('id')->map(fn ($id) => (string) $id);
            foreach ($t['engagement']['campaign_ids'] as $id) {
                if (! $owned->contains((string) $id)) {
                    throw new \InvalidArgumentException(__('x_builder.server.campaign_not_owned'));
                }
            }
        }

        $refs = ['media' => []];
        if (($ad['source'] ?? 'new') === 'existing') {
            $ids = array_values(array_unique(array_map('strval', $ad['tweet_ids'] ?? [])));
            $found = collect($this->api->get($this->api->path('tweets'), ['tweet_type' => 'PUBLISHED', 'timeline_type' => 'ALL', 'tweet_ids' => implode(',', $ids), 'trim_user' => true])['data'] ?? [])
                ->map(fn ($tw) => $this->presentTweet($tw))->keyBy('id');
            foreach ($ids as $id) {
                if (! $found->has($id)) {
                    throw new \InvalidArgumentException(__('x_builder.server.tweet_not_owned', ['id' => $id]));
                }
                if (! empty($spec['needs_video']) && ! in_array($found[$id]['media_type'], ['video', 'animated_gif'], true)) {
                    throw new \InvalidArgumentException(__('x_builder.server.tweet_needs_video', ['id' => $id]));
                }
            }
        } else {
            foreach ($ad['media_ids'] ?? [] as $assetId) {
                $asset = MediaAsset::ownedBy((int) $this->api->account->user_id)->find((int) $assetId);
                if (! $asset || ! in_array($asset->type, ['image', 'video'], true)) {
                    throw new \InvalidArgumentException(__('x_builder.server.media_not_owned'));
                }
                $refs['media'][] = $asset;
            }
            $types = collect($refs['media'])->pluck('type');
            if ($types->contains('video') && $types->count() > 1) {
                throw new \InvalidArgumentException(__('x_builder.server.one_video'));
            }
            if (! empty($spec['needs_video']) && ! $types->contains('video')) {
                throw new \InvalidArgumentException(__('x_builder.server.needs_video'));
            }
            if (in_array($ad['card'] ?? 'none', ['website', 'app'], true) && count($refs['media']) !== 1) {
                throw new \InvalidArgumentException(__('x_builder.server.card_one_media'));
            }
        }

        return $refs;
    }

    private function hasTargeting(array $t, string $key): bool
    {
        return match ($key) {
            'followers' => ! empty($t['own_followers']),
            'similar_followers' => ! empty($t['similar_followers']),
            'engagement' => ! empty($t['engagement']['type']),
            default => ! empty($t[$key]),
        };
    }

    private function placementList(array $li): array
    {
        $placements = array_values(array_unique($li['placements'] ?? []));

        return ($li['placement_mode'] ?? 'all') === 'all' || ! $placements ? ['ALL_ON_TWITTER'] : $placements;
    }

    // ---- campaign -----------------------------------------------------

    private function campaignStep(AdBuilderRun $run, array $input): array
    {
        if ($remote = $run->result('campaign.remote')) {
            return ['remote' => $remote, 'local' => $run->result('campaign.local'), 'reused' => (bool) $run->result('campaign.reused')];
        }

        $spec = $input['campaign'];
        $objective = $input['objective'];

        if (($spec['mode'] ?? 'new') === 'existing') {
            $existing = collect($this->campaigns())->firstWhere('id', (string) ($spec['id'] ?? ''));
            if (! $existing) {
                throw new \InvalidArgumentException(__('x_builder.server.campaign_not_owned'));
            }
            if (! $existing['supported'] || ($existing['objective'] && $existing['objective'] !== $objective)) {
                throw new \InvalidArgumentException(__('x_builder.server.campaign_objective', ['objective' => $existing['objective'] ?? '-']));
            }

            $local = AdCampaign::firstOrCreate(
                ['ad_campaign_id' => $existing['id'], 'platform' => 'x'],
                ['user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $existing['name'], 'objective' => $objective, 'funding_instrument_id' => $existing['funding_instrument_id'], 'budget_mode' => $existing['daily_budget'] ? 'daily' : 'total', 'budget' => $existing['total_budget'], 'daily_budget' => $existing['daily_budget'], 'status' => $existing['status'] === 'ACTIVE']
            );
            $run->remember('campaign', ['remote' => $existing['id'], 'local' => $local->id, 'reused' => true]);

            return ['remote' => $existing['id'], 'local' => $local->id, 'reused' => true];
        }

        $name = ($spec['name'] ?? null) ?: __('x_builder.objectives.' . $objective . '.label') . ' - ' . now()->format('Y-m-d');
        $params = array_filter([
            'funding_instrument_id' => $spec['funding_instrument_id'],
            'name' => mb_substr($name, 0, 255),
            'budget_optimization' => 'LINE_ITEM',
            'daily_budget_amount_local_micro' => $this->micro($spec['daily_budget'] ?? null),
            'total_budget_amount_local_micro' => $this->micro($spec['total_budget'] ?? null),
            'purchase_order_number' => $spec['purchase_order_number'] ?? null,
            // Activated at the very end, once the line item is complete.
            'entity_status' => 'PAUSED',
        ], fn ($v) => $v !== null && $v !== '');

        $id = (string) ($this->api->post($this->api->path('campaigns'), $params)['data']['id'] ?? '');
        if ($id === '') {
            throw new XApiException(__('x_builder.server.no_id', ['object' => 'campaign']));
        }
        $run->remember('campaign', ['remote' => $id, 'reused' => false]);

        $local = AdCampaign::updateOrCreate(['ad_campaign_id' => $id, 'platform' => 'x'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $params['name'], 'objective' => $objective,
            'funding_instrument_id' => $spec['funding_instrument_id'],
            'budget_mode' => ! empty($spec['daily_budget']) ? 'daily' : 'total',
            'budget' => $spec['total_budget'] ?? $spec['daily_budget'] ?? null,
            'daily_budget' => $spec['daily_budget'] ?? null,
            'start_time' => $this->utc($input['lineitem']['start_time'] ?? null),
            'end_time' => $this->utc($input['lineitem']['end_time'] ?? null),
            'status' => false,
        ]);
        $run->remember('campaign.local', $local->id);

        return ['remote' => $id, 'local' => $local->id, 'reused' => false];
    }

    // ---- line item ----------------------------------------------------

    public function lineItemParams(array $input, string $campaignId): array
    {
        $li = $input['lineitem'];
        $objective = $input['objective'];
        $spec = self::OBJECTIVES[$objective];
        $bid = $li['bid_strategy'];
        $payBy = isset(self::PAY_BY[$li['goal']]) ? ($li['pay_by'] ?? null) : null;
        if ($bid === 'TARGET' && $li['goal'] === 'LINK_CLICKS') {
            $payBy = 'LINK_CLICK';
        }
        $frequency = ! empty($spec['frequency']) && ! empty($li['frequency_cap']);

        return array_filter([
            'campaign_id' => $campaignId,
            'name' => mb_substr(($li['name'] ?? null) ?: __('x_builder.defaults.lineitem', ['date' => now()->format('Y-m-d')]), 0, 255),
            'objective' => $objective,
            'product_type' => 'PROMOTED_TWEETS',
            'placements' => implode(',', $this->placementList($li)),
            'goal' => $li['goal'],
            'bid_strategy' => $bid,
            'bid_amount_local_micro' => $bid !== 'AUTO' ? $this->micro($li['bid_amount'] ?? null) : null,
            'pay_by' => $payBy,
            'daily_budget_amount_local_micro' => $this->micro($li['daily_budget'] ?? null),
            'total_budget_amount_local_micro' => $this->micro($li['total_budget'] ?? null),
            'standard_delivery' => array_key_exists('standard_delivery', $li) && $li['standard_delivery'] !== null ? (bool) $li['standard_delivery'] : null,
            'start_time' => $this->utc($li['start_time'] ?? null) ?? now()->addMinutes(5)->utc()->toIso8601ZuluString(),
            'end_time' => $this->utc($li['end_time'] ?? null),
            'frequency_cap' => $frequency ? (int) $li['frequency_cap'] : null,
            'duration_in_days' => $frequency ? (int) ($li['frequency_days'] ?? 7) : null,
            'audience_expansion' => $li['targeting']['audience_expansion'] ?? null,
            'primary_web_event_tag' => $li['goal'] === 'WEBSITE_CONVERSIONS' ? ($li['web_event_tag'] ?? null) : null,
            'ios_app_store_identifier' => ! empty($spec['needs_app']) ? ($input['app']['ios_id'] ?? null) : null,
            'android_app_store_identifier' => ! empty($spec['needs_app']) ? ($input['app']['android_id'] ?? null) : null,
            'entity_status' => 'PAUSED',
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function lineItemStep(AdBuilderRun $run, array $input, array $campaign): array
    {
        if ($remote = $run->result('lineitem.remote')) {
            return ['remote' => $remote, 'local' => $run->result('lineitem.local')];
        }

        $params = $this->lineItemParams($input, $campaign['remote']);

        // A brand-new campaign can take a moment to become visible to the
        // line item endpoint - one short retry. An error response means
        // nothing was created, so this can't duplicate.
        try {
            $data = $this->api->post($this->api->path('line_items'), $params)['data'] ?? [];
        } catch (XApiException $e) {
            if ($campaign['reused'] || $e->isAuthError() || $e->status !== 404) {
                throw $e;
            }
            sleep(2);
            $data = $this->api->post($this->api->path('line_items'), $params)['data'] ?? [];
        }
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new XApiException(__('x_builder.server.no_id', ['object' => 'line item']));
        }
        $run->remember('lineitem', ['remote' => $id]);

        $t = $input['lineitem']['targeting'] ?? [];
        $local = AdAdGroup::updateOrCreate(['ad_adgroup_id' => $id, 'platform' => 'x'], [
            'user_id' => $run->user_id,
            'social_account_id' => $this->api->account->id,
            'ad_campaign_id' => $campaign['local'],
            'name' => $params['name'],
            'objective' => $input['objective'],
            'placement_type' => 'PROMOTED_TWEETS',
            'placements' => json_encode(explode(',', $params['placements'])),
            'optimization_goal' => $params['goal'],
            'bid_strategy' => $params['bid_strategy'],
            'bid_type' => $params['bid_strategy'],
            'bid_price' => $input['lineitem']['bid_amount'] ?? null,
            'billing_event' => $params['pay_by'] ?? null,
            'budget_mode' => ! empty($input['lineitem']['daily_budget']) ? 'daily' : (! empty($input['lineitem']['total_budget']) ? 'total' : null),
            'budget' => $input['lineitem']['daily_budget'] ?? $input['lineitem']['total_budget'] ?? null,
            'schedule_start_time' => $params['start_time'],
            'schedule_end_time' => $params['end_time'] ?? null,
            'pacing' => isset($params['standard_delivery']) ? ($params['standard_delivery'] ? 'STANDARD' : 'ACCELERATED') : null,
            'primary_web_event_tag' => $params['primary_web_event_tag'] ?? null,
            'ios' => $params['ios_app_store_identifier'] ?? null,
            'android' => $params['android_app_store_identifier'] ?? null,
            'location_ids' => json_encode(collect($t['locations'] ?? [])->pluck('id')->all()),
            'languages' => json_encode($t['languages'] ?? []),
            'gender' => $t['gender'] ?? 'ANY',
            'age_groups' => json_encode(array_filter([$t['age'] ?? null])),
            'keywords' => json_encode($t['keywords'] ?? []),
            'targeting_criteria' => json_encode(['criteria' => $this->criteria($t), 'local' => $t]),
            'status' => false,
        ]);
        $run->remember('lineitem.local', $local->id);

        return ['remote' => $id, 'local' => $local->id];
    }

    /**
     * The line item's targeting criteria, ready for the batch endpoint.
     * Same types OR, primary types (followers, audiences, interests,
     * keywords, conversations, events) union, the rest AND (X's rules).
     *
     * @return array<int, array{targeting_type: string, targeting_value: string, operator_type: string}>
     */
    public function criteria(array $t): array
    {
        $out = [];
        $add = function (string $type, $value, string $operator = 'EQ') use (&$out) {
            if ($value !== null && $value !== '') {
                $out[] = ['targeting_type' => $type, 'targeting_value' => (string) $value, 'operator_type' => $operator];
            }
        };

        foreach ($t['locations'] ?? [] as $l) {
            $add('LOCATION', $l['id'] ?? null);
        }
        foreach ($t['languages'] ?? [] as $lang) {
            $add('LANGUAGE', $lang);
        }
        if (in_array($t['gender'] ?? 'ANY', ['MALE', 'FEMALE'], true)) {
            $add('GENDER', $t['gender'] === 'MALE' ? '1' : '2');
        }
        $add('AGE', $t['age'] ?? null);
        foreach ($t['platforms'] ?? [] as $v) {
            $add('PLATFORM', $v);
        }
        foreach ($t['platform_versions'] ?? [] as $v) {
            $add('PLATFORM_VERSION', $v);
        }
        foreach ($t['devices'] ?? [] as $v) {
            $add('DEVICE', $v);
        }
        foreach ($t['network_operators'] ?? [] as $v) {
            $add('NETWORK_OPERATOR', $v);
        }
        foreach ($t['app_categories'] ?? [] as $v) {
            $add('APP_STORE_CATEGORY', $v);
        }
        foreach ($t['interests'] ?? [] as $v) {
            $add('INTEREST', $v);
        }
        foreach ($t['conversations'] ?? [] as $v) {
            $add('CONVERSATION', $v);
        }
        $add('EVENT', $t['event'] ?? null);
        foreach ($t['keywords'] ?? [] as $k) {
            $add($k['type'] ?? 'BROAD_KEYWORD', $k['value'] ?? null);
        }
        foreach ($t['excluded_keywords'] ?? [] as $k) {
            $add($k['type'] ?? 'BROAD_KEYWORD', $k['value'] ?? null, 'NE');
        }
        if (! empty($t['own_followers']) && $this->api->userId()) {
            $add('FOLLOWERS_OF_USER', $this->api->userId());
        }
        foreach ($t['similar_followers'] ?? [] as $v) {
            $add('SIMILAR_TO_FOLLOWERS_OF_USER', $v);
        }
        foreach ($t['audiences'] ?? [] as $v) {
            $add(! empty($t['audience_lookalike']) ? 'CUSTOM_AUDIENCE_EXPANDED' : 'CUSTOM_AUDIENCE', $v);
        }
        foreach ($t['excluded_audiences'] ?? [] as $v) {
            $add('CUSTOM_AUDIENCE', $v, 'NE');
        }
        if (! empty($t['engagement']['type'])) {
            $add('ENGAGEMENT_TYPE', $t['engagement']['type']);
            foreach ($t['engagement']['campaign_ids'] ?? [] as $v) {
                $add('CAMPAIGN_ENGAGEMENT', $v);
            }
            if (! empty($t['engagement']['organic']) && $this->api->userId()) {
                $add('USER_ENGAGEMENT', $this->api->userId());
            }
        }

        return $out;
    }

    /** Batch-create the criteria (500 per call; a batch succeeds or fails as a whole). */
    private function targetingStep(AdBuilderRun $run, array $input, array $lineItem): void
    {
        $chunks = array_chunk($this->criteria($input['lineitem']['targeting'] ?? []), 500);

        foreach ($chunks as $i => $chunk) {
            if ($run->result("targeting.chunk_{$i}")) {
                continue;
            }
            $ops = array_map(fn ($c) => ['operation_type' => 'Create', 'params' => $c + ['line_item_id' => $lineItem['remote']]], $chunk);
            $this->api->postJson('batch/' . $this->api->path('targeting_criteria'), $ops);
            $run->remember("targeting.chunk_{$i}", count($chunk));
        }
    }

    // ---- creative -----------------------------------------------------

    /** @return string[] the Tweet ids to promote */
    private function creativeStep(AdBuilderRun $run, array $input, array $campaign, array $lineItem, array $refs): array
    {
        if ($ids = $run->result('creative.tweets')) {
            return (array) $ids;
        }

        $ad = $input['ad'];

        if (($ad['source'] ?? 'new') === 'existing') {
            $ids = array_values(array_unique(array_map('strval', $ad['tweet_ids'])));
            $run->remember('creative', ['tweets' => $ids, 'existing' => true]);
            foreach ($ids as $id) {
                AdCreative::updateOrCreate(['ad_creative_id' => $id, 'platform' => 'x', 'ad_adgroup_id' => $lineItem['local']], [
                    'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'ad_campaign_id' => $campaign['local'],
                    'name' => $ad['name'] ?? ('Tweet ' . $id), 'type' => 'PROMOTED_TWEET', 'ad_format' => 'EXISTING_TWEET',
                ]);
            }

            return $ids;
        }

        $mediaKeys = [];
        foreach ($refs['media'] as $asset) {
            $mediaKeys[] = $this->uploadMedia($run, $asset, $campaign);
        }

        $cardUri = $run->result('creative.card');
        $card = $ad['card'] ?? 'none';
        if (! $cardUri && in_array($card, ['website', 'app'], true)) {
            $components = [['type' => 'MEDIA', 'media_key' => $mediaKeys[0]]];
            if ($card === 'website') {
                $components[] = ['type' => 'DETAILS', 'title' => mb_substr((string) $ad['headline'], 0, 70), 'destination' => ['type' => 'WEBSITE', 'url' => $ad['url']]];
            } else {
                $app = $input['app'];
                $components[] = ['type' => 'BUTTON', 'label' => ['type' => 'ENUM', 'value' => $ad['app_label'] ?? 'INSTALL'], 'destination' => array_filter([
                    'type' => 'APP', 'country_code' => strtoupper($app['country_code']),
                    'ios_app_store_identifier' => $app['ios_id'] ?? null, 'googleplay_app_id' => $app['android_id'] ?? null,
                ])];
            }
            $data = $this->api->postJson($this->api->path('cards'), ['name' => mb_substr(($ad['name'] ?? null) ?: 'Syncora card ' . now()->format('Y-m-d H:i'), 0, 80), 'components' => $components])['data'] ?? [];
            $cardUri = $data['card_uri'] ?? null;
            if (! $cardUri) {
                throw new XApiException(__('x_builder.server.no_id', ['object' => 'card']));
            }
            $run->remember('creative.card', $cardUri);
        }

        $text = trim((string) ($ad['text'] ?? ''));
        // Without a card, a website URL rides in the Tweet text (X unfurls it).
        if ($card === 'none' && ! empty($ad['url']) && ! str_contains($text, $ad['url'])) {
            $text = trim($text . ' ' . $ad['url']);
        }

        $data = $this->api->post($this->api->path('tweet'), array_filter([
            'as_user_id' => $ad['as_user_id'],
            'text' => $text,
            'media_keys' => $cardUri ? null : ($mediaKeys ? implode(',', $mediaKeys) : null),
            'card_uri' => $cardUri,
            'nullcast' => true,
            'conversation_settings' => $ad['conversation_settings'] ?? null,
            'name' => $ad['name'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''))['data'] ?? [];
        $tweetId = (string) ($data['id_str'] ?? $data['id'] ?? '');
        if ($tweetId === '') {
            throw new XApiException(__('x_builder.server.no_id', ['object' => 'Tweet']));
        }
        $run->remember('creative', ['tweets' => [$tweetId], 'existing' => false, 'card' => $cardUri]);

        AdCreative::updateOrCreate(['ad_creative_id' => $tweetId, 'platform' => 'x'], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'ad_campaign_id' => $campaign['local'], 'ad_adgroup_id' => $lineItem['local'],
            'name' => $ad['name'] ?? ('Tweet ' . $tweetId), 'type' => 'PROMOTED_TWEET', 'ad_format' => $card === 'none' ? 'TWEET' : strtoupper($card) . '_CARD',
            'message' => $text, 'headline' => $ad['headline'] ?? null, 'url' => $ad['url'] ?? null, 'call_to_action' => $card === 'app' ? ($ad['app_label'] ?? null) : null,
        ]);

        return [$tweetId];
    }

    /**
     * X API v2 chunked upload (initialize -> append -> finalize -> status),
     * then added to the ad account's Media Library, which cards require.
     */
    private function uploadMedia(AdBuilderRun $run, MediaAsset $asset, array $campaign): string
    {
        if ($key = $run->result("media.asset_{$asset->id}")) {
            return $key;
        }

        $bytes = $this->bytes($asset);
        $mime = $asset->mime_type ?: ($asset->type === 'video' ? 'video/mp4' : 'image/jpeg');
        $category = $asset->type === 'video' ? 'amplify_video' : ($mime === 'image/gif' ? 'tweet_gif' : 'tweet_image');

        $init = $this->api->upload('POST', '/initialize', [], ['media_category' => $category, 'media_type' => $mime, 'total_bytes' => strlen($bytes)])['data'] ?? [];
        $mediaId = (string) ($init['id'] ?? '');
        $mediaKey = $init['media_key'] ?? null;
        if ($mediaId === '') {
            throw new XApiException(__('x_builder.server.upload_failed', ['name' => $asset->original_name]));
        }

        foreach (str_split($bytes, 4 * 1024 * 1024) as $segment => $chunk) {
            $this->api->upload('POST', '/' . $mediaId . '/append', [], null, ['field' => 'media', 'contents' => $chunk, 'name' => 'chunk' . $segment, 'fields' => ['segment_index' => (string) $segment]]);
        }

        $final = $this->api->upload('POST', '/' . $mediaId . '/finalize')['data'] ?? [];
        $mediaKey = $final['media_key'] ?? $mediaKey;
        $processing = $final['processing_info'] ?? null;

        for ($i = 0; $processing && ($processing['state'] ?? '') !== 'succeeded' && $i < 30; $i++) {
            if (($processing['state'] ?? '') === 'failed') {
                throw new XApiException(__('x_builder.server.processing_failed', ['reason' => $processing['error']['message'] ?? '-']));
            }
            sleep(min(10, max(1, (int) ($processing['check_after_secs'] ?? 2))));
            $processing = $this->api->upload('GET', '', ['command' => 'STATUS', 'media_id' => $mediaId])['data']['processing_info'] ?? null;
        }
        if ($processing && ($processing['state'] ?? '') !== 'succeeded') {
            throw new XApiException(__('x_builder.server.still_processing'));
        }
        if (! $mediaKey) {
            throw new XApiException(__('x_builder.server.upload_failed', ['name' => $asset->original_name]));
        }

        $this->api->post($this->api->path('media_library'), array_filter(['media_key' => $mediaKey, 'name' => mb_substr((string) $asset->original_name, 0, 100) ?: null]));
        $run->remember("media.asset_{$asset->id}", $mediaKey);

        AdMedia::updateOrCreate(['ad_media_id' => $mediaKey, 'platform' => 'x', 'ad_campaign_id' => $campaign['local']], [
            'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'name' => $asset->original_name, 'file_name' => $asset->original_name,
            'url' => $asset->url, 'type' => strtoupper($asset->type), 'file_id' => $mediaKey, 'status' => false, 'thumbnail_url' => $asset->thumbnail_url,
        ]);

        return $mediaKey;
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
            throw new \InvalidArgumentException(__('x_builder.server.unreadable', ['name' => $asset->original_name]));
        }

        return $response->body();
    }

    // ---- promoted tweets, tracking, activation ------------------------

    private function promotedStep(AdBuilderRun $run, array $input, array $campaign, array $lineItem, array $tweetIds): void
    {
        if ($run->result('promoted.ids')) {
            return;
        }

        $data = $this->api->post($this->api->path('promoted_tweets'), ['line_item_id' => $lineItem['remote'], 'tweet_ids' => implode(',', $tweetIds)])['data'] ?? [];
        $records = array_is_list($data) ? $data : [$data];
        $ids = collect($records)->pluck('id')->filter()->map(fn ($id) => (string) $id)->values()->all();
        if (! $ids) {
            throw new XApiException(__('x_builder.server.no_id', ['object' => 'promoted Tweet']));
        }
        $run->remember('promoted', ['ids' => $ids]);

        foreach ($records as $record) {
            $creative = AdCreative::where('platform', 'x')->where('ad_creative_id', (string) ($record['tweet_id'] ?? ''))->first();
            Ad::updateOrCreate(['ad_id' => (string) $record['id'], 'platform' => 'x'], [
                'user_id' => $run->user_id, 'social_account_id' => $this->api->account->id, 'ad_campaign_id' => $campaign['local'],
                'ad_adgroup_id' => $lineItem['local'], 'ad_creative_id' => $creative?->id, 'name' => $input['ad']['name'] ?? ('Promoted Tweet ' . ($record['tweet_id'] ?? '')),
                'type' => 'PROMOTED_TWEET', 'text' => $creative?->message, 'status' => false,
            ]);
        }
    }

    private function trackingStep(AdBuilderRun $run, array $input, array $lineItem): void
    {
        foreach (['impression_tag' => 'IMPRESSION_TAG', 'click_tracker' => 'CLICK_TRACKER'] as $field => $type) {
            $url = $input['lineitem'][$field] ?? null;
            if (! $url || $run->result("tracking.{$field}")) {
                continue;
            }
            $data = $this->api->post($this->api->path('tracking_tags'), ['line_item_id' => $lineItem['remote'], 'tracking_tag_type' => $type, 'tracking_tag_url' => $url])['data'] ?? [];
            $run->remember("tracking.{$field}", (string) ($data['id'] ?? 'ok'));
        }
    }

    /** Launch: everything exists, so the requested status can be applied safely. */
    private function activateStep(AdBuilderRun $run, array $input, array $campaign, array $lineItem): void
    {
        $status = ($input['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';

        if ($status === 'ACTIVE') {
            if (! $run->result('activate.lineitem')) {
                $this->api->put($this->api->path('line_items/' . $lineItem['remote']), ['entity_status' => 'ACTIVE']);
                $run->remember('activate.lineitem', true);
            }
            if (! $campaign['reused'] && ! $run->result('activate.campaign')) {
                $this->api->put($this->api->path('campaigns/' . $campaign['remote']), ['entity_status' => 'ACTIVE']);
                $run->remember('activate.campaign', true);
            }
            AdAdGroup::whereKey($lineItem['local'])->update(['status' => true]);
            AdCampaign::whereKey($campaign['local'])->update(['status' => true]);
            Ad::where('platform', 'x')->whereIn('ad_id', (array) $run->result('promoted.ids', []))->update(['status' => true]);
        }

        $run->remember('activate.status', $status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function ownedUser(string $userId): array
    {
        $user = collect($this->promotableUsers())->firstWhere('id', $userId);
        if (! $user) {
            throw new \InvalidArgumentException(__('x_builder.server.user_not_owned'));
        }

        return $user;
    }

    /** The ad account's time zone (X reports it on GET accounts/:id). */
    private function timezone(): string
    {
        $tz = $this->info()['timezone'] ?? $this->api->account->metadata['timezone'] ?? 'UTC';

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    private function micro($amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round(((float) $amount) * 1_000_000);
    }

    /** A datetime-local value in the ad account's time zone -> ISO 8601 UTC. */
    private function utc(?string $local): ?string
    {
        if (! $local) {
            return null;
        }
        try {
            return Carbon::parse($local, $this->timezone())->utc()->toIso8601ZuluString();
        } catch (\Throwable) {
            return Carbon::parse($local, 'UTC')->toIso8601ZuluString();
        }
    }
}
