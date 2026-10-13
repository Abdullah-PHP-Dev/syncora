<?php

namespace App\Services\AdServices;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\Admin\PlatformPage;
use App\Models\MediaAsset;
use App\Support\Ads\MetaAdsGraph;
use App\Support\Ads\MetaApiException;
use App\Support\Ads\MetaGoals;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook / Instagram campaign builder (ads/facebook/campaigns/create):
 * campaign -> ad set -> creative -> ad, each either an existing object of
 * the selected ad account or a new one.
 *
 * - Lookups (campaigns, ad sets, creatives, audiences, pixels, targeting,
 *   locations, languages, audience estimates) are read live from the
 *   Marketing API for the seller's own ad account - nothing is invented.
 * - Every existing object (and Page / audience / pixel) a request names is
 *   checked to belong to that ad account (or the seller) before use.
 * - submit() is idempotent per key (AdBuilderRun): each Meta object is
 *   recorded the moment it is created, so a retry after a failure, or a
 *   double click, continues from the failed step instead of creating
 *   duplicates. New objects are created PAUSED unless the ad is published
 *   ACTIVE.
 */
class FacebookCampaignBuilder
{
    /** VAT (%) shown on top of the ad budget - same rate the other ad forms use. Display only; Meta gets the budget as entered. */
    public const VAT_RATE = 15;

    public function __construct(private MetaAdsGraph $graph)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $graph = MetaAdsGraph::forUser($userId);

        return $graph ? new self($graph) : null;
    }

    public function graph(): MetaAdsGraph
    {
        return $this->graph;
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    /** Everything the wizard needs up front (lists that can be long load separately). */
    public function bootstrap(): array
    {
        $info = $this->graph->info();

        return [
            'account' => [
                'id' => $this->graph->actId(),
                'local_id' => $this->graph->account->id,
                'name' => $info['name'] ?? $this->graph->account->name,
                'currency' => $this->graph->currency(),
                'currency_offset' => $this->graph->currencyOffset(),
                'timezone' => $info['timezone_name'] ?? null,
                'min_daily_budget' => isset($info['min_daily_budget']) ? $this->graph->toMajor($info['min_daily_budget']) : null,
                'active' => (int) ($info['account_status'] ?? 1) === 1,
            ],
            'pages' => $this->pages(),
            'vat_rate' => self::VAT_RATE,
            'goals' => MetaGoals::all(),
            'conversion_events' => MetaGoals::CONVERSION_EVENTS,
        ];
    }

    /** The seller's Facebook Pages (saved when the ad account was connected). */
    public function pages(): array
    {
        return PlatformPage::where('user_id', $this->graph->account->user_id)->where('platform', 'facebook')
            ->orderBy('name')->get()
            // Graph's /{page}/picture redirect always serves the Page's current
            // avatar; the stored URL is a signed CDN link that expires.
            ->map(fn ($p) => ['id' => (string) $p->page_id, 'name' => $p->name, 'picture' => 'https://graph.facebook.com/' . $p->page_id . '/picture?type=large', 'username' => $p->username])
            ->values()->all();
    }

    public function instagramAccounts(): array
    {
        $accounts = collect($this->graph->list($this->graph->actId() . '/instagram_accounts', ['fields' => 'id,username,profile_pic'], 50))
            ->map(fn ($a) => ['id' => (string) $a['id'], 'username' => $a['username'] ?? null, 'picture' => $a['profile_pic'] ?? null]);

        // Accounts linked to the seller's Pages are also valid identities
        // (instagram_user_id) - the ad-account edge can come back empty
        // even when Business Settings shows one connected.
        foreach (array_slice($this->pages(), 0, 10) as $page) {
            try {
                $ig = $this->graph->get($page['id'], ['fields' => 'instagram_business_account{id,username,profile_picture_url}'])['instagram_business_account'] ?? null;
            } catch (MetaApiException) {
                $ig = null;
            }
            if ($ig && ! $accounts->contains('id', (string) $ig['id'])) {
                $accounts->push(['id' => (string) $ig['id'], 'username' => $ig['username'] ?? null, 'picture' => $ig['profile_picture_url'] ?? null, 'page_id' => $page['id']]);
            }
        }

        return $accounts->values()->all();
    }

    public function campaigns(?string $search = null): array
    {
        $params = ['fields' => 'id,name,objective,status,effective_status,special_ad_categories,daily_budget,lifetime_budget,bid_strategy,created_time'];
        $params['filtering'] = array_values(array_filter([
            ['field' => 'effective_status', 'operator' => 'IN', 'value' => ['ACTIVE', 'PAUSED', 'IN_PROCESS', 'WITH_ISSUES', 'CAMPAIGN_PAUSED']],
            $search ? ['field' => 'name', 'operator' => 'CONTAIN', 'value' => $search] : null,
        ]));

        return array_map(fn ($c) => [
            'id' => $c['id'],
            'name' => $c['name'] ?? $c['id'],
            'objective' => $c['objective'] ?? null,
            'goals' => MetaGoals::forObjective($c['objective'] ?? ''),
            'status' => $c['effective_status'] ?? $c['status'] ?? null,
            'special_ad_categories' => array_values(array_diff($c['special_ad_categories'] ?? [], ['NONE'])),
            'campaign_budget' => isset($c['daily_budget']) || isset($c['lifetime_budget']),
            'daily_budget' => $this->graph->toMajor($c['daily_budget'] ?? null),
            'lifetime_budget' => $this->graph->toMajor($c['lifetime_budget'] ?? null),
            'bid_strategy' => $c['bid_strategy'] ?? null,
            'created_time' => $c['created_time'] ?? null,
        ], $this->graph->list($this->graph->actId() . '/campaigns', $params, 200));
    }

    public function adSets(string $campaignId): array
    {
        $this->ownedCampaign($campaignId);

        return array_map(fn ($s) => [
            'id' => $s['id'],
            'name' => $s['name'] ?? $s['id'],
            'status' => $s['effective_status'] ?? $s['status'] ?? null,
            'optimization_goal' => $s['optimization_goal'] ?? null,
            'billing_event' => $s['billing_event'] ?? null,
            'destination_type' => $s['destination_type'] ?? null,
            'daily_budget' => $this->graph->toMajor($s['daily_budget'] ?? null),
            'lifetime_budget' => $this->graph->toMajor($s['lifetime_budget'] ?? null),
            'start_time' => $s['start_time'] ?? null,
            'end_time' => $s['end_time'] ?? null,
            'targeting_summary' => $this->targetingSummary($s['targeting'] ?? []),
        ], $this->graph->list($campaignId . '/adsets', [
            'fields' => 'id,name,status,effective_status,optimization_goal,billing_event,destination_type,daily_budget,lifetime_budget,start_time,end_time,targeting',
            'filtering' => [['field' => 'effective_status', 'operator' => 'IN', 'value' => ['ACTIVE', 'PAUSED', 'IN_PROCESS', 'WITH_ISSUES', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED']]],
        ], 200));
    }

    public function creatives(?string $search = null): array
    {
        $items = $this->graph->list($this->graph->actId() . '/adcreatives', [
            'fields' => 'id,name,status,thumbnail_url,image_url,object_type,call_to_action_type,object_story_spec{page_id,instagram_user_id,link_data{link,message,name,description},video_data{message,title}}',
        ], 150);

        $creatives = array_map(fn ($c) => [
            'id' => $c['id'],
            'name' => $c['name'] ?? $c['id'],
            'status' => $c['status'] ?? null,
            'thumbnail' => $c['thumbnail_url'] ?? $c['image_url'] ?? null,
            'type' => $c['object_type'] ?? null,
            'cta' => $c['call_to_action_type'] ?? null,
            'page_id' => $c['object_story_spec']['page_id'] ?? null,
            'text' => $c['object_story_spec']['link_data']['message'] ?? $c['object_story_spec']['video_data']['message'] ?? null,
            'headline' => $c['object_story_spec']['link_data']['name'] ?? $c['object_story_spec']['video_data']['title'] ?? null,
            'link' => $c['object_story_spec']['link_data']['link'] ?? null,
        ], array_values(array_filter($items, fn ($c) => ($c['status'] ?? 'ACTIVE') !== 'DELETED')));

        return $search
            ? array_values(array_filter($creatives, fn ($c) => Str::contains(Str::lower($c['name'] . ' ' . $c['headline'] . ' ' . $c['text']), Str::lower($search))))
            : $creatives;
    }

    /** Meta's own rendering of an existing creative (iframe HTML). */
    public function creativePreview(string $creativeId, string $format = 'MOBILE_FEED_STANDARD'): ?string
    {
        $this->ownedObject($creativeId, 'creative');
        $data = $this->graph->get($creativeId . '/previews', ['ad_format' => $format]);

        return $data['data'][0]['body'] ?? null;
    }

    public function audiences(): array
    {
        $custom = array_map(fn ($a) => [
            'id' => $a['id'],
            'name' => $a['name'] ?? $a['id'],
            'kind' => ($a['subtype'] ?? '') === 'LOOKALIKE' ? 'lookalike' : 'custom',
            'subtype' => $a['subtype'] ?? null,
            'size_min' => $a['approximate_count_lower_bound'] ?? null,
            'size_max' => $a['approximate_count_upper_bound'] ?? null,
            'ready' => (int) ($a['delivery_status']['code'] ?? 200) === 200,
            'status' => $a['delivery_status']['description'] ?? null,
        ], $this->graph->list($this->graph->actId() . '/customaudiences', [
            'fields' => 'id,name,subtype,approximate_count_lower_bound,approximate_count_upper_bound,delivery_status',
        ], 300));

        try {
            $saved = array_map(fn ($a) => [
                'id' => $a['id'],
                'name' => $a['name'] ?? $a['id'],
                'kind' => 'saved',
                'summary' => $this->targetingSummary($a['targeting'] ?? []),
            ], $this->graph->list($this->graph->actId() . '/saved_audiences', ['fields' => 'id,name,targeting'], 100));
        } catch (MetaApiException) {
            $saved = [];
        }

        return ['custom' => $custom, 'saved' => $saved];
    }

    public function pixels(): array
    {
        return array_map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'] ?? $p['id'],
            'last_fired' => $p['last_fired_time'] ?? null,
            'available' => empty($p['is_unavailable']),
        ], $this->graph->list($this->graph->actId() . '/adspixels', ['fields' => 'id,name,last_fired_time,is_unavailable'], 50));
    }

    public function apps(): array
    {
        return array_map(fn ($a) => [
            'id' => $a['id'],
            'name' => $a['name'] ?? $a['id'],
            'store_urls' => array_values(array_filter((array) ($a['object_store_urls'] ?? []))),
        ], $this->graph->list($this->graph->actId() . '/advertisable_applications', ['fields' => 'id,name,object_store_urls'], 50));
    }

    /** Instant forms on one of the seller's Pages. */
    public function leadForms(string $pageId): array
    {
        $this->ownedPage($pageId);

        return array_map(fn ($f) => ['id' => $f['id'], 'name' => $f['name'] ?? $f['id'], 'status' => $f['status'] ?? null],
            array_values(array_filter(
                $this->graph->list($pageId . '/leadgen_forms', ['fields' => 'id,name,status'], 100),
                fn ($f) => ($f['status'] ?? 'ACTIVE') === 'ACTIVE'
            )));
    }

    /** Detailed targeting - whatever Meta offers this account for the query (interests, behaviors, demographics...). */
    public function targetingSearch(string $query, ?string $type = null): array
    {
        $params = array_filter(['q' => $query, 'limit_type' => $type, 'limit' => 25]);

        return array_map(fn ($t) => [
            'id' => (string) $t['id'],
            'name' => $t['name'] ?? '',
            'type' => $t['type'] ?? 'interests',
            'path' => $t['path'] ?? [],
            'description' => $t['description'] ?? null,
            'size_min' => $t['audience_size_lower_bound'] ?? null,
            'size_max' => $t['audience_size_upper_bound'] ?? null,
        ], $this->graph->get($this->graph->actId() . '/targetingsearch', $params)['data'] ?? []);
    }

    public function locations(string $query): array
    {
        return array_map(fn ($l) => [
            'key' => (string) $l['key'],
            'name' => $l['name'] ?? '',
            'type' => $l['type'] ?? 'country',
            'country_code' => $l['country_code'] ?? null,
            'country_name' => $l['country_name'] ?? null,
            'region' => $l['region'] ?? null,
            'supports_radius' => ($l['type'] ?? '') === 'city',
        ], $this->graph->get('search', [
            'type' => 'adgeolocation',
            'q' => $query,
            'location_types' => ['country', 'region', 'city'],
            'limit' => 15,
        ])['data'] ?? []);
    }

    public function locales(string $query): array
    {
        return array_map(fn ($l) => ['key' => (int) $l['key'], 'name' => $l['name'] ?? ''],
            $this->graph->get('search', ['type' => 'adlocale', 'q' => $query, 'limit' => 15])['data'] ?? []);
    }

    /**
     * Meta's audience estimate for the targeting (delivery_estimate).
     * null when Meta has none - the UI then shows nothing rather than a
     * made-up number.
     */
    public function estimate(array $input): ?array
    {
        $goal = MetaGoals::find($input['goal'] ?? '') ?? [];
        $destination = MetaGoals::destination($input['goal'] ?? '', $input['destination'] ?? '') ?? [];
        $optimization = $input['adset']['optimization_goal'] ?? array_key_first($destination['optimization_goals'] ?? ['REACH' => 1]);

        $data = $this->graph->get($this->graph->actId() . '/delivery_estimate', array_filter([
            'targeting_spec' => $this->targeting($input),
            'optimization_goal' => $optimization,
            'promoted_object' => $this->promotedObject($input) ?: null,
        ]))['data'][0] ?? null;

        if (! $data || (! isset($data['estimate_mau_lower_bound']) && ! isset($data['estimate_mau_upper_bound']) && ! isset($data['estimate_mau']))) {
            return null;
        }

        return [
            'audience_min' => $data['estimate_mau_lower_bound'] ?? $data['estimate_mau'] ?? null,
            'audience_max' => $data['estimate_mau_upper_bound'] ?? $data['estimate_mau'] ?? null,
            'ready' => (bool) ($data['estimate_ready'] ?? true),
            'goal' => $goal['label'] ?? null,
        ];
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------

    /**
     * Create the ad, re-using/creating its campaign, ad set and creative.
     *
     * @return array{success: bool, ...}
     */
    public function submit(array $input, string $key): array
    {
        $userId = (int) $this->graph->account->user_id;
        $hash = hash('sha256', json_encode($input));

        $lock = Cache::lock("ad-builder:{$userId}:{$key}", 300);
        if (! $lock->get()) {
            return ['success' => false, 'status' => 409, 'message' => 'This campaign is already being created - wait for it to finish.'];
        }

        try {
            $run = AdBuilderRun::firstOrCreate(
                ['user_id' => $userId, 'idempotency_key' => $key],
                ['social_account_id' => $this->graph->account->id, 'payload_hash' => $hash, 'status' => 'running', 'results' => []]
            );

            if ($run->status === 'completed') {
                return $this->completedResponse($run, true);
            }
            if ($run->payload_hash !== $hash && ! $run->wasRecentlyCreated) {
                // Same key, changed settings: carry on with what exists but use the new settings for the rest.
                $run->forceFill(['payload_hash' => $hash])->save();
            }

            $run->forceFill(['status' => 'running', 'failed_step' => null, 'error' => null])->save();
            $step = 'validation';

            try {
                $this->validateSelection($input);

                $step = 'campaign';
                $campaign = $this->campaignStep($run, $input);
                $step = 'adset';
                $adSet = $this->adSetStep($run, $input, $campaign);
                $step = 'creative';
                $creative = $this->creativeStep($run, $input, $adSet);
                $step = 'ad';
                $this->adStep($run, $input, $campaign, $adSet, $creative);
            } catch (MetaApiException|\InvalidArgumentException $e) {
                $run->forceFill(['status' => 'failed', 'failed_step' => $step, 'error' => $e->getMessage()])->save();
                Log::warning('Facebook campaign builder step failed.', ['run' => $run->id, 'step' => $step, 'error' => $e->getMessage(), 'fbtrace_id' => $e instanceof MetaApiException ? $e->fbtraceId : null]);

                return [
                    'success' => false,
                    'status' => 422,
                    'step' => $step,
                    'message' => $e->getMessage(),
                    'created' => $this->createdSummary($run),
                    'retryable' => true,
                ];
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
            'message' => $replayed ? 'This ad was already created - nothing new was submitted.' : 'Your ad was created on Meta.',
            'created' => $this->createdSummary($run),
            'ad_id' => $run->result('ad.remote'),
            'campaign_local_id' => $run->result('campaign.local'),
            'status' => $run->result('ad.status', 'PAUSED'),
        ];
    }

    /** What exists on Meta for this run (shown after a failure so nothing is re-created by hand). */
    private function createdSummary(AdBuilderRun $run): array
    {
        return array_values(array_filter([
            $run->result('campaign.remote') ? ['type' => 'campaign', 'id' => $run->result('campaign.remote'), 'reused' => (bool) $run->result('campaign.reused')] : null,
            $run->result('adset.remote') ? ['type' => 'adset', 'id' => $run->result('adset.remote'), 'reused' => (bool) $run->result('adset.reused')] : null,
            $run->result('creative.remote') ? ['type' => 'creative', 'id' => $run->result('creative.remote'), 'reused' => (bool) $run->result('creative.reused')] : null,
            $run->result('ad.remote') ? ['type' => 'ad', 'id' => $run->result('ad.remote'), 'reused' => false] : null,
        ]));
    }

    /** Everything a request references must be the seller's: Page, audiences, pixel. */
    private function validateSelection(array $input): void
    {
        foreach (array_filter([$input['adset']['page_id'] ?? null, $input['creative']['page_id'] ?? null]) as $pageId) {
            $this->ownedPage((string) $pageId);
        }

        $audienceIds = array_merge($input['adset']['targeting']['custom_audiences'] ?? [], $input['adset']['targeting']['excluded_custom_audiences'] ?? []);
        if ($audienceIds) {
            $owned = collect($this->audiences()['custom'])->pluck('id')->map(fn ($id) => (string) $id);
            foreach ($audienceIds as $id) {
                if (! $owned->contains((string) $id)) {
                    throw new \InvalidArgumentException('One of the selected audiences does not belong to this ad account.');
                }
            }
        }

        if ($pixel = $input['adset']['pixel_id'] ?? null) {
            if (! collect($this->pixels())->pluck('id')->contains((string) $pixel)) {
                throw new \InvalidArgumentException('The selected Pixel / dataset is not available to this ad account.');
            }
        }
    }

    // ---- campaign -----------------------------------------------------

    private function campaignStep(AdBuilderRun $run, array $input): array
    {
        $goal = MetaGoals::find($input['goal']);
        $spec = $input['campaign'];

        if ($remote = $run->result('campaign.remote')) {
            return ['remote' => $remote, 'local' => $run->result('campaign.local'), 'campaign_budget' => (bool) $run->result('campaign.campaign_budget'), 'special' => $run->result('campaign.special', [])];
        }

        if (($spec['mode'] ?? 'new') === 'existing') {
            $remote = $this->ownedCampaign((string) $spec['id']);
            if (($remote['objective'] ?? null) !== $goal['objective']) {
                throw new \InvalidArgumentException("The campaign \"{$remote['name']}\" uses a different objective ({$remote['objective']}) than the goal you chose.");
            }

            $local = AdCampaign::updateOrCreate(['ad_campaign_id' => $remote['id'], 'platform' => 'facebook'], [
                'user_id' => $run->user_id,
                'social_account_id' => $this->graph->account->id,
                'name' => $remote['name'] ?? $remote['id'],
                'objective' => $remote['objective'] ?? null,
                'status' => strtolower($remote['effective_status'] ?? $remote['status'] ?? ''),
            ]);
            $special = array_values(array_diff($remote['special_ad_categories'] ?? [], ['NONE']));
            $campaignBudget = isset($remote['daily_budget']) || isset($remote['lifetime_budget']);
            $run->remember('campaign', ['remote' => $remote['id'], 'local' => $local->id, 'reused' => true, 'campaign_budget' => $campaignBudget, 'special' => $special]);

            return ['remote' => $remote['id'], 'local' => $local->id, 'campaign_budget' => $campaignBudget, 'special' => $special];
        }

        $status = ($input['ad']['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
        $special = array_values(array_filter([$spec['special_ad_category'] ?? null]));
        $campaignBudget = ! empty($spec['campaign_budget']);

        $payload = [
            'name' => $spec['name'],
            'objective' => $goal['objective'],
            'status' => $status,
            'buying_type' => 'AUCTION',
            'special_ad_categories' => $special,
        ];
        if ($campaignBudget) {
            $payload[($spec['budget_type'] ?? 'daily') === 'lifetime' ? 'lifetime_budget' : 'daily_budget'] = $this->graph->toMinor($spec['budget']);
            $payload['bid_strategy'] = $spec['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP';
        } else {
            $payload['is_adset_budget_sharing_enabled'] = false;
        }

        $created = $this->graph->post($this->graph->actId() . '/campaigns', $payload);

        $local = AdCampaign::create([
            'ad_campaign_id' => $created['id'],
            'platform' => 'facebook',
            'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id,
            'name' => $spec['name'],
            'objective' => $goal['objective'],
            'special_ad_category' => $special[0] ?? null,
            'status' => strtolower($status),
            'budget_mode' => $campaignBudget ? (($spec['budget_type'] ?? 'daily') === 'lifetime' ? 'lifetime_budget' : 'daily_budget') : null,
            'daily_budget' => $campaignBudget && ($spec['budget_type'] ?? 'daily') !== 'lifetime' ? $spec['budget'] : null,
            'budget' => $campaignBudget && ($spec['budget_type'] ?? 'daily') === 'lifetime' ? $spec['budget'] : null,
            'bidding_strategy' => $payload['bid_strategy'] ?? null,
        ]);
        $run->remember('campaign', ['remote' => $created['id'], 'local' => $local->id, 'reused' => false, 'campaign_budget' => $campaignBudget, 'special' => $special]);

        return ['remote' => $created['id'], 'local' => $local->id, 'campaign_budget' => $campaignBudget, 'special' => $special];
    }

    // ---- ad set -------------------------------------------------------

    private function adSetStep(AdBuilderRun $run, array $input, array $campaign): array
    {
        $spec = $input['adset'];

        if ($remote = $run->result('adset.remote')) {
            return ['remote' => $remote, 'local' => $run->result('adset.local')];
        }

        if (($spec['mode'] ?? 'new') === 'existing') {
            $remote = $this->ownedObject((string) $spec['id'], 'ad set', 'id,name,account_id,campaign_id,optimization_goal,billing_event,destination_type,status');
            if ((string) ($remote['campaign_id'] ?? '') !== (string) $campaign['remote']) {
                throw new \InvalidArgumentException('The selected ad set belongs to a different campaign.');
            }

            $local = AdAdGroup::updateOrCreate(['ad_adgroup_id' => $remote['id'], 'platform' => 'facebook'], [
                'user_id' => $run->user_id,
                'social_account_id' => $this->graph->account->id,
                'ad_campaign_id' => $campaign['local'],
                'name' => $remote['name'] ?? $remote['id'],
                'optimization_goal' => $remote['optimization_goal'] ?? null,
                'billing_event' => $remote['billing_event'] ?? null,
                'destination_type' => $remote['destination_type'] ?? null,
                'status' => false,
            ]);
            $run->remember('adset', ['remote' => $remote['id'], 'local' => $local->id, 'reused' => true]);

            return ['remote' => $remote['id'], 'local' => $local->id];
        }

        $destination = MetaGoals::destination($input['goal'], $input['destination']);
        if (! $destination) {
            throw new \InvalidArgumentException('That conversion location is not available for this goal.');
        }
        $optimization = $spec['optimization_goal'] ?? array_key_first($destination['optimization_goals']);
        if (! isset($destination['optimization_goals'][$optimization])) {
            throw new \InvalidArgumentException('That optimization goal is not available for this goal and conversion location.');
        }

        $status = ($input['ad']['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
        $start = ! empty($spec['start_time']) ? Carbon::parse($spec['start_time']) : now()->addMinutes(10);
        $end = ! empty($spec['end_time']) ? Carbon::parse($spec['end_time']) : null;

        $payload = array_filter([
            'name' => $spec['name'],
            'campaign_id' => $campaign['remote'],
            'status' => $status,
            'optimization_goal' => $optimization,
            'billing_event' => $destination['billing_event'],
            'destination_type' => MetaGoals::destinationType($input['destination']),
            'promoted_object' => $this->promotedObject($input) ?: null,
            'targeting' => $this->targeting($input, $campaign['special']),
            'start_time' => $start->toIso8601String(),
            'end_time' => $end?->toIso8601String(),
        ], fn ($v) => $v !== null);

        // Budget lives on the campaign when it uses Advantage campaign budget.
        if (! $campaign['campaign_budget']) {
            $budgetType = ($spec['budget_type'] ?? 'daily') === 'lifetime' ? 'lifetime_budget' : 'daily_budget';
            if ($budgetType === 'lifetime_budget' && ! $end) {
                throw new \InvalidArgumentException('A lifetime budget needs an end date.');
            }
            $payload[$budgetType] = $this->graph->toMinor($spec['budget']);
            $payload['bid_strategy'] = $spec['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP';
        }
        if (in_array($spec['bid_strategy'] ?? null, ['COST_CAP', 'LOWEST_COST_WITH_BID_CAP'], true)) {
            $payload['bid_amount'] = $this->graph->toMinor($spec['bid_amount'] ?? 0);
        }

        $created = $this->graph->post($this->graph->actId() . '/adsets', $payload);

        $local = AdAdGroup::create([
            'ad_adgroup_id' => $created['id'],
            'platform' => 'facebook',
            'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id,
            'ad_campaign_id' => $campaign['local'],
            'name' => $spec['name'],
            'optimization_goal' => $optimization,
            'billing_event' => $destination['billing_event'],
            'destination_type' => MetaGoals::destinationType($input['destination']),
            'budget_mode' => $campaign['campaign_budget'] ? null : ((($spec['budget_type'] ?? 'daily') === 'lifetime') ? 'lifetime_budget' : 'daily_budget'),
            'budget' => $campaign['campaign_budget'] ? null : $spec['budget'],
            'bid_strategy' => $payload['bid_strategy'] ?? null,
            'bid_price' => $spec['bid_amount'] ?? null,
            'schedule_start_time' => $start,
            'schedule_end_time' => $end,
            'promotion_target_type' => json_encode($payload['promoted_object'] ?? []),
            'targeting_criteria' => json_encode(['targeting' => $payload['targeting'], 'builder' => $spec['targeting'] ?? []]),
            'publisher_platforms' => json_encode($payload['targeting']['publisher_platforms'] ?? ['automatic']),
            'status' => false,
        ]);
        $run->remember('adset', ['remote' => $created['id'], 'local' => $local->id, 'reused' => false]);

        return ['remote' => $created['id'], 'local' => $local->id];
    }

    /** promoted_object fields the goal's destination needs. */
    public function promotedObject(array $input): array
    {
        $destination = MetaGoals::destination($input['goal'] ?? '', $input['destination'] ?? '') ?? [];
        $spec = $input['adset'] ?? [];
        $values = [
            'page_id' => $spec['page_id'] ?? ($input['creative']['page_id'] ?? null),
            'pixel_id' => $spec['pixel_id'] ?? null,
            'custom_event_type' => $spec['custom_event_type'] ?? ($destination['default_event'] ?? null),
            'application_id' => $spec['application_id'] ?? null,
            'object_store_url' => $spec['object_store_url'] ?? null,
        ];

        $object = [];
        foreach ($destination['promoted_object'] ?? [] as $field) {
            if (empty($values[$field])) {
                throw new \InvalidArgumentException(match ($field) {
                    'page_id' => 'Choose the Facebook Page this ad runs for.',
                    'pixel_id' => 'Choose the Meta Pixel (dataset) that tracks conversions on your website.',
                    'custom_event_type' => 'Choose the conversion event to optimize for.',
                    'application_id', 'object_store_url' => 'Choose your app and its store URL.',
                    default => "Missing {$field}.",
                });
            }
            $object[$field] = $values[$field];
        }

        return $object;
    }

    /**
     * Meta targeting spec from the builder's audience settings. Special Ad
     * Categories (housing, employment, credit, social issues) don't allow
     * age/gender narrowing, detailed targeting or lookalikes - those are
     * dropped/forced here so Meta doesn't reject the ad set.
     */
    public function targeting(array $input, array $special = []): array
    {
        $t = $input['adset']['targeting'] ?? [];
        $special = $special ?: array_values(array_filter([$input['campaign']['special_ad_category'] ?? null]));

        // A saved audience brings its own targeting (defined in Ads Manager).
        if (! empty($t['saved_audience_id'])) {
            $saved = $this->ownedObject((string) $t['saved_audience_id'], 'saved audience', 'id,account,targeting')['targeting'] ?? [];
            $spec = $saved;
        } else {
            $spec = ['geo_locations' => $this->geo($t['locations'] ?? [])];
            if (! empty($t['excluded_locations'])) {
                $spec['excluded_geo_locations'] = $this->geo($t['excluded_locations']);
            }

            $spec['age_min'] = max(18, (int) ($t['age_min'] ?? 18));
            $spec['age_max'] = min(65, (int) ($t['age_max'] ?? 65));
            $genders = array_values(array_intersect(array_map('intval', $t['genders'] ?? []), [1, 2]));
            if (count($genders) === 1) {
                $spec['genders'] = $genders;
            }
            if (! empty($t['locales'])) {
                $spec['locales'] = array_map(fn ($l) => (int) (is_array($l) ? $l['key'] : $l), $t['locales']);
            }

            // Detailed targeting: the first group is "any of"; extra groups narrow (AND).
            $groups = array_values(array_filter(array_merge([$t['interests'] ?? []], $t['narrow'] ?? [])));
            $flexible = [];
            foreach ($groups as $group) {
                $byType = [];
                foreach ($group as $item) {
                    $byType[$item['type'] ?? 'interests'][] = ['id' => (string) $item['id'], 'name' => $item['name'] ?? ''];
                }
                if ($byType) {
                    $flexible[] = $byType;
                }
            }
            if ($flexible) {
                $spec['flexible_spec'] = $flexible;
            }

            if (! empty($t['custom_audiences'])) {
                $spec['custom_audiences'] = array_map(fn ($id) => ['id' => (string) $id], $t['custom_audiences']);
            }
            if (! empty($t['excluded_custom_audiences'])) {
                $spec['excluded_custom_audiences'] = array_map(fn ($id) => ['id' => (string) $id], $t['excluded_custom_audiences']);
            }
        }

        // Advantage+ audience: the age/detailed settings become suggestions,
        // and Meta requires the maximum age to stay at 65.
        $advantage = ! empty($t['advantage_audience']) && empty($special);
        $spec['targeting_automation'] = ['advantage_audience' => $advantage ? 1 : 0];
        if ($advantage) {
            $spec['age_max'] = 65;
            $spec['age_min'] = min($spec['age_min'] ?? 18, 25);
        }

        $placements = $input['adset']['placements'] ?? [];
        if (($placements['mode'] ?? 'advantage') === 'manual') {
            $spec['publisher_platforms'] = array_values(array_intersect($placements['publisher_platforms'] ?? [], ['facebook', 'instagram', 'messenger', 'audience_network']));
            foreach (['facebook_positions', 'instagram_positions', 'messenger_positions', 'audience_network_positions'] as $key) {
                if (! empty($placements[$key])) {
                    $spec[$key] = array_values($placements[$key]);
                }
            }
            if (! empty($placements['device_platforms'])) {
                $spec['device_platforms'] = array_values(array_intersect($placements['device_platforms'], ['mobile', 'desktop']));
            }
        }

        if ($special) {
            unset($spec['genders'], $spec['flexible_spec']);
            $spec['age_min'] = 18;
            $spec['age_max'] = 65;
        }

        return $spec;
    }

    private function geo(array $locations): array
    {
        $geo = [];
        foreach ($locations as $l) {
            match ($l['type'] ?? 'country') {
                'country' => $geo['countries'][] = $l['country_code'] ?? $l['key'],
                'region' => $geo['regions'][] = ['key' => (string) $l['key']],
                'city' => $geo['cities'][] = array_filter([
                    'key' => (string) $l['key'],
                    'radius' => isset($l['radius']) ? (int) $l['radius'] : null,
                    'distance_unit' => isset($l['radius']) ? ($l['distance_unit'] ?? 'kilometer') : null,
                ]),
                default => null,
            };
        }

        return $geo;
    }

    // ---- creative -----------------------------------------------------

    private function creativeStep(AdBuilderRun $run, array $input, array $adSet): array
    {
        $spec = $input['creative'];

        if ($remote = $run->result('creative.remote')) {
            return ['remote' => $remote, 'local' => $run->result('creative.local')];
        }

        if (($spec['mode'] ?? 'new') === 'existing') {
            $remote = $this->ownedObject((string) $spec['id'], 'creative', 'id,name,account_id,object_story_spec{page_id}');
            $local = AdCreative::updateOrCreate(['ad_creative_id' => $remote['id'], 'platform' => 'facebook'], [
                'user_id' => $run->user_id,
                'social_account_id' => $this->graph->account->id,
                'ad_campaign_id' => $run->result('campaign.local'),
                'ad_adgroup_id' => $adSet['local'],
                'name' => $remote['name'] ?? $remote['id'],
                'page_id' => $remote['object_story_spec']['page_id'] ?? null,
            ]);
            $run->remember('creative', ['remote' => $remote['id'], 'local' => $local->id, 'reused' => true]);

            return ['remote' => $remote['id'], 'local' => $local->id];
        }

        $destination = MetaGoals::destination($input['goal'], $input['destination']) ?? [];
        $format = strtoupper($spec['format'] ?? 'IMAGE');
        $story = ['page_id' => (string) $spec['page_id']];
        if (! empty($spec['instagram_user_id'])) {
            $story['instagram_user_id'] = (string) $spec['instagram_user_id'];
        }

        $cta = $this->callToAction($input, $destination);
        $link = $this->creativeLink($input, $destination);

        if ($format === 'VIDEO') {
            $asset = $this->asset($spec['media'][0]['asset_id'] ?? null, 'video');
            $videoId = $this->uploadVideo($run, $asset);
            $thumb = $this->asset($spec['thumbnail_asset_id'] ?? null, 'image', required: false);
            $story['video_data'] = array_filter([
                'video_id' => $videoId,
                'message' => $spec['primary_text'] ?? null,
                'title' => $spec['headline'] ?? null,
                'link_description' => $spec['description'] ?? null,
                'image_hash' => $thumb ? $this->uploadImage($run, $thumb) : null,
                'image_url' => ! $thumb ? $asset->thumbnail_url : null,
                'call_to_action' => $cta,
            ]);
            if (empty($story['video_data']['image_hash']) && empty($story['video_data']['image_url'])) {
                throw new \InvalidArgumentException('Add a thumbnail image for the video.');
            }
        } elseif ($format === 'CAROUSEL') {
            $cards = $spec['cards'] ?? [];
            if (count($cards) < 2 || count($cards) > 10) {
                throw new \InvalidArgumentException('A carousel needs between 2 and 10 cards.');
            }
            $story['link_data'] = array_filter([
                'link' => $link,
                'message' => $spec['primary_text'] ?? null,
                'multi_share_optimized' => true,
                'call_to_action' => $cta,
                'child_attachments' => array_map(fn ($card) => array_filter([
                    'link' => $card['link'] ?? $link,
                    'image_hash' => $this->uploadImage($run, $this->asset($card['asset_id'] ?? null, 'image')),
                    'name' => $card['headline'] ?? null,
                    'description' => $card['description'] ?? null,
                    'call_to_action' => $cta,
                ]), $cards),
            ]);
        } else {
            $story['link_data'] = array_filter([
                'link' => $link,
                'message' => $spec['primary_text'] ?? null,
                'name' => $spec['headline'] ?? null,
                'description' => $spec['description'] ?? null,
                'image_hash' => $this->uploadImage($run, $this->asset($spec['media'][0]['asset_id'] ?? null, 'image')),
                'call_to_action' => $cta,
            ]);
        }

        $name = $spec['name'] ?? ('Creative ' . now()->format('Y-m-d H:i'));
        $created = $this->graph->post($this->graph->actId() . '/adcreatives', ['name' => $name, 'object_story_spec' => $story]);

        $local = AdCreative::create([
            'ad_creative_id' => $created['id'],
            'platform' => 'facebook',
            'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id,
            'ad_campaign_id' => $run->result('campaign.local'),
            'ad_adgroup_id' => $adSet['local'],
            'name' => $name,
            'type' => $format,
            'page_id' => $spec['page_id'],
            'message' => $spec['primary_text'] ?? null,
            'headline' => $spec['headline'] ?? null,
            'url' => $link,
            'call_to_action' => $cta['type'] ?? null,
            'lead_generation_form_id' => $spec['lead_form_id'] ?? null,
        ]);
        $run->remember('creative', ['remote' => $created['id'], 'local' => $local->id, 'reused' => false]);

        return ['remote' => $created['id'], 'local' => $local->id];
    }

    /** CTA with the value Meta needs for the destination (message apps, lead forms, links). */
    private function callToAction(array $input, array $destination): ?array
    {
        $spec = $input['creative'];
        $type = $spec['cta'] ?? ($destination['ctas'][0] ?? 'LEARN_MORE');
        if ($type === 'NO_BUTTON') {
            return null;
        }

        $value = match (true) {
            ! empty($destination['message_app']) => ['app_destination' => $destination['message_app']],
            ! empty($destination['needs_lead_form']) => ['lead_gen_form_id' => (string) ($spec['lead_form_id'] ?? '')],
            default => ['link' => $this->creativeLink($input, $destination)],
        };

        if (! empty($destination['needs_lead_form']) && empty($spec['lead_form_id'])) {
            throw new \InvalidArgumentException('Choose the Instant form people fill in.');
        }

        return ['type' => $type, 'value' => $value];
    }

    /** The link_data.link Meta requires: the website, or Meta's placeholder for message/lead ads. */
    private function creativeLink(array $input, array $destination): string
    {
        if (! empty($destination['message_app'])) {
            return $destination['message_app'] === 'WHATSAPP' ? 'https://api.whatsapp.com/send' : 'https://fb.com/messenger_doc/';
        }
        if (! empty($destination['needs_lead_form'])) {
            return 'http://fb.me/';
        }

        $link = trim((string) ($input['creative']['link'] ?? ($input['adset']['object_store_url'] ?? '')));

        // Awareness / engagement ads don't need a website: without one the
        // button (and link_data.link) points to the advertiser's Page.
        if ($link === '' && empty($destination['needs_link']) && ! empty($input['creative']['page_id'])) {
            return 'https://www.facebook.com/' . $input['creative']['page_id'];
        }
        if (! filter_var($link, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Add the website address (starting with https://) people go to.');
        }

        return $link;
    }

    private function asset(?int $assetId, string $type, bool $required = true): ?MediaAsset
    {
        $asset = $assetId ? MediaAsset::ownedBy((int) $this->graph->account->user_id)->find($assetId) : null;

        if (! $asset) {
            if ($required) {
                throw new \InvalidArgumentException($type === 'video' ? 'Choose a video for the ad.' : 'Choose an image for the ad.');
            }

            return null;
        }
        if ($asset->type !== $type) {
            throw new \InvalidArgumentException("\"{$asset->original_name}\" is not " . ($type === 'video' ? 'a video.' : 'an image.'));
        }

        return $asset;
    }

    /** /adimages -> image hash (remembered per asset, so a retry doesn't upload again). */
    private function uploadImage(AdBuilderRun $run, MediaAsset $asset): string
    {
        if ($hash = $run->result("media.image_{$asset->id}")) {
            return $hash;
        }

        $bytes = Http::timeout(120)->get($asset->url);
        if (! $bytes->successful()) {
            throw new \InvalidArgumentException("Couldn't read \"{$asset->original_name}\" from your Media Gallery.");
        }

        $data = $this->graph->post($this->graph->actId() . '/adimages', ['bytes' => base64_encode($bytes->body())]);
        $image = collect($data['images'] ?? [])->first();
        if (empty($image['hash'])) {
            throw new MetaApiException("Meta didn't accept the image \"{$asset->original_name}\".");
        }

        AdMedia::create([
            'ad_media_id' => $image['hash'], 'platform' => 'facebook', 'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id, 'name' => $asset->original_name, 'file_name' => $asset->original_name,
            'url' => $image['url'] ?? $asset->url, 'download_link' => $asset->url, 'type' => 'IMAGE', 'status' => false,
            'signature' => $image['hash'], 'file_id' => $image['hash'], 'upload_by_type' => 'UPLOAD_BY_FILE', 'ad_format' => 'FEED',
        ]);
        $run->remember("media.image_{$asset->id}", $image['hash']);

        return $image['hash'];
    }

    /** /advideos (Meta fetches the file from the gallery URL) -> video id. */
    private function uploadVideo(AdBuilderRun $run, MediaAsset $asset): string
    {
        if ($id = $run->result("media.video_{$asset->id}")) {
            return $id;
        }

        $data = $this->graph->post($this->graph->actId() . '/advideos', ['file_url' => $asset->url, 'name' => $asset->original_name]);
        if (empty($data['id'])) {
            throw new MetaApiException("Meta didn't accept the video \"{$asset->original_name}\".");
        }

        AdMedia::create([
            'ad_media_id' => $data['id'], 'platform' => 'facebook', 'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id, 'name' => $asset->original_name, 'file_name' => $asset->original_name,
            'url' => $asset->url, 'download_link' => $asset->url, 'type' => 'VIDEO', 'status' => false,
            'signature' => $data['id'], 'file_id' => $data['id'], 'upload_by_type' => 'UPLOAD_BY_URL', 'ad_format' => 'FEED',
            'thumbnail_url' => $asset->thumbnail_url,
        ]);
        $run->remember("media.video_{$asset->id}", $data['id']);

        return $data['id'];
    }

    // ---- ad -----------------------------------------------------------

    private function adStep(AdBuilderRun $run, array $input, array $campaign, array $adSet, array $creative): void
    {
        if ($run->result('ad.remote')) {
            return;
        }

        $status = ($input['ad']['status'] ?? 'PAUSED') === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
        $name = $input['ad']['name'] ?? ($input['adset']['name'] ?? 'Ad') . ' - Ad';

        $created = $this->graph->post($this->graph->actId() . '/ads', [
            'name' => $name,
            'adset_id' => $adSet['remote'],
            'creative' => ['creative_id' => $creative['remote']],
            'status' => $status,
        ]);

        $local = Ad::create([
            'ad_id' => $created['id'],
            'platform' => 'facebook',
            'user_id' => $run->user_id,
            'social_account_id' => $this->graph->account->id,
            'ad_campaign_id' => $campaign['local'],
            'ad_adgroup_id' => $adSet['local'],
            'ad_creative_id' => $creative['local'],
            'name' => $name,
            'status' => strtolower($status),
            'call_to_action' => $input['creative']['cta'] ?? null,
        ]);
        $run->remember('ad', ['remote' => $created['id'], 'local' => $local->id, 'status' => $status]);
    }

    // ------------------------------------------------------------------
    // Ownership
    // ------------------------------------------------------------------

    /** A campaign of THIS ad account, or an error. */
    private function ownedCampaign(string $id): array
    {
        return $this->ownedObject($id, 'campaign', 'id,name,account_id,objective,status,effective_status,special_ad_categories,daily_budget,lifetime_budget,bid_strategy');
    }

    /** Fetches $id and checks its account_id is the selected ad account. */
    private function ownedObject(string $id, string $label, string $fields = 'id,name,account_id'): array
    {
        if (! preg_match('/^\d+$/', $id)) {
            throw new \InvalidArgumentException("That {$label} id isn't valid.");
        }

        try {
            $object = $this->graph->get($id, ['fields' => $fields]);
        } catch (MetaApiException $e) {
            throw new \InvalidArgumentException("That {$label} wasn't found in this ad account (" . $e->getMessage() . ')');
        }

        $accountId = (string) ($object['account_id'] ?? ($object['account']['account_id'] ?? $object['account']['id'] ?? ''));
        if (preg_replace('/^act_/', '', $accountId) !== $this->graph->accountId()) {
            throw new \InvalidArgumentException("That {$label} belongs to a different ad account.");
        }

        return $object;
    }

    private function ownedPage(string $pageId): void
    {
        $owned = PlatformPage::where('user_id', $this->graph->account->user_id)->where('platform', 'facebook')->where('page_id', $pageId)->exists();
        if (! $owned) {
            throw new \InvalidArgumentException('That Facebook Page is not connected to your account. Reconnect your ad account to refresh your Pages.');
        }
    }

    /** "Saudi Arabia, Riyadh +20 km · 25-45 · 3 interests" for existing ad sets / saved audiences. */
    private function targetingSummary(array $t): string
    {
        $geo = $t['geo_locations'] ?? [];
        $places = array_merge($geo['countries'] ?? [], array_column($geo['regions'] ?? [], 'name'), array_column($geo['cities'] ?? [], 'name'));
        $parts = array_filter([
            $places ? implode(', ', array_slice($places, 0, 3)) . (count($places) > 3 ? ' +' . (count($places) - 3) : '') : null,
            isset($t['age_min']) ? ($t['age_min'] . '-' . ($t['age_max'] ?? '65+')) : null,
            ! empty($t['flexible_spec']) ? count(array_merge(...array_map(fn ($g) => array_merge(...array_values($g)), $t['flexible_spec']))) . ' detailed targeting' : null,
            ! empty($t['custom_audiences']) ? count($t['custom_audiences']) . ' custom audience(s)' : null,
        ]);

        return implode(' · ', $parts) ?: 'Broad audience';
    }
}
