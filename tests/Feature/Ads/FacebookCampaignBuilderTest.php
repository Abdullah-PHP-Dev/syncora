<?php

namespace Tests\Feature\Ads;

use App\Http\Controllers\Admin\FacebookAdBuilderController;
use App\Http\Requests\Admin\FacebookAdBuilderRequest;
use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\FacebookCampaignBuilder;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * Facebook campaign builder with Meta's Graph API faked: new / existing /
 * mixed objects, ownership checks, objective and Special Ad Category
 * rules, budgets in the currency's minor unit, retries after a partial
 * failure, duplicate submissions and the lookups.
 */
class FacebookCampaignBuilderTest extends TestCase
{
    use CreatesConnectionTables;

    private const G = 'https://graph.facebook.com/v22.0/';

    private User $user;

    private SocialAccount $account;

    /** Responses by "METHOD path" (path without the version prefix). */
    private array $routes = [];

    /** Every Graph call made: [method, path, params]. */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        (require database_path('migrations/2026_10_14_120000_create_ad_builder_runs_table.php'))->up();
        $this->createAdTables();
        Settings::set('ads.facebook.base_url', 'https://graph.facebook.com/v22.0/act_{accountId}');

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => '111', 'name' => 'Socialeaz Ads',
            'account_type' => 'ad_account', 'access_token' => 'ads-token', 'expires_at' => now()->addDays(30),
            'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
        DB::table('platform_pages')->insert(['platform' => 'facebook', 'user_id' => $this->user->id, 'page_id' => '500', 'name' => 'Socialeaz Page', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('media_assets')->insert([
            ['id' => 1, 'user_id' => $this->user->id, 'type' => 'image', 'path' => 'a.jpg', 'url' => 'https://cdn.socialeaz.com/a.jpg', 'original_name' => 'banner.jpg', 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'thumbnail_url' => null],
            ['id' => 2, 'user_id' => $this->user->id, 'type' => 'video', 'path' => 'v.mp4', 'url' => 'https://cdn.socialeaz.com/v.mp4', 'original_name' => 'promo.mp4', 'extension' => 'mp4', 'mime_type' => 'video/mp4', 'size' => 10, 'thumbnail_url' => 'https://cdn.socialeaz.com/v.jpg'],
            ['id' => 3, 'user_id' => $this->user->id, 'type' => 'image', 'path' => 'b.jpg', 'url' => 'https://cdn.socialeaz.com/b.jpg', 'original_name' => 'card2.jpg', 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'thumbnail_url' => null],
        ]);

        // Defaults every scenario shares.
        $this->route('GET act_111', ['id' => 'act_111', 'account_id' => '111', 'name' => 'Socialeaz Ads', 'currency' => 'SAR', 'timezone_name' => 'Asia/Riyadh', 'account_status' => 1, 'min_daily_budget' => 400]);
        $this->route('POST act_111/campaigns', ['id' => '9001']);
        $this->route('POST act_111/adsets', ['id' => '9002']);
        $this->route('POST act_111/adimages', ['images' => ['bytes' => ['hash' => 'HASH1', 'url' => 'https://scontent/hash1.jpg']]]);
        $this->route('POST act_111/advideos', ['id' => 'VID1']);
        $this->route('POST act_111/adcreatives', ['id' => '9003']);
        $this->route('POST act_111/ads', ['id' => '9004']);
        $this->route('GET act_111/adspixels', ['data' => [['id' => '777', 'name' => 'Main pixel']]]);
        $this->route('GET act_111/customaudiences', ['data' => [['id' => '880', 'name' => 'Customers', 'subtype' => 'CUSTOM'], ['id' => '881', 'name' => 'Lookalike 1%', 'subtype' => 'LOOKALIKE']]]);
        $this->route('GET act_111/saved_audiences', ['data' => []]);

        Http::fake(function (HttpRequest $request) {
            if (str_starts_with($request->url(), 'https://cdn.socialeaz.com/')) {
                return Http::response('image-bytes');
            }

            $path = strtok(substr($request->url(), strlen(self::G)), '?');
            $params = $request->method() === 'GET' ? $request->data() : $this->formData($request);
            $this->calls[] = [$request->method(), $path, $params];

            $response = $this->routes[$request->method() . ' ' . $path] ?? null;
            if ($response === null) {
                return Http::response(['error' => ['message' => "Unfaked {$request->method()} {$path}", 'code' => 100]], 400);
            }
            if ($response instanceof \Closure) {
                $response = $response($params);
            }

            return $response instanceof \GuzzleHttp\Promise\PromiseInterface ? $response : Http::response($response);
        });
    }

    private function formData(HttpRequest $request): array
    {
        parse_str($request->body(), $data);

        return array_map(fn ($v) => is_string($v) && in_array($v[0] ?? '', ['{', '['], true) ? json_decode($v, true) : $v, $data);
    }

    private function route(string $key, $response): void
    {
        $this->routes[$key] = $response;
    }

    private function createAdTables(): void
    {
        Schema::create('ad_campaigns', function ($t) {
            $t->id();
            foreach (['social_account_id', 'user_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['name', 'platform', 'status', 'ad_campaign_id', 'objective', 'special_ad_category', 'budget_mode', 'bidding_strategy'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('budget', 12, 2)->nullable();
            $t->decimal('daily_budget', 12, 2)->nullable();
            $t->timestamp('start_time')->nullable();
            $t->timestamp('end_time')->nullable();
            $t->timestamps();
        });
        Schema::create('ad_adgroups', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_adgroup_id', 'platform', 'name', 'optimization_goal', 'billing_event', 'destination_type', 'budget_mode', 'bid_strategy', 'bid_price', 'status'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('budget', 12, 2)->nullable();
            $t->timestamp('schedule_start_time')->nullable();
            $t->timestamp('schedule_end_time')->nullable();
            foreach (['promotion_target_type', 'targeting_criteria', 'publisher_platforms'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_creatives', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id', 'ad_adgroup_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_creative_id', 'platform', 'name', 'type', 'page_id', 'headline', 'url', 'call_to_action', 'lead_generation_form_id'] as $c) {
                $t->string($c)->nullable();
            }
            $t->text('message')->nullable();
            $t->timestamps();
        });
        Schema::create('ads', function ($t) {
            $t->id();
            foreach (['user_id', 'social_account_id', 'ad_campaign_id', 'ad_adgroup_id', 'ad_creative_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['ad_id', 'platform', 'name', 'status', 'call_to_action'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_media', function ($t) {
            $t->id();
            foreach (['ad_media_id', 'platform', 'name', 'file_name', 'type', 'status', 'signature', 'file_id', 'upload_by_type', 'ad_format'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['user_id', 'social_account_id', 'ad_campaign_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['url', 'download_link', 'thumbnail_url'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('platform_pages', function ($t) {
            $t->id();
            foreach (['platform', 'page_id', 'name', 'username', 'picture'] as $c) {
                $t->string($c)->nullable();
            }
            $t->unsignedBigInteger('user_id');
            $t->timestamps();
        });
        Schema::create('media_assets', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            foreach (['type', 'path', 'url', 'thumbnail_url', 'original_name', 'extension', 'mime_type'] as $c) {
                $t->string($c)->nullable();
            }
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function builder(): FacebookCampaignBuilder
    {
        return FacebookCampaignBuilder::forUser($this->user->id);
    }

    /** A complete "all new" website-traffic submission. */
    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'goal' => 'traffic',
            'destination' => 'WEBSITE',
            'campaign' => ['mode' => 'new', 'name' => 'Spring sale'],
            'adset' => [
                'mode' => 'new', 'name' => 'KSA 25-45', 'optimization_goal' => 'LANDING_PAGE_VIEWS',
                'budget_type' => 'daily', 'budget' => 50, 'start_time' => now()->addDay()->format('Y-m-d H:i'),
                'targeting' => [
                    'locations' => [['key' => 'SA', 'type' => 'country', 'country_code' => 'SA'], ['key' => '2420605', 'type' => 'city', 'radius' => 25]],
                    'age_min' => 25, 'age_max' => 45, 'genders' => [2],
                    'locales' => [['key' => 28, 'name' => 'Arabic']],
                    'interests' => [['id' => '6003107902433', 'name' => 'Online shopping', 'type' => 'interests'], ['id' => '6002714895372', 'name' => 'Frequent travelers', 'type' => 'behaviors']],
                    'advantage_audience' => false,
                ],
                'placements' => ['mode' => 'advantage'],
            ],
            'creative' => [
                'mode' => 'new', 'name' => 'Spring creative', 'format' => 'IMAGE', 'page_id' => '500', 'instagram_user_id' => '1784',
                'primary_text' => 'Up to 30% off this week.', 'headline' => 'Spring sale', 'description' => 'Free delivery',
                'link' => 'https://socialeaz.com/sale', 'cta' => 'SHOP_NOW', 'media' => [['asset_id' => 1]],
            ],
            'ad' => ['name' => 'Spring sale - Ad 1', 'status' => 'PAUSED'],
        ], $overrides);
    }

    private function posts(string $path): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === 'POST' && $c[1] === $path));
    }

    // ---- new -> new -> new -> ad ----------------------------------------

    public function test_all_new_objects_are_created_with_the_right_payloads(): void
    {
        $result = $this->builder()->submit($this->input(), 'key-all-new-0000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        [, , $campaign] = $this->posts('act_111/campaigns')[0];
        $this->assertSame(['Spring sale', 'OUTCOME_TRAFFIC', 'PAUSED', [], 'false'], [$campaign['name'], $campaign['objective'], $campaign['status'], $campaign['special_ad_categories'], $campaign['is_adset_budget_sharing_enabled']]);

        [, , $adSet] = $this->posts('act_111/adsets')[0];
        $this->assertSame('9001', $adSet['campaign_id']);
        $this->assertSame('5000', $adSet['daily_budget']); // SAR 50 -> 5000 halalas (was * 1000 before)
        $this->assertSame(['LANDING_PAGE_VIEWS', 'IMPRESSIONS', 'WEBSITE', 'LOWEST_COST_WITHOUT_CAP'], [$adSet['optimization_goal'], $adSet['billing_event'], $adSet['destination_type'], $adSet['bid_strategy']]);
        $t = $adSet['targeting'];
        $this->assertSame(['countries' => ['SA'], 'cities' => [['key' => '2420605', 'radius' => 25, 'distance_unit' => 'kilometer']]], $t['geo_locations']);
        $this->assertSame([25, 45, [2], [28]], [$t['age_min'], $t['age_max'], $t['genders'], $t['locales']]);
        $this->assertSame([['interests' => [['id' => '6003107902433', 'name' => 'Online shopping']], 'behaviors' => [['id' => '6002714895372', 'name' => 'Frequent travelers']]]], $t['flexible_spec']);
        $this->assertSame(['advantage_audience' => 0], $t['targeting_automation']);
        $this->assertArrayNotHasKey('publisher_platforms', $t); // Advantage+ placements

        [, , $creative] = $this->posts('act_111/adcreatives')[0];
        $story = $creative['object_story_spec'];
        $this->assertSame(['500', '1784'], [$story['page_id'], $story['instagram_user_id']]); // not instagram_actor_id
        $this->assertSame(['https://socialeaz.com/sale', 'HASH1', 'Spring sale', 'Up to 30% off this week.'], [$story['link_data']['link'], $story['link_data']['image_hash'], $story['link_data']['name'], $story['link_data']['message']]);
        $this->assertSame(['type' => 'SHOP_NOW', 'value' => ['link' => 'https://socialeaz.com/sale']], $story['link_data']['call_to_action']);

        [, , $ad] = $this->posts('act_111/ads')[0];
        $this->assertSame(['9002', ['creative_id' => '9003'], 'PAUSED'], [$ad['adset_id'], $ad['creative'], $ad['status']]);

        $this->assertSame(['9001', '9002', '9003', '9004'], [AdCampaign::sole()->ad_campaign_id, AdAdGroup::sole()->ad_adgroup_id, AdCreative::sole()->ad_creative_id, Ad::sole()->ad_id]);
        $this->assertSame('completed', AdBuilderRun::sole()->status);
    }

    public function test_publishing_active_activates_the_new_objects(): void
    {
        $this->builder()->submit($this->input(['ad' => ['status' => 'ACTIVE']]), 'key-active-00000001');

        $this->assertSame('ACTIVE', $this->posts('act_111/campaigns')[0][2]['status']);
        $this->assertSame('ACTIVE', $this->posts('act_111/adsets')[0][2]['status']);
        $this->assertSame('ACTIVE', $this->posts('act_111/ads')[0][2]['status']);
    }

    // ---- existing -> existing -> existing -> ad -------------------------

    public function test_existing_campaign_ad_set_and_creative_only_create_the_ad(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Always on', 'account_id' => '111', 'objective' => 'OUTCOME_TRAFFIC', 'effective_status' => 'ACTIVE', 'special_ad_categories' => []]);
        $this->route('GET 4002', ['id' => '4002', 'name' => 'Broad KSA', 'account_id' => '111', 'campaign_id' => '4001', 'optimization_goal' => 'LINK_CLICKS']);
        $this->route('GET 4003', ['id' => '4003', 'name' => 'Old creative', 'account_id' => '111', 'object_story_spec' => ['page_id' => '500']]);

        $result = $this->builder()->submit($this->input([
            'campaign' => ['mode' => 'existing', 'id' => '4001'],
            'adset' => ['mode' => 'existing', 'id' => '4002'],
            'creative' => ['mode' => 'existing', 'id' => '4003'],
        ]), 'key-all-existing-01');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame([], $this->posts('act_111/campaigns'));
        $this->assertSame([], $this->posts('act_111/adsets'));
        $this->assertSame([], $this->posts('act_111/adcreatives'));
        $this->assertSame(['adset_id' => '4002', 'creative' => ['creative_id' => '4003']], array_intersect_key($this->posts('act_111/ads')[0][2], ['adset_id' => 1, 'creative' => 1]));
        // Existing Meta objects get local rows so the ad shows in Socialeaz.
        $this->assertSame(['4001', '4002', '4003'], [AdCampaign::sole()->ad_campaign_id, AdAdGroup::sole()->ad_adgroup_id, AdCreative::sole()->ad_creative_id]);
        $this->assertSame(['campaign' => true, 'adset' => true, 'creative' => true, 'ad' => false], collect($result['created'])->pluck('reused', 'type')->all());
    }

    public function test_mixed_existing_campaign_new_ad_set_existing_creative(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Always on', 'account_id' => '111', 'objective' => 'OUTCOME_TRAFFIC', 'special_ad_categories' => []]);
        $this->route('GET 4003', ['id' => '4003', 'name' => 'Old creative', 'account_id' => '111']);

        $result = $this->builder()->submit($this->input([
            'campaign' => ['mode' => 'existing', 'id' => '4001'],
            'creative' => ['mode' => 'existing', 'id' => '4003'],
        ]), 'key-mixed-000000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame('4001', $this->posts('act_111/adsets')[0][2]['campaign_id']);
        $this->assertSame([], $this->posts('act_111/adcreatives'));
        $this->assertSame('4003', $this->posts('act_111/ads')[0][2]['creative']['creative_id']);
    }

    public function test_a_campaign_with_its_own_budget_keeps_the_budget_off_the_ad_set(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'CBO', 'account_id' => '111', 'objective' => 'OUTCOME_TRAFFIC', 'daily_budget' => '20000', 'special_ad_categories' => []]);

        $this->builder()->submit($this->input(['campaign' => ['mode' => 'existing', 'id' => '4001']]), 'key-cbo-existing-01');

        $adSet = $this->posts('act_111/adsets')[0][2];
        $this->assertArrayNotHasKey('daily_budget', $adSet);
        $this->assertArrayNotHasKey('bid_strategy', $adSet);
    }

    public function test_new_campaign_with_advantage_campaign_budget(): void
    {
        $this->builder()->submit($this->input(['campaign' => ['campaign_budget' => true, 'budget_type' => 'daily', 'budget' => 120]]), 'key-cbo-new-0000001');

        $campaign = $this->posts('act_111/campaigns')[0][2];
        $this->assertSame(['12000', 'LOWEST_COST_WITHOUT_CAP'], [$campaign['daily_budget'], $campaign['bid_strategy']]);
        $this->assertArrayNotHasKey('is_adset_budget_sharing_enabled', $campaign);
        $this->assertArrayNotHasKey('daily_budget', $this->posts('act_111/adsets')[0][2]);
    }

    // ---- ownership & compatibility --------------------------------------

    public function test_a_campaign_from_another_ad_account_is_refused(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Not mine', 'account_id' => '999', 'objective' => 'OUTCOME_TRAFFIC']);

        $result = $this->builder()->submit($this->input(['campaign' => ['mode' => 'existing', 'id' => '4001']]), 'key-foreign-0000001');

        $this->assertFalse($result['success']);
        $this->assertSame('campaign', $result['step']);
        $this->assertStringContainsString('belongs to a different ad account', $result['message']);
        $this->assertSame([], array_filter($this->calls, fn ($c) => $c[0] === 'POST'));
    }

    public function test_an_ad_set_from_another_campaign_is_refused(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Mine', 'account_id' => '111', 'objective' => 'OUTCOME_TRAFFIC']);
        $this->route('GET 4002', ['id' => '4002', 'name' => 'Other', 'account_id' => '111', 'campaign_id' => '5555']);

        $result = $this->builder()->submit($this->input(['campaign' => ['mode' => 'existing', 'id' => '4001'], 'adset' => ['mode' => 'existing', 'id' => '4002']]), 'key-wrong-adset-001');

        $this->assertSame(['adset', 'The selected ad set belongs to a different campaign.'], [$result['step'], $result['message']]);
    }

    public function test_an_existing_campaign_must_match_the_goal(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Awareness', 'account_id' => '111', 'objective' => 'OUTCOME_AWARENESS']);

        $result = $this->builder()->submit($this->input(['campaign' => ['mode' => 'existing', 'id' => '4001']]), 'key-objective-00001');

        $this->assertStringContainsString('different objective (OUTCOME_AWARENESS)', $result['message']);
    }

    public function test_a_page_audience_or_pixel_that_isnt_the_sellers_is_refused(): void
    {
        $page = $this->builder()->submit($this->input(['creative' => ['page_id' => '999']]), 'key-page-000000001');
        $this->assertStringContainsString('Facebook Page is not connected', $page['message']);

        $audience = $this->builder()->submit($this->input(['adset' => ['targeting' => ['custom_audiences' => ['12345']]]]), 'key-audience-00001');
        $this->assertStringContainsString('audiences does not belong', $audience['message']);

        $pixel = $this->builder()->submit($this->input(['goal' => 'sales', 'adset' => ['optimization_goal' => 'OFFSITE_CONVERSIONS', 'pixel_id' => '123']]), 'key-pixel-00000001');
        $this->assertStringContainsString('Pixel / dataset is not available', $pixel['message']);

        $this->assertSame([], array_filter($this->calls, fn ($c) => $c[0] === 'POST'));
    }

    // ---- objectives ------------------------------------------------------

    public function test_sales_needs_and_sends_the_pixel_event(): void
    {
        $this->builder()->submit($this->input(['goal' => 'sales', 'adset' => ['optimization_goal' => 'OFFSITE_CONVERSIONS', 'pixel_id' => '777', 'custom_event_type' => 'PURCHASE']]), 'key-sales-00000001');

        $adSet = $this->posts('act_111/adsets')[0][2];
        $this->assertSame(['pixel_id' => '777', 'custom_event_type' => 'PURCHASE'], $adSet['promoted_object']);
        $this->assertSame('OUTCOME_SALES', $this->posts('act_111/campaigns')[0][2]['objective']);
    }

    public function test_instant_form_leads_use_the_form_and_page(): void
    {
        $result = $this->builder()->submit($this->input([
            'goal' => 'leads', 'destination' => 'ON_AD',
            'adset' => ['optimization_goal' => 'LEAD_GENERATION', 'page_id' => '500'],
            'creative' => ['cta' => 'SIGN_UP', 'lead_form_id' => '3333', 'link' => null],
        ]), 'key-leads-00000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(['page_id' => '500'], $this->posts('act_111/adsets')[0][2]['promoted_object']);
        $linkData = $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['link_data'];
        $this->assertSame(['http://fb.me/', ['type' => 'SIGN_UP', 'value' => ['lead_gen_form_id' => '3333']]], [$linkData['link'], $linkData['call_to_action']]);
    }

    public function test_whatsapp_messages_use_the_app_destination(): void
    {
        $this->builder()->submit($this->input([
            'goal' => 'messages', 'destination' => 'WHATSAPP',
            'adset' => ['optimization_goal' => 'CONVERSATIONS', 'page_id' => '500'],
            'creative' => ['cta' => 'WHATSAPP_MESSAGE', 'link' => null],
        ]), 'key-whatsapp-00001');

        $this->assertSame(['WHATSAPP', 'OUTCOME_ENGAGEMENT'], [$this->posts('act_111/adsets')[0][2]['destination_type'], $this->posts('act_111/campaigns')[0][2]['objective']]);
        $linkData = $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['link_data'];
        $this->assertSame(['type' => 'WHATSAPP_MESSAGE', 'value' => ['app_destination' => 'WHATSAPP']], $linkData['call_to_action']);
    }

    public function test_special_ad_categories_drop_disallowed_targeting(): void
    {
        $this->builder()->submit($this->input(['campaign' => ['special_ad_category' => 'EMPLOYMENT'], 'adset' => ['targeting' => ['advantage_audience' => true]]]), 'key-special-000001');

        $this->assertSame(['EMPLOYMENT'], $this->posts('act_111/campaigns')[0][2]['special_ad_categories']);
        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame([18, 65, ['advantage_audience' => 0]], [$t['age_min'], $t['age_max'], $t['targeting_automation']]);
        $this->assertArrayNotHasKey('genders', $t);
        $this->assertArrayNotHasKey('flexible_spec', $t);
    }

    public function test_advantage_audience_keeps_age_max_at_65(): void
    {
        $this->builder()->submit($this->input(['adset' => ['targeting' => ['advantage_audience' => true, 'age_min' => 30, 'age_max' => 40]]]), 'key-advantage-00001');

        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame([25, 65, ['advantage_audience' => 1]], [$t['age_min'], $t['age_max'], $t['targeting_automation']]);
    }

    public function test_manual_placements(): void
    {
        $this->builder()->submit($this->input(['adset' => ['placements' => ['mode' => 'manual', 'publisher_platforms' => ['facebook', 'instagram', 'tiktok'], 'instagram_positions' => ['stream', 'story']]]]), 'key-placements-0001');

        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame([['facebook', 'instagram'], ['stream', 'story']], [$t['publisher_platforms'], $t['instagram_positions']]);
    }

    public function test_video_and_carousel_creatives(): void
    {
        $this->builder()->submit($this->input(['creative' => ['format' => 'VIDEO', 'media' => [['asset_id' => 2]]]]), 'key-video-00000001');
        $video = $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['video_data'];
        $this->assertSame(['VID1', 'https://cdn.socialeaz.com/v.jpg'], [$video['video_id'], $video['image_url']]);
        $this->assertSame('https://cdn.socialeaz.com/v.mp4', $this->posts('act_111/advideos')[0][2]['file_url']);

        $this->builder()->submit($this->input(['creative' => ['format' => 'CAROUSEL', 'media' => [], 'cards' => [['asset_id' => 1, 'headline' => 'One'], ['asset_id' => 3, 'headline' => 'Two', 'link' => 'https://socialeaz.com/two']]]]), 'key-carousel-00001');
        $cards = $this->posts('act_111/adcreatives')[1][2]['object_story_spec']['link_data']['child_attachments'];
        $this->assertSame(['One', 'Two', 'https://socialeaz.com/two'], [$cards[0]['name'], $cards[1]['name'], $cards[1]['link']]);
    }

    public function test_budgets_use_the_currency_unit(): void
    {
        $this->route('GET act_111', ['id' => 'act_111', 'account_id' => '111', 'currency' => 'JPY']);

        $this->builder()->submit($this->input(['adset' => ['budget' => 5000]]), 'key-jpy-0000000001');

        $this->assertSame('5000', $this->posts('act_111/adsets')[0][2]['daily_budget']); // JPY has no minor unit
    }

    // ---- retries & duplicates -------------------------------------------

    public function test_a_retry_after_a_failure_reuses_what_was_created(): void
    {
        $this->route('POST act_111/adcreatives', Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_title' => 'Image too small', 'error_user_msg' => 'Use an image at least 600px wide.', 'code' => 100]], 400));

        $failed = $this->builder()->submit($this->input(), 'key-retry-00000001');

        $this->assertSame(['creative', 'Image too small: Use an image at least 600px wide.'], [$failed['step'], $failed['message']]);
        $this->assertSame(['campaign', 'adset'], collect($failed['created'])->pluck('type')->all());

        $this->route('POST act_111/adcreatives', ['id' => '9003']);
        $retried = $this->builder()->submit($this->input(), 'key-retry-00000001');

        $this->assertTrue($retried['success'], $retried['message'] ?? '');
        $this->assertCount(1, $this->posts('act_111/campaigns'));
        $this->assertCount(1, $this->posts('act_111/adsets'));
        $this->assertCount(1, $this->posts('act_111/adimages')); // image hash remembered too
        $this->assertCount(1, $this->posts('act_111/ads'));
        $this->assertSame(1, AdCampaign::count());
    }

    public function test_submitting_twice_does_not_create_twice(): void
    {
        $this->builder()->submit($this->input(), 'key-twice-00000001');
        $again = $this->builder()->submit($this->input(), 'key-twice-00000001');

        $this->assertTrue($again['replayed']);
        $this->assertCount(1, $this->posts('act_111/campaigns'));
        $this->assertCount(1, $this->posts('act_111/ads'));
    }

    public function test_a_submission_already_in_progress_is_refused(): void
    {
        $lock = Cache::lock("ad-builder:{$this->user->id}:key-locked-0000001", 60);
        $lock->get();

        $result = $this->builder()->submit($this->input(), 'key-locked-0000001');

        $this->assertSame(409, $result['status']);
        $this->assertSame([], array_filter($this->calls, fn ($c) => $c[0] === 'POST'));
        $lock->release();
    }

    public function test_expired_token_and_permission_errors_read_clearly(): void
    {
        $this->route('POST act_111/campaigns', Http::response(['error' => ['message' => 'Error validating access token', 'code' => 190]], 400));
        $this->assertStringContainsString('Reconnect your ad account', $this->builder()->submit($this->input(), 'key-token-00000001')['message']);

        $this->route('POST act_111/campaigns', Http::response(['error' => ['message' => '(#200) Requires ads_management permission', 'code' => 200]], 400));
        $this->assertStringContainsString('lacks the permission', $this->builder()->submit($this->input(), 'key-perm-000000001')['message']);
    }

    // ---- lookups ---------------------------------------------------------

    public function test_lookups(): void
    {
        $this->route('GET act_111/campaigns', ['data' => [['id' => '4001', 'name' => 'Always on', 'objective' => 'OUTCOME_ENGAGEMENT', 'effective_status' => 'ACTIVE', 'daily_budget' => '20000', 'special_ad_categories' => ['NONE']]]]);
        $this->route('GET act_111/targetingsearch', ['data' => [['id' => '6003107902433', 'name' => 'Online shopping', 'type' => 'interests', 'path' => ['Shopping'], 'audience_size_lower_bound' => 100, 'audience_size_upper_bound' => 200]]]);
        $this->route('GET search', fn ($p) => $p['type'] === 'adgeolocation'
            ? ['data' => [['key' => '2420605', 'name' => 'Riyadh', 'type' => 'city', 'country_code' => 'SA', 'country_name' => 'Saudi Arabia']]]
            : ['data' => [['key' => 28, 'name' => 'Arabic']]]);
        $this->route('GET act_111/delivery_estimate', ['data' => [['estimate_mau_lower_bound' => 120000, 'estimate_mau_upper_bound' => 140000, 'estimate_ready' => true]]]);
        $b = $this->builder();

        $campaign = $b->campaigns()[0];
        $this->assertSame([['messages', 'engagement'], true, 200.0, []], [$campaign['goals'], $campaign['campaign_budget'], $campaign['daily_budget'], $campaign['special_ad_categories']]);
        $this->assertSame('Online shopping', $b->targetingSearch('shopping')[0]['name']);
        $this->assertSame(['2420605', 'city', true], array_values(array_intersect_key($b->locations('Riy')[0], ['key' => 1, 'type' => 1, 'supports_radius' => 1])));
        $this->assertSame([['key' => 28, 'name' => 'Arabic']], $b->locales('ara'));
        $this->assertSame([120000, 140000], array_values(array_intersect_key($b->estimate($this->input()), ['audience_min' => 1, 'audience_max' => 1])));
        $this->assertSame(['custom', 'lookalike'], collect($b->audiences()['custom'])->pluck('kind')->all());
        $this->assertSame(['SAR', 100, 4.0], [$b->bootstrap()['account']['currency'], $b->bootstrap()['account']['currency_offset'], $b->bootstrap()['account']['min_daily_budget']]);
    }

    public function test_no_estimate_means_none_is_shown(): void
    {
        $this->route('GET act_111/delivery_estimate', ['data' => [['estimate_ready' => false]]]);

        $this->assertNull($this->builder()->estimate($this->input()));
    }

    // ---- request validation ---------------------------------------------

    public function test_request_validation_names_the_step(): void
    {
        $request = FacebookAdBuilderRequest::create('/', 'POST', ['idempotency_key' => 'key-validation-0001', 'goal' => 'traffic', 'destination' => 'WEBSITE', 'campaign' => ['mode' => 'new'], 'adset' => ['mode' => 'new', 'name' => 'x'], 'creative' => ['mode' => 'new', 'format' => 'IMAGE'], 'ad' => ['name' => 'Ad', 'status' => 'PAUSED']]);
        $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $this->user);

        try {
            $request->validateResolved();
            $this->fail('validated');
        } catch (ValidationException $e) {
            $messages = collect($e->errors())->flatten()->all();
            $this->assertContains('Campaign: give the new campaign a name.', $messages);
            $this->assertContains('Ad set: enter a budget.', $messages);
            $this->assertContains('Audience: add at least one location.', $messages);
            $this->assertContains('Creative: choose the Facebook Page the ad is posted from.', $messages);
            $this->assertContains('Creative: choose an image or video.', $messages);
        }
    }

    // ---- page ------------------------------------------------------------

    public function test_create_page_opens_the_builder_and_keeps_the_old_form_reachable(): void
    {
        Schema::create('countries', fn ($t) => [$t->id(), $t->string('name')->nullable(), $t->string('code')->nullable()]);
        $controller = app(\App\Http\Controllers\Admin\AdCampaignController::class);

        $this->assertSame('admin.ads.facebook.campaigns.builder', $controller->create('facebook')->name());

        request()->merge(['legacy' => 1]);
        $this->assertSame('admin.ads.facebook.campaigns.create', $controller->create('facebook')->name());
    }

    public function test_legacy_form_budgets_use_the_currency_unit_not_x1000(): void
    {
        $this->account->adDetails()->updateOrCreate([], ['currency' => 'SAR']);
        $service = app(\App\Services\AdServices\FacebookAdService::class);
        $minor = new \ReflectionMethod($service, 'toMinorUnits');

        $this->assertSame(5000, $minor->invoke($service, 50)); // SAR 50 -> 5000 halalas (was 50000)

        $this->account->adDetails()->update(['currency' => 'JPY']);
        $service = app(\App\Services\AdServices\FacebookAdService::class);
        $this->assertSame(5000, (new \ReflectionMethod($service, 'toMinorUnits'))->invoke($service, 5000));
    }
}
