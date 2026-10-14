<?php

namespace Tests\Feature\Ads;

use App\Http\Controllers\Admin\FacebookAdBuilderController;
use App\Http\Requests\Admin\FacebookAdBuilderRequest;
use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
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
        // The seller's Instagram identity (instagram_user_id 1784 in input()).
        $this->route('GET act_111/instagram_accounts', ['data' => [['id' => '1784', 'username' => 'social.eaz', 'profile_picture_url' => 'https://cdn/ig.jpg']]]);

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
            foreach (['user_id', 'social_account_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->unsignedBigInteger('ad_campaign_id'); // NOT NULL, as on MySQL
            foreach (['url', 'download_link', 'thumbnail_url'] as $c) {
                $t->text($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('ad_creative_media', function ($t) {
            $t->id();
            $t->unsignedBigInteger('ad_creative_id');
            $t->unsignedBigInteger('ad_media_id');
            $t->integer('sort_order')->default(0);
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

    public function test_uploaded_media_is_saved_with_its_campaign_and_linked_to_the_creative(): void
    {
        $result = $this->builder()->submit($this->input(), 'key-media-row-00001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $media = AdMedia::sole();
        $this->assertSame(['HASH1', AdCampaign::sole()->id, 'IMAGE'], [$media->ad_media_id, (int) $media->ad_campaign_id, $media->type]);
        $this->assertSame([[AdCreative::sole()->id, $media->id]], DB::table('ad_creative_media')->get()->map(fn ($r) => [(int) $r->ad_creative_id, (int) $r->ad_media_id])->all());
    }

    public function test_a_local_save_failure_never_recreates_the_meta_object_on_retry(): void
    {
        // Meta creates the creative, then saving our copy fails (like the
        // staging ad_media error) - the retry must reuse Meta's creative.
        $fail = true;
        AdCreative::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('SQLSTATE[HY000]: General error: 1364 Field doesn\'t have a default value');
            }
        });

        $first = $this->builder()->submit($this->input(), 'key-local-fail-0001');
        $this->assertFalse($first['success']);
        $this->assertSame('creative', $first['step']);
        $this->assertStringNotContainsString('SQLSTATE', $first['message']);
        $this->assertStringContainsString('reference #', $first['message']);

        $fail = false;
        $retry = $this->builder()->submit($this->input(), 'key-local-fail-0001');

        $this->assertTrue($retry['success'], $retry['message'] ?? '');
        $this->assertCount(1, $this->posts('act_111/campaigns'));
        $this->assertCount(1, $this->posts('act_111/adsets'));
        $this->assertCount(1, $this->posts('act_111/adcreatives'));
        $this->assertCount(1, $this->posts('act_111/ads'));
        $this->assertSame(1, AdCreative::count());
    }

    public function test_every_graph_call_carries_the_ads_app_secret_proof(): void
    {
        // Image uploads post base64 bytes - too large for the global proof
        // middleware to read - so the client must add the proof itself.
        Settings::set('ads.facebook.client_secret', 'ads-secret');
        Settings::set('posts.facebook.client_secret', 'posts-secret');
        Settings::forgetRequestCache();

        $result = $this->builder()->submit($this->input(), 'key-proof-00000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $graph = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn ($r) => str_starts_with($r->url(), self::G));
        $this->assertNotEmpty($graph->filter(fn ($r) => str_contains($r->url(), 'adimages'))->all());
        foreach ($graph as $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame(hash_hmac('sha256', 'ads-token', 'ads-secret'), $query['appsecret_proof'] ?? null, $request->method() . ' ' . $request->url());
        }
    }

    public function test_awareness_ads_use_the_website_or_fall_back_to_the_page(): void
    {
        $awareness = ['goal' => 'awareness', 'destination' => 'NONE', 'adset' => ['optimization_goal' => 'REACH', 'page_id' => '500']];

        $this->builder()->submit($this->input($awareness + ['creative' => ['cta' => 'LEARN_MORE', 'link' => 'https://socialeaz.com/about']]), 'key-aware-link-0001');
        $linkData = $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['link_data'];
        $this->assertSame(['https://socialeaz.com/about', ['type' => 'LEARN_MORE', 'value' => ['link' => 'https://socialeaz.com/about']]], [$linkData['link'], $linkData['call_to_action']]);

        $result = $this->builder()->submit($this->input($awareness + ['creative' => ['cta' => 'LEARN_MORE', 'link' => null]]), 'key-aware-nolink-01');
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $linkData = $this->posts('act_111/adcreatives')[1][2]['object_story_spec']['link_data'];
        $this->assertSame(['https://www.facebook.com/500', ['type' => 'LEARN_MORE', 'value' => ['link' => 'https://www.facebook.com/500']]], [$linkData['link'], $linkData['call_to_action']]);
    }

    public function test_website_goals_still_require_the_website(): void
    {
        $result = $this->builder()->submit($this->input(['creative' => ['link' => null]]), 'key-traffic-nolink1');

        $this->assertFalse($result['success']);
        $this->assertSame('creative', $result['step']);
        $this->assertStringContainsString('website address', $result['message']);
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
        $this->assertSame(15, $b->bootstrap()['vat_rate']);
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

    /** Validation errors for a full builder request (empty when it passes). */
    private function requestErrors(array $data): array
    {
        $request = FacebookAdBuilderRequest::create('/', 'POST', $data);
        $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $this->user);

        try {
            $request->validateResolved();

            return [];
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->all();
        }
    }

    public function test_a_single_image_ignores_empty_carousel_cards(): void
    {
        // The wizard used to send cards: [] with a single image -> "a carousel needs at least 2 cards".
        $data = ['idempotency_key' => 'key-image-cards-001'] + $this->input(['creative' => ['cards' => []]]);
        $this->assertSame([], $this->requestErrors($data));

        $video = $this->input(['creative' => ['format' => 'VIDEO', 'cards' => [['asset_id' => 3]]]]);
        $this->assertSame([], $this->requestErrors(['idempotency_key' => 'key-video-cards-001'] + $video));
    }

    public function test_a_carousel_still_needs_two_cards(): void
    {
        $data = ['idempotency_key' => 'key-carousel-1card'] + $this->input(['creative' => ['format' => 'CAROUSEL', 'media' => [], 'cards' => [['asset_id' => 1]]]]);

        $this->assertContains('Creative: a carousel needs at least 2 cards.', $this->requestErrors($data));
    }

    // ---- Instagram ---------------------------------------------------------

    private function instagram(array $overrides = []): array
    {
        return $this->input(array_replace_recursive(['channel' => 'instagram', 'adset' => ['placements' => ['instagram_positions' => ['explore_home', 'reels']]]], $overrides));
    }

    public function test_instagram_ads_deliver_on_instagram_only_and_save_as_instagram(): void
    {
        $result = $this->builder()->submit($this->instagram(), 'key-instagram-0001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame(['instagram'], $t['publisher_platforms']);
        // Explore home is delivered with Feed - added automatically (never the removed 'explore').
        $this->assertEqualsCanonicalizing(['explore_home', 'reels', 'stream'], $t['instagram_positions']);
        $this->assertArrayNotHasKey('facebook_positions', $t);
        $this->assertSame('1784', $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['instagram_user_id']);
        $this->assertSame(['instagram', 'instagram', 'instagram', 'instagram'], [AdCampaign::sole()->platform, AdAdGroup::sole()->platform, AdCreative::sole()->platform, Ad::sole()->platform]);
    }

    public function test_the_reported_instagram_payload_never_sends_the_removed_explore_placement(): void
    {
        // Meta: "IG Explore Placement Is Deprecated ... please remove it from your targeting."
        $this->route('POST act_111/adsets', fn ($p) => in_array('explore', $p['targeting']['instagram_positions'] ?? [], true)
            ? Http::response(['error' => ['code' => 100, 'error_user_title' => 'IG Explore Placement Is Deprecated', 'error_user_msg' => 'IG Explore placement is deprecated for this API version and cannot be selected, please remove it from your targeting.']], 400)
            : ['id' => '9002']);

        // The payload from the report: awareness, CBO, every position incl. 'explore', a job title, 3 languages.
        $input = $this->instagram([
            'goal' => 'awareness', 'destination' => 'NONE',
            'campaign' => ['campaign_budget' => true, 'budget_type' => 'daily', 'budget' => 20],
            'adset' => ['optimization_goal' => 'REACH', 'page_id' => '500', 'budget' => null, 'targeting' => [
                'locations' => [['key' => 'SA', 'type' => 'country', 'country_code' => 'SA']],
                'interests' => [['id' => '662527803859073', 'name' => 'Sales and Marketing Manager', 'type' => 'work_positions']],
                'locales' => [['key' => 1001, 'name' => 'English (All)'], ['key' => 28, 'name' => 'Arabic'], ['key' => 90, 'name' => 'Urdu']],
                'advantage_audience' => true,
            ]],
            'creative' => ['cta' => 'LEARN_MORE', 'link' => 'https://socialeaz.com'],
        ]);
        $input['adset']['placements'] = ['mode' => 'manual', 'publisher_platforms' => ['instagram'], 'instagram_positions' => ['reels', 'story', 'stream', 'explore', 'explore_home', 'ig_search'], 'device_platforms' => ['mobile', 'desktop']];
        $input['adset']['targeting']['locations'] = [['key' => 'SA', 'type' => 'country', 'country_code' => 'SA']];
        $input['adset']['targeting']['interests'] = [['id' => '662527803859073', 'name' => 'Sales and Marketing Manager', 'type' => 'work_positions']];

        $this->assertSame([], $this->requestErrors(['idempotency_key' => 'key-ig-report-val01'] + $input));
        $result = $this->builder()->submit($input, 'key-ig-report-0001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertEqualsCanonicalizing(['reels', 'story', 'stream', 'explore_home', 'ig_search'], $t['instagram_positions']);
        $this->assertSame([['work_positions' => [['id' => '662527803859073', 'name' => 'Sales and Marketing Manager']]]], $t['flexible_spec']);
        $this->assertSame([1001, 28, 90], $t['locales']);

        // Facebook page, manual placements with an old 'explore' pick: dropped too.
        $this->builder()->submit($this->input(['adset' => ['placements' => ['mode' => 'manual', 'publisher_platforms' => ['instagram'], 'instagram_positions' => ['explore', 'reels']]]]), 'key-fb-explore-0001');
        $this->assertSame(['reels'], $this->posts('act_111/adsets')[1][2]['targeting']['instagram_positions']);
    }

    public function test_instagram_carousel_sends_its_placements_and_cards(): void
    {
        $input = $this->instagram(['creative' => ['format' => 'CAROUSEL', 'media' => [], 'cards' => [
            ['asset_id' => 1, 'headline' => 'Card one', 'link' => 'https://socialeaz.com/one'],
            ['asset_id' => 3, 'headline' => 'Card two'],
        ]]]);
        $input['adset']['placements'] = ['mode' => 'manual', 'publisher_platforms' => ['instagram'], 'instagram_positions' => ['stream', 'story', 'explore_home'], 'device_platforms' => ['mobile']];

        $this->assertSame([], $this->requestErrors(['idempotency_key' => 'key-ig-carousel-v01'] + $input));
        $result = $this->builder()->submit($input, 'key-ig-carousel-0001');
        $this->assertTrue($result['success'], $result['message'] ?? '');

        // Ad set: Instagram only, exactly the chosen positions, mobile.
        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame(['instagram'], $t['publisher_platforms']);
        $this->assertEqualsCanonicalizing(['stream', 'story', 'explore_home'], $t['instagram_positions']);
        $this->assertSame(['mobile'], $t['device_platforms']);

        // Creative: carousel cards with their own images/links, Instagram identity.
        $story = $this->posts('act_111/adcreatives')[0][2]['object_story_spec'];
        $this->assertSame(['500', '1784'], [$story['page_id'], $story['instagram_user_id']]);
        $cards = $story['link_data']['child_attachments'];
        $this->assertCount(2, $cards);
        $this->assertSame(['https://socialeaz.com/one', 'https://socialeaz.com/sale'], array_column($cards, 'link')); // card 2 uses the ad's link
        $this->assertSame(['HASH1', 'HASH1'], array_column($cards, 'image_hash'));
        $this->assertSame(['Card one', 'Card two'], array_column($cards, 'name'));
        // Each card's button follows the card: card one -> its own link, card two -> the ad's link.
        $this->assertSame(['https://socialeaz.com/one', 'https://socialeaz.com/sale'], array_map(fn ($c) => $c['call_to_action']['value']['link'], $cards));
        $this->assertCount(2, $this->posts('act_111/adimages'));
    }

    public function test_instagram_all_positions_when_none_picked(): void
    {
        $input = $this->instagram();
        $input['adset']['placements']['instagram_positions'] = []; // array_replace_recursive can't empty a list
        $this->builder()->submit($input, 'key-instagram-0002');

        $t = $this->posts('act_111/adsets')[0][2]['targeting'];
        $this->assertSame(['instagram'], $t['publisher_platforms']);
        $this->assertArrayNotHasKey('instagram_positions', $t);
    }

    public function test_instagram_needs_an_owned_instagram_account_and_no_messenger(): void
    {
        $b = $this->builder();

        $none = $b->submit($this->instagram(['creative' => ['instagram_user_id' => null]]), 'key-instagram-0003');
        $this->assertSame('Choose the Instagram account the ad is posted from.', $none['message']);

        $foreign = $b->submit($this->instagram(['creative' => ['instagram_user_id' => '9999']]), 'key-instagram-0004');
        $this->assertSame('That Instagram account is not connected to this ad account or your Pages.', $foreign['message']);

        $messenger = $b->submit($this->instagram(['goal' => 'messages', 'destination' => 'MESSENGER', 'adset' => ['optimization_goal' => 'CONVERSATIONS', 'page_id' => '500'], 'creative' => ['cta' => 'MESSAGE_PAGE', 'link' => null]]), 'key-instagram-0005');
        $this->assertStringContainsString('Instagram Direct', $messenger['message']);

        $this->assertSame([], $this->posts('act_111/campaigns'));
        $this->assertArrayNotHasKey('MESSENGER', $b->forChannel('instagram')->bootstrap()['goals']['messages']['destinations']);
        $this->assertArrayHasKey('MESSENGER', $b->forChannel('facebook')->bootstrap()['goals']['messages']['destinations']);
    }

    public function test_connected_instagram_accounts_are_offered_when_meta_returns_none(): void
    {
        // Ad-account edge empty and the Page lookup refused (ads token without Page access).
        $this->route('GET act_111/instagram_accounts', ['data' => []]);
        $otherSeller = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('x')]);
        DB::table('social_accounts')->insert([
            ['user_id' => $this->user->id, 'platform' => 'instagram', 'platform_account_id' => '17841440159861121', 'name' => 'Socialeaz', 'username' => 'social.eaz', 'account_type' => 'business_account', 'access_token' => 't', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $otherSeller->id, 'platform' => 'instagram', 'platform_account_id' => '17841499999999999', 'name' => 'Other seller', 'username' => 'other', 'account_type' => 'business_account', 'access_token' => 't', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $b = $this->builder();
        $this->assertSame(['17841440159861121'], collect($b->instagramAccounts())->pluck('id')->all());

        $input = $this->instagram();
        $input['creative']['instagram_user_id'] = '17841440159861121';
        $result = $b->submit($input, 'key-instagram-fallback1');
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame('17841440159861121', $this->posts('act_111/adcreatives')[0][2]['object_story_spec']['instagram_user_id']);

        $other = $this->instagram();
        $other['creative']['instagram_user_id'] = '17841499999999999';
        $this->assertSame('That Instagram account is not connected to this ad account or your Pages.', $b->submit($other, 'key-instagram-fallback2')['message']);
    }

    public function test_a_refused_instagram_accounts_field_falls_back_instead_of_failing(): void
    {
        // Meta: "(#100) Tried accessing nonexisting field (profile_pic)" - the list still loads.
        $this->route('GET act_111/instagram_accounts', fn ($p) => str_contains($p['fields'], 'profile_picture_url')
            ? Http::response(['error' => ['message' => '(#100) Tried accessing nonexisting field (profile_picture_url)', 'code' => 100]], 400)
            : ['data' => [['id' => '1784', 'username' => 'social.eaz']]]);

        $this->assertSame([['id' => '1784', 'username' => 'social.eaz', 'picture' => null]], $this->builder()->instagramAccounts());

        $this->route('GET act_111/instagram_accounts', Http::response(['error' => ['message' => '(#100) Tried accessing nonexisting field (profile_pic)', 'code' => 100]], 400));
        $this->assertSame([], $this->builder()->instagramAccounts()); // no throw - connected accounts would fill it
        $this->assertNotContains('profile_pic', collect($this->calls)->pluck('2.fields')->filter()->all());
    }

    public function test_an_all_zero_estimate_shows_no_estimate(): void
    {
        $this->route('GET act_111/delivery_estimate', ['data' => [['estimate_mau_lower_bound' => 0, 'estimate_mau_upper_bound' => 0, 'estimate_ready' => false]]]);

        $this->assertNull($this->builder()->estimate($this->input()));
    }

    public function test_an_instagram_identity_from_elsewhere_is_refused_on_facebook_too(): void
    {
        $result = $this->builder()->submit($this->input(['creative' => ['instagram_user_id' => '9999']]), 'key-fb-foreign-ig1');

        $this->assertSame('That Instagram account is not connected to this ad account or your Pages.', $result['message']);
    }

    public function test_an_existing_facebook_only_ad_set_is_refused_for_instagram(): void
    {
        $this->route('GET 4001', ['id' => '4001', 'name' => 'Always on', 'account_id' => '111', 'objective' => 'OUTCOME_TRAFFIC', 'effective_status' => 'ACTIVE']);
        $this->route('GET 4002', ['id' => '4002', 'name' => 'FB only', 'account_id' => '111', 'campaign_id' => '4001', 'targeting' => ['publisher_platforms' => ['facebook']]]);

        $result = $this->builder()->submit($this->instagram(['campaign' => ['mode' => 'existing', 'id' => '4001'], 'adset' => ['mode' => 'existing', 'id' => '4002']]), 'key-instagram-0006');

        $this->assertFalse($result['success']);
        $this->assertSame('adset', $result['step']);
        $this->assertStringContainsString("doesn't deliver on Instagram", $result['message']);
    }

    public function test_request_requires_the_instagram_account_on_the_instagram_page(): void
    {
        $errors = $this->requestErrors(['idempotency_key' => 'key-instagram-val01'] + $this->instagram(['creative' => ['instagram_user_id' => null]]));

        $this->assertContains('Creative: choose the Instagram account the ad is posted from.', $errors);
        $this->assertSame([], $this->requestErrors(['idempotency_key' => 'key-instagram-val02'] + $this->instagram()));
        $this->assertNotSame([], $this->requestErrors(['idempotency_key' => 'key-instagram-val03'] + $this->instagram(['adset' => ['placements' => ['instagram_positions' => ['profile_magic']]]])));
    }

    // ---- B2B work & education, more location types -------------------------

    public function test_work_and_education_search_and_targeting(): void
    {
        $this->route('GET search', fn ($p) => match ($p['type']) {
            'adworkposition' => ['data' => [['id' => '105', 'name' => 'Marketing Manager', 'audience_size' => 1000]]],
            'adworkemployer' => ['data' => [['id' => '206', 'name' => 'Aramco']]],
            default => ['data' => []],
        });
        $b = $this->builder();

        $this->assertSame([['id' => '105', 'name' => 'Marketing Manager', 'type' => 'work_positions', 'description' => null, 'size_min' => 1000, 'size_max' => null]], $b->workSearch('Marketing', 'adworkposition'));
        $this->assertSame('work_employers', $b->workSearch('Aramco', 'adworkemployer')[0]['type']);
        try {
            $b->workSearch('x', 'adinterest');
            $this->fail('accepted an unsupported type');
        } catch (\InvalidArgumentException) {
        }

        $b->submit($this->input(['adset' => ['targeting' => ['interests' => [['id' => '105', 'name' => 'Marketing Manager', 'type' => 'work_positions'], ['id' => '6003107902433', 'name' => 'Online shopping', 'type' => 'interests']]]]]), 'key-work-target-01');
        $this->assertSame([['work_positions' => [['id' => '105', 'name' => 'Marketing Manager']], 'interests' => [['id' => '6003107902433', 'name' => 'Online shopping']]]], $this->posts('act_111/adsets')[0][2]['targeting']['flexible_spec']);
    }

    public function test_zip_dma_and_neighborhood_locations(): void
    {
        $locations = [['key' => 'US:10001', 'type' => 'zip'], ['key' => '501', 'type' => 'geo_market'], ['key' => '2000', 'type' => 'neighborhood']];
        $this->builder()->submit($this->input(['adset' => ['targeting' => ['locations' => $locations]]]), 'key-geo-types-0001');

        $geo = $this->posts('act_111/adsets')[0][2]['targeting']['geo_locations'];
        $this->assertSame([['key' => 'US:10001']], $geo['zips']);
        $this->assertSame([['key' => '501']], $geo['geo_markets']);
        $this->assertSame([['key' => '2000']], $geo['neighborhoods']);

        $special = $this->builder()->submit($this->input(['campaign' => ['special_ad_category' => 'HOUSING'], 'adset' => ['targeting' => ['locations' => $locations]]]), 'key-geo-types-0002');
        $this->assertStringContainsString('ZIP', $special['message']);
    }

    public function test_builder_view_compiles_to_valid_php(): void
    {
        // A one-line @php(...) next to a @php ... @endphp block once compiled to broken PHP.
        $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/admin/ads/facebook/campaigns/builder.blade.php')));

        token_get_all($compiled, TOKEN_PARSE); // throws ParseError on invalid PHP
        $this->assertStringContainsString('channel="<?php echo e($channel); ?>"', $compiled);
    }

    public function test_instagram_create_page_opens_the_builder_in_instagram_mode(): void
    {
        Schema::create('countries', fn ($t) => [$t->id(), $t->string('name')->nullable(), $t->string('code')->nullable()]);
        $controller = app(\App\Http\Controllers\Admin\AdCampaignController::class);

        $view = $controller->create('instagram');
        $this->assertSame('admin.ads.facebook.campaigns.builder', $view->name());
        $this->assertSame($this->account->id, $view->getData()['account']->id); // the Meta ad account

        request()->merge(['legacy' => 1]);
        $this->assertSame('admin.ads.facebook.campaigns.create', $controller->create('instagram')->name());
    }

    // ---- Arabic -----------------------------------------------------------

    public function test_arabic_locale_translates_goals_messages_and_asks_meta_in_arabic(): void
    {
        app()->setLocale('ar');
        $this->route('GET search', ['data' => [['key' => 28, 'name' => 'Arabic']]]);
        $b = $this->builder();

        $boot = $b->bootstrap();
        $this->assertSame('زيارات الموقع', $boot['goals']['traffic']['label']);
        $this->assertSame('الموقع الإلكتروني', $boot['goals']['traffic']['destinations']['WEBSITE']['label']);
        $this->assertSame('الوصول اليومي الفريد', $boot['goals']['traffic']['destinations']['WEBSITE']['optimization_goals']['REACH']);
        $this->assertSame('الوصول (العرض لأكبر عدد من الأشخاص)', $boot['goals']['awareness']['destinations']['NONE']['optimization_goals']['REACH']);
        $this->assertSame('شراء', $boot['conversion_events']['PURCHASE']);
        $this->assertSame(['WEBSITE'], array_keys($boot['goals']['traffic']['destinations'])); // codes unchanged

        // Meta reads in Arabic; the language list stays English so quick-add can match it.
        $this->assertSame('ar_AR', collect($this->calls)->firstWhere(1, 'act_111')[2]['locale'] ?? null);
        $b->locales('Arab');
        $this->assertSame('en_US', collect($this->calls)->last(fn ($c) => $c[1] === 'search')[2]['locale']);

        // Server-side failures come back in Arabic.
        $result = $b->submit($this->input(['creative' => ['link' => null]]), 'key-arabic-msg-0001');
        $this->assertSame('أضف عنوان الموقع (يبدأ بـ https://) الذي ينتقل إليه الأشخاص.', $result['message']);
        $this->assertNotContains('locale', array_keys($this->posts('act_111/campaigns')[0][2])); // creates don't carry it

        $errors = $this->requestErrors(['idempotency_key' => 'key-arabic-val-0001'] + $this->input(['campaign' => ['name' => null]]));
        $this->assertContains('الحملة: أدخل اسماً للحملة الجديدة.', $errors);
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
