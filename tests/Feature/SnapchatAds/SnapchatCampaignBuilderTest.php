<?php

namespace Tests\Feature\SnapchatAds;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Services\AdServices\SnapchatCampaignBuilder;
use App\Support\Ads\SnapchatAdsApi;
use App\Support\Ads\SnapchatApiException;
use App\Support\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SnapchatCampaignBuilder against a faked Snapchat Marketing API:
 * targeting -> spec mapping, the campaign -> ad squad -> media -> creative
 * -> ad -> activate order, idempotent replays, resuming after a failure,
 * and token refresh.
 */
class SnapchatCampaignBuilderTest extends TestCase
{
    private const BASE = 'https://adsapi.snapchat.com/v1/';

    private const PROFILE = '36b8655e-2523-410f-ab00-02061267c3b6';

    private const PIXEL = '9a0b1c2d-0000-4000-8000-000000000001';

    private SocialAccount $account;

    private MediaAsset $video;

    private bool $failCreative = false;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdCampaign::class, AdAdGroup::class, AdCreative::class, Ad::class, AdMedia::class, AdBuilderRun::class, SocialAccount::class, MediaAsset::class] as $model) {
            $instance = new $model();
            Schema::dropIfExists($instance->getTable());
            Schema::create($instance->getTable(), function (Blueprint $table) use ($instance) {
                $table->id();
                foreach (array_unique($instance->getFillable()) as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }

        (new \ReflectionProperty(Settings::class, 'requestCache'))->setValue(null, ['ads.snapchat.client_id' => 'cid', 'ads.snapchat.client_secret' => 'csecret']);

        $this->account = SocialAccount::create([
            'user_id' => 7, 'platform' => 'snapchat', 'name' => 'Acme Snap', 'platform_account_id' => 'acc1',
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addMinutes(20), 'has_ads_permission' => true,
            'metadata' => ['organization_id' => 'org1', 'currency' => 'SAR'],
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('gallery/clip.mp4', 'video-bytes');
        $this->video = MediaAsset::create(['user_id' => 7, 'type' => 'video', 'disk' => 'local', 'path' => 'gallery/clip.mp4', 'url' => 'https://cdn.test/clip.mp4', 'original_name' => 'clip.mp4', 'extension' => 'mp4', 'mime_type' => 'video/mp4', 'size' => 1024, 'width' => 1080, 'height' => 1920]);

        $this->fakeSnap();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Settings::class, 'requestCache'))->setValue(null, null);
        parent::tearDown();
    }

    private function builder(): SnapchatCampaignBuilder
    {
        return new SnapchatCampaignBuilder(new SnapchatAdsApi($this->account, self::BASE));
    }

    private function fakeSnap(): void
    {
        Http::fake(function (HttpRequest $request) {
            $url = strtok($request->url(), '?');
            $method = $request->method();

            if ($url === 'https://businessapi.snapchat.com/v1/adaccounts/acc1/sharing_policies') {
                return Http::response(['request_status' => 'SUCCESS', 'sharing_policies' => [
                    ['source' => ['resource_type' => 'pixels', 'resource_id' => self::PIXEL]],
                    ['source' => ['resource_type' => 'public_profiles', 'resource_id' => self::PROFILE]],
                ]]);
            }
            if (str_starts_with($url, 'https://businessapi.snapchat.com/v1/organizations/org1/public_profiles')) {
                return Http::response(['request_status' => 'SUCCESS', 'public_profiles' => [['sub_request_status' => 'SUCCESS', 'public_profile' => ['id' => self::PROFILE, 'display_name' => 'Acme']]]]);
            }
            $path = substr($url, strlen(self::BASE));

            return match (true) {
                $method === 'GET' && $path === 'adaccounts/acc1' => Http::response(['request_status' => 'SUCCESS', 'adaccounts' => [['adaccount' => ['id' => 'acc1', 'name' => 'Acme Snap', 'currency' => 'SAR', 'timezone' => 'Asia/Riyadh', 'status' => 'ACTIVE']]]]),
                $method === 'GET' && $path === 'adaccounts/acc1/pixels' => Http::response(['request_status' => 'SUCCESS', 'pixels' => [['pixel' => ['id' => self::PIXEL, 'name' => 'Site pixel', 'status' => 'ACTIVE']]]]),
                $method === 'GET' && $path === 'adaccounts/acc1/segments' => Http::response(['request_status' => 'SUCCESS', 'segments' => [
                    ['segment' => ['id' => '111', 'name' => 'Buyers', 'source_type' => 'FIRST_PARTY', 'status' => 'ACTIVE', 'targetable_status' => 'READY']],
                    ['segment' => ['id' => '222', 'name' => 'Visitors', 'source_type' => 'PIXEL', 'status' => 'ACTIVE', 'targetable_status' => 'READY']],
                ]]),
                $method === 'POST' && $path === 'adaccounts/acc1/campaigns' => Http::response(['request_status' => 'SUCCESS', 'campaigns' => [['sub_request_status' => 'SUCCESS', 'campaign' => ['id' => 'camp1']]]]),
                $method === 'POST' && $path === 'campaigns/camp1/adsquads' => Http::response(['request_status' => 'SUCCESS', 'adsquads' => [['sub_request_status' => 'SUCCESS', 'adsquad' => ['id' => 'sq1']]]]),
                $method === 'POST' && $path === 'adaccounts/acc1/media' => Http::response(['request_status' => 'SUCCESS', 'media' => [['sub_request_status' => 'SUCCESS', 'media' => ['id' => 'med1', 'media_status' => 'PENDING_UPLOAD']]]]),
                $method === 'POST' && $path === 'media/med1/upload' => Http::response(['request_status' => 'SUCCESS', 'result' => ['id' => 'med1']]),
                $method === 'GET' && $path === 'media/med1' => Http::response(['request_status' => 'SUCCESS', 'media' => [['media' => ['id' => 'med1', 'media_status' => 'READY']]]]),
                $method === 'POST' && $path === 'adaccounts/acc1/creatives' => $this->failCreative
                    // Snap reports item failures as HTTP 200 + request_status ERROR.
                    ? Http::response(['request_status' => 'ERROR', 'creatives' => [['sub_request_status' => 'ERROR', 'sub_request_error_reason' => 'Headline contains a prohibited word']]])
                    : Http::response(['request_status' => 'SUCCESS', 'creatives' => [['sub_request_status' => 'SUCCESS', 'creative' => ['id' => 'cr1']]]]),
                $method === 'POST' && $path === 'adsquads/sq1/ads' => Http::response(['request_status' => 'SUCCESS', 'ads' => [['sub_request_status' => 'SUCCESS', 'ad' => ['id' => 'ad1']]]]),
                $method === 'PATCH' => Http::response(['request_status' => 'SUCCESS']),
                default => Http::response(['request_status' => 'ERROR', 'debug_message' => "Unexpected {$method} {$path}"], 404),
            };
        });
    }

    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'objective' => 'SALES',
            'destination' => 'web',
            'status' => 'ACTIVE',
            'campaign' => ['mode' => 'new', 'name' => 'Launch', 'daily_cap' => 100],
            'adsquad' => [
                'goal' => 'PIXEL_PURCHASE', 'bid_strategy' => 'TARGET_COST', 'bid_amount' => 12.5, 'budget_type' => 'daily', 'budget' => 50,
                'start_time' => '2026-11-01T09:00', 'end_time' => '2026-11-30T23:00', 'placement_mode' => 'custom', 'positions' => ['INTERSTITIAL_USER', 'INSTREAM'],
                'content_mode' => 'exclude', 'content_types' => ['NEWS'], 'pixel_id' => self::PIXEL, 'conversion_window' => 'SWIPE_7DAY',
                'targeting' => [
                    'countries' => ['sa', 'ae'], 'regions' => [['id' => '35000', 'country' => 'sa']], 'languages' => ['ar'], 'gender' => 'FEMALE', 'min_age' => 18, 'max_age' => 34,
                    'interests' => ['SLC_1'], 'audiences' => ['222'], 'excluded_audiences' => ['111'], 'os_types' => ['iOS'], 'os_versions' => ['iOS' => ['min' => '15.0', 'max' => null]],
                ],
            ],
            'ad' => ['profile_id' => self::PROFILE, 'headline' => 'New season is here', 'media_ids' => [$this->video->id], 'cta' => 'SHOP_NOW', 'url' => 'https://acme.test/shoes', 'shareable' => true],
        ], $overrides);
    }

    private function writes(): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])->reject(fn ($r) => $r->method() === 'GET')
            ->map(fn ($r) => $r->method() . ' ' . str_replace(self::BASE, '', strtok($r->url(), '?')))->values()->all();
    }

    public function test_targeting_maps_to_a_snap_targeting_spec(): void
    {
        $spec = $this->builder()->targetingSpec([
            'countries' => ['sa', 'ae'], 'regions' => [['id' => '35000', 'country' => 'sa']], 'excluded_regions' => [['id' => '9', 'country' => 'ae']],
            'postal_codes' => [['code' => '11564', 'country' => 'sa']], 'circles' => [['latitude' => 24.7, 'longitude' => 46.6, 'radius' => 5]],
            'gender' => 'MALE', 'min_age' => 13, 'max_age' => 34, 'languages' => ['ar'], 'advanced_demographics' => ['DLXD_1'],
            'interests' => ['SLC_1'], 'excluded_interests' => ['SLC_2'], 'os_types' => ['ANDROID'], 'os_versions' => ['ANDROID' => ['min' => '10', 'max' => null]],
            'connection_type' => 'WIFI', 'audiences' => ['222'], 'excluded_audiences' => ['111'], 'interest_expansion' => true,
            'metros' => [['id' => '557', 'country' => 'ae']], 'excluded_metros' => [['id' => '641', 'country' => 'ae']],
            'visitation' => ['VAC_1'], 'nielsen' => ['NLN_1000'], 'location_categories' => ['LOI_1000'], 'carriers' => ['US_ATT'], 'device_makes' => ['Apple/'],
        ]);

        $this->assertSame([
            ['country_code' => 'sa', 'region_id' => ['35000'], 'operation' => 'INCLUDE'],
            ['country_code' => 'sa', 'postal_code' => ['11564'], 'operation' => 'INCLUDE'],
            ['country_code' => 'ae', 'metro_id' => ['557'], 'operation' => 'INCLUDE'],
            ['country_code' => 'ae', 'metro_id' => ['641'], 'operation' => 'EXCLUDE'],
            ['country_code' => 'ae', 'region_id' => ['9'], 'operation' => 'EXCLUDE'],
        ], $spec['geos']);
        // min_age 13 is Snap's default, so it is omitted.
        $this->assertSame([['gender' => 'MALE', 'max_age' => '34', 'languages' => ['ar'], 'advanced_demographics' => ['DLXD_1'], 'operation' => 'INCLUDE']], $spec['demographics']);
        $this->assertSame([['category_id' => ['SLC_1', 'VAC_1', 'NLN_1000'], 'operation' => 'INCLUDE'], ['category_id' => ['SLC_2'], 'operation' => 'EXCLUDE']], $spec['interests']);
        $this->assertSame([['os_type' => 'ANDROID', 'os_version_min' => '10', 'connection_type' => 'WIFI', 'carrier_id' => ['US_ATT'], 'marketing_name' => ['Apple/'], 'operation' => 'INCLUDE']], $spec['devices']);
        $this->assertSame(['categories_loi' => ['LOI_1000'], 'operation' => 'INCLUDE'], $spec['locations'][1]);
        $this->assertSame([['segment_id' => ['222'], 'operation' => 'INCLUDE'], ['segment_id' => ['111'], 'operation' => 'EXCLUDE']], $spec['segments']);
        $this->assertSame('KILOMETERS', $spec['locations'][0]['circles'][0]['unit']);
        $this->assertTrue($spec['enable_targeting_expansion']);
        $this->assertSame(['interest_expansion_option' => ['enabled' => true], 'custom_audience_expansion_option' => ['enabled' => false]], $spec['auto_expansion_options']);
    }

    public function test_submit_creates_everything_paused_then_activates_and_replays(): void
    {
        $result = $this->builder()->submit($this->input(), 'key-0000000000000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('sq1', $result['adsquad_id']);
        $this->assertSame([
            'POST adaccounts/acc1/campaigns', 'POST campaigns/camp1/adsquads', 'POST adaccounts/acc1/media', 'POST media/med1/upload',
            'POST adaccounts/acc1/creatives', 'POST adsquads/sq1/ads',
            'PATCH adsquads/sq1/ads/ad1', 'PATCH campaigns/camp1/adsquads/sq1', 'PATCH adaccounts/acc1/campaigns/camp1',
        ], $this->writes());

        $calls = collect(Http::recorded())->map(fn ($p) => $p[0])->reject(fn ($r) => $r->method() === 'GET')->values();
        $campaign = $calls[0]->data()['campaigns'][0];
        $this->assertSame(['PAUSED', 'SALES', 100000000, 'AUCTION'], [$campaign['status'], $campaign['objective_v2_properties']['objective_v2_type'], $campaign['daily_budget_micro'], $campaign['buy_model']]);
        $this->assertSame('2026-11-01T06:00:00.000Z', $campaign['start_time'], 'account time zone (Asia/Riyadh) -> UTC');
        $this->assertSame('Bearer tok', $calls[0]->header('Authorization')[0]);

        $squad = $calls[1]->data()['adsquads'][0];
        $this->assertSame(['PAUSED', 'PIXEL_PURCHASE', 'TARGET_COST', 12500000, 50000000, 'DAILY_BUDGET', self::PIXEL, 'SWIPE_7DAY'], [
            $squad['status'], $squad['optimization_goal'], $squad['bid_strategy'], $squad['bid_micro'], $squad['daily_budget_micro'], $squad['delivery_constraint'], $squad['pixel_id'], $squad['conversion_window'],
        ]);
        $this->assertSame(['config' => 'CUSTOM', 'platforms' => ['SNAPCHAT'], 'snapchat_positions' => ['INTERSTITIAL_USER', 'INSTREAM'], 'exclusion' => ['content_types' => ['NEWS']]], $squad['placement_v2']);
        $this->assertSame([['country_code' => 'sa', 'region_id' => ['35000'], 'operation' => 'INCLUDE'], ['country_code' => 'ae', 'operation' => 'INCLUDE']], $squad['targeting']['geos']);
        $this->assertArrayNotHasKey('lifetime_budget_micro', $squad);

        $creative = $calls[4]->data()['creatives'][0];
        $this->assertSame(['WEB_VIEW', 'med1', self::PROFILE, 'SHOP_NOW', 'https://acme.test/shoes'], [$creative['type'], $creative['top_snap_media_id'], $creative['profile_properties']['profile_id'], $creative['call_to_action'], $creative['web_view_properties']['url']]);
        $this->assertSame(['REMOTE_WEBPAGE', 'PAUSED', 'cr1'], [$calls[5]->data()['ads'][0]['type'], $calls[5]->data()['ads'][0]['status'], $calls[5]->data()['ads'][0]['creative_id']]);
        $this->assertSame('application/json-patch+json', $calls[6]->header('Content-Type')[0]);
        $this->assertSame([['op' => 'replace', 'path' => '/status', 'value' => 'ACTIVE']], json_decode($calls[6]->body(), true));

        $this->assertSame(1, AdCampaign::where('platform', 'snapchat')->where('ad_campaign_id', 'camp1')->count());
        $this->assertSame(1, AdAdGroup::where('ad_adgroup_id', 'sq1')->count());
        $this->assertSame(1, Ad::where('ad_id', 'ad1')->count());

        // Same key again: replayed from the run log, nothing new sent to Snap.
        $before = count(Http::recorded());
        $again = $this->builder()->submit($this->input(), 'key-0000000000000001');
        $this->assertTrue($again['replayed']);
        $this->assertCount($before, Http::recorded());
    }

    public function test_retry_after_failure_resumes_without_duplicates(): void
    {
        $this->failCreative = true;
        $first = $this->builder()->submit($this->input(['status' => 'PAUSED']), 'key-0000000000000002');

        $this->assertFalse($first['success']);
        $this->assertSame('creative', $first['step']);
        $this->assertSame('Headline contains a prohibited word', $first['message']);
        $this->assertSame(['campaign', 'adsquad'], array_column($first['created'], 'type'));

        $this->failCreative = false;
        $second = $this->builder()->submit($this->input(['status' => 'PAUSED']), 'key-0000000000000002');
        $this->assertTrue($second['success'], $second['message'] ?? '');
        $this->assertSame('PAUSED', $second['status']);

        $writes = collect($this->writes())->countBy();
        $this->assertSame(1, $writes['POST adaccounts/acc1/campaigns']);
        $this->assertSame(1, $writes['POST campaigns/camp1/adsquads']);
        $this->assertSame(1, $writes['POST media/med1/upload'], 'uploaded media is reused');
        $this->assertSame(2, $writes['POST adaccounts/acc1/creatives']);
        $this->assertArrayNotHasKey('PATCH adaccounts/acc1/campaigns/camp1', $writes->all(), 'paused submissions are never activated');
    }

    public function test_rejects_invalid_selections_before_calling_snap(): void
    {
        $noPixel = $this->builder()->submit($this->input(['adsquad' => ['pixel_id' => null]]), 'key-0000000000000003');
        $input = $this->input();
        $input['adsquad']['positions'] = ['CHAT_FEED'];
        $chatOnly = $this->builder()->submit($input, 'key-0000000000000004');
        $foreignAudience = $this->builder()->submit($this->input(['adsquad' => ['targeting' => ['audiences' => ['999']]]]), 'key-0000000000000005');
        $wrongGoal = $this->builder()->submit($this->input(['adsquad' => ['goal' => 'APP_INSTALLS']]), 'key-0000000000000006');

        foreach ([$noPixel, $chatOnly, $foreignAudience, $wrongGoal] as $result) {
            $this->assertFalse($result['success']);
            $this->assertSame('validation', $result['step']);
        }
        $this->assertSame([], $this->writes());
    }

    public function test_profiles_come_from_the_ad_account(): void
    {
        $this->assertSame([['id' => self::PROFILE, 'name' => 'Acme', 'logo' => null]], $this->builder()->profiles());
    }

    public function test_profiles_saved_at_connect_are_used_without_calling_snap(): void
    {
        $this->account->forceFill(['metadata' => $this->account->metadata + ['public_profiles' => [['id' => '971b6883-2309-419a-b988-8f04d6436f49', 'name' => 'Socialeaz', 'logo' => null]]]])->save();

        $this->assertSame('Socialeaz', $this->builder()->profiles()[0]['name']);
        $this->assertFalse(collect(Http::recorded())->contains(fn ($p) => str_contains($p[0]->url(), 'businessapi')));
    }

    public function test_expired_token_is_refreshed_and_shared_with_sibling_accounts(): void
    {
        $sibling = SocialAccount::create(['user_id' => 7, 'platform' => 'snapchat', 'name' => 'Other', 'platform_account_id' => 'acc2', 'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->subMinute()]);
        $this->account->forceFill(['expires_at' => now()->subMinute()])->save();

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'accounts.snapchat.com/*' => Http::response(['access_token' => 'tok2', 'refresh_token' => 'ref2', 'expires_in' => 1800]),
            '*' => Http::response(['request_status' => 'SUCCESS', 'adaccounts' => [['adaccount' => ['id' => 'acc1']]]]),
        ]);

        (new SnapchatAdsApi($this->account, self::BASE))->get('adaccounts/acc1');

        $this->assertSame('Bearer tok2', collect(Http::recorded())->last()[0]->header('Authorization')[0]);
        $this->assertSame(['tok2', 'ref2'], [$this->account->fresh()->access_token, $this->account->fresh()->refresh_token]);
        $this->assertSame('tok2', $sibling->fresh()->access_token, 'accounts sharing the grant get the new token');
    }

    public function test_auth_errors_ask_to_reconnect(): void
    {
        $this->account->forceFill(['refresh_token' => null])->save();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['request_status' => 'ERROR', 'debug_message' => 'Invalid access token'], 401)]);

        try {
            (new SnapchatAdsApi($this->account, self::BASE))->get('adaccounts/acc1');
            $this->fail('Expected a SnapchatApiException');
        } catch (SnapchatApiException $e) {
            $this->assertTrue($e->isAuthError());
            $this->assertStringContainsString('Invalid access token', $e->getMessage());
        }
    }
}
