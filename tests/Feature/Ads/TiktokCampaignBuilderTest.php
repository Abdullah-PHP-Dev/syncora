<?php

namespace Tests\Feature\Ads;

use App\Http\Controllers\Admin\TiktokAdBuilderController;
use App\Http\Requests\Admin\TiktokAdBuilderRequest;
use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\TiktokCampaignBuilder;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * TikTok campaign builder with the Marketing API faked: payload mapping
 * (objective, bidding, schedule, every targeting group), Spark Ads,
 * ownership checks, TikTok's targeting rules, idempotent retries and the
 * lookups. Response shapes match live v1.3 responses.
 */
class TiktokCampaignBuilderTest extends TestCase
{
    use CreatesConnectionTables, CreatesAdTables;

    private const BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    private User $user;

    private SocialAccount $account;

    private array $routes = [];

    /** Every TikTok call: [method, path, params]. */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        (require database_path('migrations/2026_10_14_120000_create_ad_builder_runs_table.php'))->up();
        $this->createAdTables();
        Settings::set('ads.tiktok.base_url', self::BASE);
        Cache::flush();

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'tiktok', 'platform_account_id' => '7001', 'name' => 'Socialeaz_adv',
            'account_type' => 'ad_account', 'access_token' => 'tt-token', 'expires_at' => now()->addYear(),
            'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
        DB::table('media_assets')->insert([
            ['id' => 1, 'user_id' => $this->user->id, 'type' => 'video', 'path' => 'v.mp4', 'url' => 'https://cdn.socialeaz.com/v.mp4', 'original_name' => 'promo.mp4', 'extension' => 'mp4', 'mime_type' => 'video/mp4', 'size' => 10, 'thumbnail_url' => 'https://cdn.socialeaz.com/v.jpg'],
            ['id' => 2, 'user_id' => $this->user->id, 'type' => 'image', 'path' => 'a.jpg', 'url' => 'https://cdn.socialeaz.com/a.jpg', 'original_name' => 'banner.jpg', 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'thumbnail_url' => null],
        ]);

        $this->route('GET advertiser/info/', ['list' => [['name' => 'Socialeaz_adv', 'currency' => 'SAR', 'timezone' => 'Asia/Riyadh', 'display_timezone' => 'Asia/Riyadh', 'status' => 'STATUS_ENABLE', 'country' => 'SA']]]);
        $this->route('GET identity/get/', ['identity_list' => [
            ['identity_id' => 'idt-1', 'identity_type' => 'TT_USER', 'display_name' => 'Socialeaz', 'username' => 'socialeaz', 'available_status' => 'AVAILABLE', 'can_pull_video' => true, 'can_push_video' => true, 'profile_image' => 'https://p16/x.jpg', 'identity_authorized_bc_id' => null],
            ['identity_id' => 'idt-custom', 'identity_type' => 'CUSTOMIZED_USER', 'display_name' => 'Old custom'],
        ]]);
        $this->route('GET dmp/custom_audience/list/', ['list' => [['audience_id' => '880', 'name' => 'Buyers', 'audience_type' => 'Customer File', 'cover_num' => 12000, 'is_valid' => true, 'is_expired' => false], ['audience_id' => '881', 'name' => 'Lookalike 5%', 'audience_type' => 'Lookalike Audience', 'is_valid' => true, 'is_expired' => false]]]);
        $this->route('GET pixel/list/', ['pixels' => [['pixel_id' => '555', 'pixel_name' => 'Store pixel', 'events' => [['optimization_event' => 'SHOPPING', 'name' => 'Complete payment'], ['optimization_event' => 'ON_WEB_CART', 'name' => 'Add to cart']]]]]);
        $this->route('GET app/list/', ['apps' => [['app_id' => '9100', 'app_name' => 'Syncora', 'platform' => 'IOS']]]);
        $this->route('GET page/get/', ['list' => [['page_id' => '4400', 'title' => 'Get a quote form']]]);
        $this->route('GET campaign/get/', ['list' => [['campaign_id' => '1800', 'campaign_name' => 'Traffic Q4', 'objective_type' => 'TRAFFIC', 'budget_mode' => 'BUDGET_MODE_INFINITE', 'operation_status' => 'ENABLE', 'budget_optimize_on' => false]]]);
        $this->route('POST campaign/create/', ['campaign_id' => '1900']);
        $this->route('POST adgroup/create/', ['adgroup_id' => '2900']);
        $this->route('POST ad/create/', ['ad_ids' => ['3900']]);
        $this->route('POST file/video/ad/upload/', [['video_id' => 'v-77']]);
        $this->route('POST file/image/ad/upload/', ['image_id' => 'img-88']);
        $this->route('GET file/video/ad/info/', ['list' => [['video_id' => 'v-77', 'video_cover_url' => 'https://cdn.socialeaz.com/cover.jpg']]]);
        $this->route('GET file/music/get/', ['musics' => [['music_id' => 'm-1']]]);

        Http::fake(function (HttpRequest $request) {
            if (str_starts_with($request->url(), 'https://cdn.socialeaz.com/')) {
                return Http::response('file-bytes');
            }
            $path = strtok(substr($request->url(), strlen(self::BASE)), '?');
            $params = $request->method() === 'GET' ? $request->data() : ($request->isJson() ? $request->data() : ['multipart' => true]);
            $this->calls[] = [$request->method(), $path, $params];

            $response = $this->routes[$request->method() . ' ' . $path] ?? null;
            if ($response === null) {
                return Http::response(['code' => 40002, 'message' => "Unfaked {$request->method()} {$path}", 'data' => []]);
            }
            if ($response instanceof \Closure) {
                $response = $response($params);
            }

            return isset($response['code']) && is_int($response['code']) ? Http::response($response) : Http::response(['code' => 0, 'message' => 'OK', 'request_id' => 'r1', 'data' => $response]);
        });
    }

    private function route(string $key, $response): void
    {
        $this->routes[$key] = $response;
    }

    private function builder(): TiktokCampaignBuilder
    {
        return TiktokCampaignBuilder::forUser($this->user->id);
    }

    private function posts(string $path): array
    {
        return array_values(array_map(fn ($c) => $c[2], array_filter($this->calls, fn ($c) => $c[0] === 'POST' && $c[1] === $path)));
    }

    /** A complete Traffic submission; $overrides are merged with array_replace_recursive. */
    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'objective' => 'TRAFFIC',
            'campaign' => ['mode' => 'new', 'name' => 'Launch', 'budget_optimize_on' => false],
            'adgroup' => [
                'name' => 'KSA 18-34', 'optimization_goal' => 'CLICK', 'placement_type' => 'AUTOMATIC', 'placements' => [],
                'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 100, 'schedule_start' => '2026-11-01T09:00', 'schedule_end' => null,
                'bid_type' => 'BID_TYPE_NO_BID', 'pacing' => 'PACING_MODE_SMOOTH',
                'targeting' => ['locations' => [['id' => '102358', 'type' => 'COUNTRY']], 'gender' => 'GENDER_UNLIMITED', 'age_groups' => ['AGE_18_24', 'AGE_25_34']],
            ],
            'ad' => [
                'identity_id' => 'idt-1', 'identity_type' => 'TT_USER', 'source' => 'upload', 'format' => 'SINGLE_VIDEO', 'media_id' => 1,
                'text' => 'Plan your posts in minutes', 'cta' => 'LEARN_MORE', 'url' => 'https://socialeaz.com/?utm_source=tiktok', 'status' => 'DISABLE',
            ],
        ], $overrides);
    }

    private function requestErrors(array $data): array
    {
        $request = TiktokAdBuilderRequest::create('/', 'POST', $data + ['idempotency_key' => 'key-validation-0001']);
        $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $this->user);

        try {
            $request->validateResolved();

            return [];
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    public function test_bootstrap_reads_the_advertiser_and_drops_custom_identities(): void
    {
        $boot = $this->builder()->bootstrap();

        $this->assertSame('7001', $boot['account']['id']);
        $this->assertSame('SAR', $boot['account']['currency']);
        $this->assertSame('Asia/Riyadh', $boot['account']['timezone']);
        $this->assertTrue($boot['account']['active']);
        $this->assertSame(['idt-1'], array_column($boot['identities'], 'id'));
        $this->assertArrayHasKey('WEB_CONVERSIONS', $boot['objectives']);
        $this->assertSame('tt-token', collect(Http::recorded())->first()[0]->header('Access-Token')[0]);
    }

    public function test_no_builder_without_a_tiktok_ads_account(): void
    {
        $this->account->forceFill(['has_ads_permission' => false])->save();

        $this->assertNull(TiktokCampaignBuilder::forUser($this->user->id));
    }

    public function test_new_traffic_campaign_maps_every_field(): void
    {
        $result = $this->builder()->submit($this->input([
            'adgroup' => [
                'placement_type' => 'NORMAL', 'placements' => ['PLACEMENT_TIKTOK'], 'comment_disabled' => true, 'search_result_enabled' => false,
                'schedule_end' => '2026-11-30T23:00', 'dayparting' => ['enabled' => true, 'days' => [0, 1], 'from' => 9, 'to' => 18],
                'targeting' => [
                    'locations' => [['id' => '102358', 'type' => 'COUNTRY'], ['id' => 'z-90210', 'type' => 'ZIP_CODE']],
                    'languages' => ['ar', 'en'], 'gender' => 'GENDER_FEMALE', 'spending_power' => 'HIGH',
                    'audience_ids' => ['880'], 'excluded_audience_ids' => ['881'],
                    'interests' => [
                        ['id' => '23123', 'type' => 'GENERAL_INTEREST'], ['id' => '2052203207', 'type' => 'ADDITIONAL_INTEREST'],
                        ['id' => '2011100', 'type' => 'VIDEO_INTERACTION'], ['id' => '21', 'type' => 'CREATOR_INTERACTION'], ['id' => '1247325189046', 'type' => 'HASHTAG_INTERACTION'],
                    ],
                    'action_period' => 7, 'video_actions' => ['LIKED', 'SHARED'],
                    'operating_systems' => ['IOS'], 'min_ios_version' => '16.0', 'network_types' => ['WIFI', '5G'],
                    'device_price' => ['min' => 220, 'max' => 1000], 'smart_audience' => true,
                ],
            ],
        ]), 'key-traffic-000000001');

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame('3900', $result['ad_id']);

        $campaign = $this->posts('campaign/create/')[0];
        $this->assertSame(['TRAFFIC', 'BUDGET_MODE_INFINITE', 'DISABLE', 'Launch'], [$campaign['objective_type'], $campaign['budget_mode'], $campaign['operation_status'], $campaign['campaign_name']]);
        $this->assertSame('7001', $campaign['advertiser_id']);
        $this->assertArrayNotHasKey('budget', $campaign);

        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame('1900', $g['campaign_id']);
        $this->assertSame(['PLACEMENT_TYPE_NORMAL', ['PLACEMENT_TIKTOK']], [$g['placement_type'], $g['placements']]);
        $this->assertSame(['CLICK', 'CPC', 'WEBSITE'], [$g['optimization_goal'], $g['billing_event'], $g['promotion_type']]);
        $this->assertSame(['BUDGET_MODE_DAY', 100.0], [$g['budget_mode'], $g['budget']]);
        $this->assertSame(['BID_TYPE_NO_BID', 'PACING_MODE_SMOOTH'], [$g['bid_type'], $g['pacing']]);
        // Asia/Riyadh is UTC+3.
        $this->assertSame(['SCHEDULE_START_END', '2026-11-01 06:00:00', '2026-11-30 20:00:00'], [$g['schedule_type'], $g['schedule_start_time'], $g['schedule_end_time']]);
        $this->assertSame(336, strlen($g['dayparting']));
        $this->assertSame(str_repeat('0', 18) . str_repeat('1', 18) . str_repeat('0', 12), substr($g['dayparting'], 0, 48));
        $this->assertSame(str_repeat('0', 48), substr($g['dayparting'], 96, 48));
        $this->assertTrue($g['comment_disabled']);
        $this->assertFalse($g['search_result_enabled']);
        $this->assertSame(['102358'], $g['location_ids']);
        $this->assertSame(['z-90210'], $g['zipcode_ids']);
        $this->assertSame(['ar', 'en'], $g['languages']);
        $this->assertSame('GENDER_FEMALE', $g['gender']);
        $this->assertSame(['AGE_18_24', 'AGE_25_34'], $g['age_groups']);
        $this->assertSame('HIGH', $g['spending_power']);
        $this->assertSame(['880'], $g['audience_ids']);
        $this->assertSame(['881'], $g['excluded_audience_ids']);
        $this->assertSame(['23123'], $g['interest_category_ids']);
        $this->assertSame(['2052203207'], $g['interest_keyword_ids']);
        $this->assertSame([
            ['action_scene' => 'VIDEO_RELATED', 'action_period' => 7, 'video_user_actions' => ['LIKED', 'SHARED'], 'action_category_ids' => ['2011100']],
            ['action_scene' => 'CREATOR_RELATED', 'action_period' => 0, 'video_user_actions' => ['FOLLOWING', 'VIEW_HOMEPAGE'], 'action_category_ids' => ['21']],
            ['action_scene' => 'HASHTAG_RELATED', 'action_period' => 0, 'video_user_actions' => ['VIEW_HASHTAG'], 'action_category_ids' => ['1247325189046']],
        ], $g['actions']);
        $this->assertSame(['IOS'], $g['operating_systems']);
        $this->assertSame('16.0', $g['min_ios_version']);
        $this->assertSame(['WIFI', '5G'], $g['network_types']);
        $this->assertSame([200, 10000], $g['device_price_ranges']);
        $this->assertTrue($g['smart_audience_enabled']);
        $this->assertArrayNotHasKey('smart_interest_behavior_enabled', $g);

        $ad = $this->posts('ad/create/')[0];
        $this->assertSame('2900', $ad['adgroup_id']);
        $creative = $ad['creatives'][0];
        $this->assertSame(['SINGLE_VIDEO', 'v-77', ['img-88']], [$creative['ad_format'], $creative['video_id'], $creative['image_ids']]);
        $this->assertSame(['idt-1', 'TT_USER', 'Socialeaz'], [$creative['identity_id'], $creative['identity_type'], $creative['display_name']]);
        $this->assertSame(['https://socialeaz.com/?utm_source=tiktok', 'LEARN_MORE', 'Plan your posts in minutes', 'DISABLE'], [$creative['landing_page_url'], $creative['call_to_action'], $creative['ad_text'], $creative['operation_status']]);

        $this->assertDatabaseHas('ad_campaigns', ['ad_campaign_id' => '1900', 'platform' => 'tiktok', 'objective' => 'TRAFFIC']);
        $this->assertDatabaseHas('ad_adgroups', ['ad_adgroup_id' => '2900', 'platform' => 'tiktok', 'optimization_goal' => 'CLICK', 'billing_event' => 'CPC']);
        $this->assertDatabaseHas('ad_creatives', ['ad_creative_id' => '3900', 'platform' => 'tiktok', 'ad_format' => 'SINGLE_VIDEO']);
        $this->assertDatabaseHas('ads', ['ad_id' => '3900', 'platform' => 'tiktok']);
        $this->assertSame('completed', AdBuilderRun::first()->status);
        $this->assertSame('tiktok', AdBuilderRun::first()->platform);
    }

    public function test_the_same_key_never_creates_twice(): void
    {
        $this->builder()->submit($this->input(), 'key-replay-0000000001');
        $again = $this->builder()->submit($this->input(), 'key-replay-0000000001');

        $this->assertTrue($again['replayed']);
        $this->assertCount(1, $this->posts('campaign/create/'));
        $this->assertCount(1, $this->posts('ad/create/'));
        $this->assertSame(1, Ad::count());
    }

    public function test_a_failed_ad_is_retried_without_recreating_the_campaign_or_ad_group(): void
    {
        $this->route('POST ad/create/', ['code' => 40002, 'message' => 'The landing page URL is invalid.', 'data' => []]);
        $failed = $this->builder()->submit($this->input(), 'key-retry-00000000001');

        $this->assertFalse($failed['success']);
        $this->assertSame('ad', $failed['step']);
        $this->assertSame('The landing page URL is invalid.', $failed['message']);
        $this->assertSame(['campaign', 'adgroup'], array_column($failed['created'], 'type'));

        $this->route('POST ad/create/', ['ad_ids' => ['3901']]);
        $ok = $this->builder()->submit($this->input(), 'key-retry-00000000001');

        $this->assertTrue($ok['success']);
        $this->assertCount(1, $this->posts('campaign/create/'));
        $this->assertCount(1, $this->posts('adgroup/create/'));
        $this->assertCount(1, $this->posts('file/video/ad/upload/'));
        $this->assertSame(1, AdCampaign::count());
        $this->assertSame(1, AdAdGroup::count());
    }

    public function test_web_conversions_with_cost_cap_cbo_and_publish(): void
    {
        $result = $this->builder()->submit($this->input([
            'objective' => 'WEB_CONVERSIONS',
            'campaign' => ['budget_optimize_on' => true, 'budget_mode' => 'BUDGET_MODE_DAY', 'budget' => 300, 'special_industries' => ['CREDIT']],
            'adgroup' => ['optimization_goal' => 'CONVERT', 'pixel_id' => '555', 'optimization_event' => 'SHOPPING', 'bid_type' => 'BID_TYPE_CUSTOM', 'bid' => 25, 'pacing' => 'PACING_MODE_FAST'],
            'ad' => ['format' => 'SINGLE_IMAGE', 'media_id' => 2, 'cta' => 'SHOP_NOW', 'status' => 'ENABLE'],
        ]), 'key-conv-000000000001');

        $this->assertTrue($result['success'], json_encode($result));
        $campaign = $this->posts('campaign/create/')[0];
        $this->assertSame(['BUDGET_MODE_DAY', 300.0, true, ['CREDIT'], 'ENABLE'], [$campaign['budget_mode'], $campaign['budget'], $campaign['budget_optimize_on'], $campaign['special_industries'], $campaign['operation_status']]);

        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame(['CONVERT', 'OCPM', '555', 'SHOPPING'], [$g['optimization_goal'], $g['billing_event'], $g['pixel_id'], $g['optimization_event']]);
        $this->assertSame(['BID_TYPE_CUSTOM', 25.0, 'PACING_MODE_FAST'], [$g['bid_type'], $g['conversion_bid_price'], $g['pacing']]);
        $this->assertArrayNotHasKey('budget', $g);
        $this->assertSame('SCHEDULE_FROM_NOW', $g['schedule_type']);

        $creative = $this->posts('ad/create/')[0]['creatives'][0];
        $this->assertSame(['SINGLE_IMAGE', ['img-88'], 'm-1', 555, 'ENABLE'], [$creative['ad_format'], $creative['image_ids'], $creative['music_id'], $creative['tracking_pixel_id'], $creative['operation_status']]);
    }

    public function test_spark_ad_uses_the_post_and_uploads_nothing(): void
    {
        $result = $this->builder()->submit($this->input([
            'objective' => 'ENGAGEMENT',
            'adgroup' => ['optimization_goal' => 'PAGE_VISIT'],
            'ad' => ['source' => 'spark', 'tiktok_item_id' => '7696184504683121941', 'media_id' => null, 'text' => null, 'url' => null],
        ]), 'key-spark-00000000001');

        $this->assertTrue($result['success'], json_encode($result));
        $creative = $this->posts('ad/create/')[0]['creatives'][0];
        $this->assertSame(['SINGLE_VIDEO', '7696184504683121941', 'PROFILE_PAGE'], [$creative['ad_format'], $creative['tiktok_item_id'], $creative['tiktok_page_category']]);
        $this->assertArrayNotHasKey('video_id', $creative);
        $this->assertArrayNotHasKey('landing_page_url', $creative);
        $this->assertSame([], $this->posts('file/video/ad/upload/'));
        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame('CPC', $g['billing_event']);
        $this->assertArrayNotHasKey('promotion_type', $g);
    }

    public function test_app_promotion_targets_the_apps_os(): void
    {
        $this->builder()->submit($this->input([
            'objective' => 'APP_PROMOTION',
            'campaign' => ['app_promotion_type' => 'APP_INSTALL'],
            'adgroup' => ['optimization_goal' => 'INSTALL', 'app_id' => '9100', 'targeting' => ['operating_systems' => ['ANDROID']]],
            'ad' => ['url' => null, 'cta' => 'DOWNLOAD_NOW'],
        ]), 'key-app-0000000000001');

        $this->assertSame('APP_INSTALL', $this->posts('campaign/create/')[0]['app_promotion_type']);
        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame(['9100', 'APP_IOS', ['IOS'], 'OCPM'], [$g['app_id'], $g['promotion_type'], $g['operating_systems'], $g['billing_event']]);
    }

    public function test_lead_generation_uses_the_instant_form(): void
    {
        $result = $this->builder()->submit($this->input([
            'objective' => 'LEAD_GENERATION',
            'adgroup' => ['optimization_goal' => 'LEAD_GENERATION'],
            'ad' => ['url' => null, 'lead_form_id' => '4400', 'cta' => 'GET_QUOTE'],
        ]), 'key-lead-000000000001');

        $this->assertTrue($result['success'], json_encode($result));
        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame(['LEAD_GENERATION', 'INSTANT_PAGE'], [$g['promotion_type'], $g['promotion_target_type']]);
        $creative = $this->posts('ad/create/')[0]['creatives'][0];
        $this->assertSame([4400, 'GET_QUOTE'], [$creative['page_id'], $creative['call_to_action']]);
    }

    public function test_existing_campaign_is_reused_and_must_match_the_objective(): void
    {
        $ok = $this->builder()->submit($this->input(['campaign' => ['mode' => 'existing', 'id' => '1800']]), 'key-exist-00000000001');
        $this->assertTrue($ok['success'], json_encode($ok));
        $this->assertSame([], $this->posts('campaign/create/'));
        $this->assertSame('1800', $this->posts('adgroup/create/')[0]['campaign_id']);
        $this->assertTrue($ok['created'][0]['reused']);

        $wrong = $this->builder()->submit($this->input(['objective' => 'REACH', 'adgroup' => ['optimization_goal' => 'REACH'], 'campaign' => ['mode' => 'existing', 'id' => '1800']]), 'key-exist-00000000002');
        $this->assertFalse($wrong['success']);
        $this->assertStringContainsString('TRAFFIC', $wrong['message']);
    }

    public function test_references_outside_the_advertiser_are_refused_before_anything_is_created(): void
    {
        $cases = [
            ['adgroup' => ['targeting' => ['audience_ids' => ['999']]]],
            ['ad' => ['identity_id' => 'idt-custom', 'identity_type' => 'CUSTOMIZED_USER']],
            ['ad' => ['identity_id' => 'someone-else']],
            ['ad' => ['media_id' => 999]],
            ['ad' => ['tracking_pixel_id' => '000']],
        ];
        foreach ($cases as $i => $override) {
            $result = $this->builder()->submit($this->input($override), 'key-owner-00000000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $this->assertFalse($result['success'], json_encode($override));
            $this->assertSame('validation', $result['step'], json_encode($override));
        }
        $this->assertSame([], $this->posts('campaign/create/'));
        $this->assertSame([], $this->posts('ad/create/'));
    }

    public function test_tiktok_targeting_rules_are_enforced(): void
    {
        $behaviorOnAuto = $this->builder()->submit($this->input(['adgroup' => ['targeting' => ['interests' => [['id' => '2011100', 'type' => 'VIDEO_INTERACTION']]]]]), 'key-rules-00000000001');
        $this->assertSame(__('tiktok_builder.server.actions_tiktok_only'), $behaviorOnAuto['message']);

        $conflict = $this->builder()->submit($this->input(['adgroup' => ['targeting' => ['interests' => [['id' => '1', 'type' => 'PURCHASE_INTENTION'], ['id' => '2', 'type' => 'ADDITIONAL_INTEREST']]]]]), 'key-rules-00000000002');
        $this->assertSame(__('tiktok_builder.server.purchase_conflict'), $conflict['message']);

        $reachPurchase = $this->builder()->submit($this->input(['objective' => 'REACH', 'adgroup' => ['optimization_goal' => 'REACH', 'targeting' => ['interests' => [['id' => '1', 'type' => 'PURCHASE_INTENTION']]]]]), 'key-rules-00000000003');
        $this->assertSame(__('tiktok_builder.server.purchase_objective'), $reachPurchase['message']);

        $devices = $this->builder()->submit($this->input(['adgroup' => ['targeting' => ['device_model_ids' => ['d1'], 'device_price' => ['min' => 0, 'max' => 500]]]]), 'key-rules-00000000004');
        $this->assertSame(__('tiktok_builder.server.device_conflict'), $devices['message']);

        $goal = $this->builder()->submit($this->input(['adgroup' => ['optimization_goal' => 'REACH']]), 'key-rules-00000000005');
        $this->assertSame(__('tiktok_builder.server.goal_mismatch'), $goal['message']);

        $this->assertSame([], $this->posts('campaign/create/'));
    }

    public function test_purchase_intent_maps_to_its_own_field(): void
    {
        $this->builder()->submit($this->input(['adgroup' => ['targeting' => ['interests' => [['id' => '20109', 'type' => 'PURCHASE_INTENTION']]]]]), 'key-purchase-00000001');

        $g = $this->posts('adgroup/create/')[0];
        $this->assertSame(['20109'], $g['purchase_intention_keyword_ids']);
        $this->assertArrayNotHasKey('interest_keyword_ids', $g);
    }

    public function test_request_validation(): void
    {
        $base = $this->input();
        $this->assertSame([], $this->requestErrors($base));

        $emoji = $this->requestErrors(array_replace_recursive($base, ['ad' => ['text' => 'Big sale 🔥']]));
        $this->assertArrayHasKey('ad.text', $emoji);

        $long = $this->requestErrors(array_replace_recursive($base, ['ad' => ['text' => str_repeat('a', 101)]]));
        $this->assertArrayHasKey('ad.text', $long);

        $lifetime = $this->requestErrors(array_replace_recursive($base, ['adgroup' => ['budget_mode' => 'BUDGET_MODE_TOTAL']]));
        $this->assertArrayHasKey('adgroup.schedule_end', $lifetime);

        $noUrl = $this->requestErrors(array_replace_recursive($base, ['ad' => ['url' => null]]));
        $this->assertArrayHasKey('ad.url', $noUrl);

        $noLocation = $base;
        $noLocation['adgroup']['targeting']['locations'] = [];
        $this->assertArrayHasKey('adgroup.targeting.locations', $this->requestErrors($noLocation));

        $pixel = $this->requestErrors(array_replace_recursive($base, ['objective' => 'WEB_CONVERSIONS', 'adgroup' => ['optimization_goal' => 'CONVERT']]));
        $this->assertArrayHasKey('adgroup.pixel_id', $pixel);
        $this->assertArrayHasKey('adgroup.optimization_event', $pixel);

        $customBid = $this->requestErrors(array_replace_recursive($base, ['adgroup' => ['bid_type' => 'BID_TYPE_CUSTOM']]));
        $this->assertArrayHasKey('adgroup.bid', $customBid);

        $badType = $this->requestErrors(array_replace_recursive($base, ['adgroup' => ['targeting' => ['interests' => [['id' => '1', 'type' => 'MADE_UP']]]]]));
        $this->assertArrayHasKey('adgroup.targeting.interests.0.type', $badType);
    }

    public function test_lookups_map_live_response_shapes(): void
    {
        $this->route('POST tool/targeting/search/', [
            'parent_tags' => [['name' => 'United Arab Emirates', 'geo' => ['geo_id' => '290557', 'geo_type' => 'COUNTRY', 'parent_id' => '0', 'region_code' => 'AE']]],
            'targeting_tag_list' => [['name' => 'Dubai', 'keyword' => 'dubai', 'status_info' => ['status' => 'ENABLED', 'reason' => null], 'geo' => ['geo_id' => '292224', 'geo_type' => 'PROVINCE', 'parent_id' => '290557', 'region_code' => 'AE']]],
        ]);
        $this->assertSame([['id' => '292224', 'name' => 'Dubai', 'type' => 'PROVINCE', 'region_code' => 'AE', 'parent' => 'United Arab Emirates', 'enabled' => true, 'reason' => null]], $this->builder()->locations('dubai', 'TRAFFIC'));
        $search = collect($this->calls)->last()[2];
        $this->assertSame(['TRAFFIC', 'WEBSITE', 'FUZZY_SEARCH', ['dubai']], [$search['objective_type'], $search['promotion_type'], $search['search_type'], $search['keywords']]);

        $this->route('GET targeting/search/', [
            'general_interest' => ['list_result' => null, 'search_result' => ['fitness' => [['id' => '23123', 'name' => 'Sports & Fitness', 'sub_targeting_type' => 'GENERAL_INTEREST']]]],
            'hashtag_interaction' => ['list_result' => null, 'search_result' => ['fitness' => [['id' => '1247325189046', 'name' => '#fitnessgoals', 'sub_targeting_type' => 'HASHTAG_INTERACTION']]]],
        ]);
        $interests = $this->builder()->interests('fitness', ['GENERAL_INTEREST', 'HASHTAG_INTERACTION']);
        $this->assertSame([['id' => '23123', 'name' => 'Sports & Fitness', 'type' => 'GENERAL_INTEREST', 'key' => 'GENERAL_INTEREST:23123'], ['id' => '1247325189046', 'name' => '#fitnessgoals', 'type' => 'HASHTAG_INTERACTION', 'key' => 'HASHTAG_INTERACTION:1247325189046']], $interests);

        $this->route('GET tool/language/', ['languages' => [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']]]);
        $this->assertSame(['Arabic', 'English'], array_column($this->builder()->languages(), 'name'));

        $this->route('GET identity/video/get/', ['cursor' => '17', 'has_more' => true, 'video_list' => [['item_id' => '769', 'item_type' => 'VIDEO', 'text' => 'Hello', 'video_info' => ['poster_url' => 'https://p16/p.jpg', 'duration' => 17.04]]]]);
        $posts = $this->builder()->sparkPosts('idt-1', 'TT_USER');
        $this->assertSame([['id' => '769', 'text' => 'Hello', 'poster' => 'https://p16/p.jpg', 'duration' => 17.04]], $posts['posts']);
        $this->assertTrue($posts['has_more']);

        $this->assertSame(['Buyers', 'Lookalike 5%'], array_column($this->builder()->audiences(), 'name'));
        $this->assertSame([['code' => 'SHOPPING', 'name' => 'Complete payment'], ['code' => 'ON_WEB_CART', 'name' => 'Add to cart']], $this->builder()->pixels()[0]['events']);
    }

    public function test_expired_token_and_rate_limit_give_readable_errors(): void
    {
        $this->route('GET advertiser/info/', ['code' => 40105, 'message' => 'Access token is invalid or expired.', 'data' => []]);
        $response = app(TiktokAdBuilderController::class)->bootstrap();

        $this->assertSame(422, $response->status());
        $this->assertTrue($response->getData(true)['reconnect']);
        $this->assertStringContainsString('Reconnect your TikTok ad account', $response->getData(true)['message']);

        $this->route('GET pixel/list/', ['code' => 40100, 'message' => 'Too many requests.', 'data' => []]);
        $pixels = app(TiktokAdBuilderController::class)->pixels();
        $this->assertSame(__('tiktok_builder.server.rate_limited'), $pixels->getData(true)['message']);
        // Rate limits are retried once before giving up.
        $this->assertCount(2, array_filter($this->calls, fn ($c) => $c[1] === 'pixel/list/'));
    }

    public function test_lookup_validation_rejects_short_queries(): void
    {
        $this->expectException(ValidationException::class);
        app(TiktokAdBuilderController::class)->locations(Request::create('/', 'GET', ['q' => 'a']));
    }
}
