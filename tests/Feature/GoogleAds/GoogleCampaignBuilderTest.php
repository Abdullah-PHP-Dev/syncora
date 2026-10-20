<?php

namespace Tests\Feature\GoogleAds;

use App\Http\Requests\Admin\GoogleAdBuilderRequest;
use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Services\AdServices\GoogleCampaignBuilder;
use App\Support\Ads\GoogleAdsApi;
use App\Support\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * GoogleCampaignBuilder against a faked Google Ads REST API: per-type
 * operation mapping, the single atomic googleAds:mutate, image asset
 * uploads, conversion goals, idempotent replays, resuming after a failure,
 * per-type eligibility and token refresh.
 */
class GoogleCampaignBuilderTest extends TestCase
{
    private SocialAccount $account;

    private array $mutates = [];

    private bool $failMutate = false;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdCampaign::class, AdAdGroup::class, AdCreative::class, Ad::class, AdBuilderRun::class, SocialAccount::class, MediaAsset::class] as $model) {
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

        (new \ReflectionProperty(Settings::class, 'requestCache'))->setValue(null, ['ads.google.developer_token' => 'dev-token', 'ads.google.client_id' => 'cid', 'ads.google.client_secret' => 'csecret']);

        $this->account = $this->insert(SocialAccount::class, [
            'user_id' => 7, 'platform' => 'google', 'name' => 'Acme Google', 'platform_account_id' => '123-456-7890',
            'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addMinutes(20), 'has_ads_permission' => true,
        ]);

        $this->fakeGoogle();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Settings::class, 'requestCache'))->setValue(null, null);
        parent::tearDown();
    }

    /** Inserted without the package-limit save hook (it needs the full users/packages schema). */
    private function insert(string $model, array $attributes)
    {
        $instance = new $model();
        $id = \Illuminate\Support\Facades\DB::table($instance->getTable())->insertGetId($attributes + ['created_at' => now(), 'updated_at' => now()]);

        return $model::find($id);
    }

    private function builder(): GoogleCampaignBuilder
    {
        return new GoogleCampaignBuilder(new GoogleAdsApi($this->account->fresh()));
    }

    private function fakeGoogle(): void
    {
        Http::fake(function (HttpRequest $request) {
            $url = $request->url();
            $body = $request->data();

            if (str_ends_with($url, 'googleAds:mutate')) {
                $this->mutates[] = $body['mutateOperations'];
                if ($this->failMutate) {
                    return Http::response(['error' => ['code' => 400, 'message' => 'Request contains an invalid argument.', 'details' => [['errors' => [[
                        'errorCode' => ['campaignError' => 'DUPLICATE_CAMPAIGN_NAME'], 'message' => 'A campaign with this name already exists.',
                        'location' => ['fieldPathElements' => [['fieldName' => 'operations'], ['fieldName' => 'create'], ['fieldName' => 'name']]],
                    ]]]]]], 400);
                }

                return Http::response(['mutateOperationResponses' => array_map(function ($op) {
                    $kind = array_key_first($op);
                    $result = str_replace('Operation', 'Result', $kind);
                    $resource = $op[$kind]['create']['resourceName'] ?? 'customers/1234567890/x/1';

                    return [$result => ['resourceName' => preg_replace('#/-(\d+)$#', '/9$1', $resource)]];
                }, $body['mutateOperations'])]);
            }
            if (str_ends_with($url, 'assets:mutate')) {
                return Http::response(['results' => [['resourceName' => 'customers/1234567890/assets/' . (500 + count($this->mutates) + random_int(1, 99999))]]]);
            }
            if (str_ends_with($url, '/campaigns:mutate')) {
                return Http::response(['results' => [['resourceName' => 'customers/1234567890/campaigns/24383575678']]]);
            }
            if (str_ends_with($url, 'campaignConversionGoals:mutate')) {
                return Http::response(['results' => array_map(fn ($o) => ['resourceName' => $o['update']['resourceName']], $body['operations'])]);
            }
            if (str_ends_with($url, 'googleAds:search')) {
                $q = $body['query'];
                if (str_contains($q, 'FROM customer ')) {
                    return Http::response(['results' => [['customer' => ['id' => '1234567890', 'descriptiveName' => 'Acme', 'currencyCode' => 'SAR', 'timeZone' => 'Asia/Riyadh', 'status' => 'ENABLED', 'manager' => false]]]]);
                }
                if (str_contains($q, 'FROM operating_system_version_constant')) {
                    return Http::response(['results' => [
                        ['operatingSystemVersionConstant' => ['id' => '630152', 'name' => 'iOS', 'osMajorVersion' => 17, 'osMinorVersion' => 0, 'operatorType' => 'GREATER_THAN_EQUALS_TO']],
                        ['operatingSystemVersionConstant' => ['id' => '630000', 'name' => 'Android', 'operatorType' => 'EQUALS_TO']],
                    ]]);
                }
                if (str_contains($q, 'FROM life_event')) {
                    return Http::response(['results' => [['lifeEvent' => ['resourceName' => 'customers/1234567890/lifeEvents/1001', 'name' => 'Recently moved']]]]);
                }
                if (str_contains($q, 'FROM customer_conversion_goal')) {
                    return Http::response(['results' => [['customerConversionGoal' => ['category' => 'PURCHASE', 'origin' => 'WEBSITE', 'biddable' => true]]]]);
                }
                if (str_contains($q, 'FROM campaign_conversion_goal')) {
                    return Http::response(['results' => [
                        ['campaignConversionGoal' => ['resourceName' => 'customers/1234567890/campaignConversionGoals/92~PURCHASE~WEBSITE', 'category' => 'PURCHASE', 'origin' => 'WEBSITE', 'biddable' => true]],
                        ['campaignConversionGoal' => ['resourceName' => 'customers/1234567890/campaignConversionGoals/92~SUBMIT_LEAD_FORM~WEBSITE', 'category' => 'SUBMIT_LEAD_FORM', 'origin' => 'WEBSITE', 'biddable' => false]],
                    ]]);
                }

                return Http::response(['results' => []]);
            }
            if (str_ends_with($url, 'geoTargetConstants:suggest')) {
                return Http::response(['geoTargetConstantSuggestions' => [['reach' => '1000', 'geoTargetConstant' => ['id' => '2682', 'name' => 'Saudi Arabia', 'canonicalName' => 'Saudi Arabia', 'countryCode' => 'SA', 'targetType' => 'Country', 'status' => 'ENABLED']]]]);
            }
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'fresh', 'expires_in' => 3600]);
            }

            return Http::response(['error' => ['message' => 'unexpected ' . $url]], 404);
        });
    }

    private function searchInput(array $overrides = []): array
    {
        return array_replace_recursive([
            'status' => 'PAUSED',
            'goal' => 'LEADS',
            'campaign_type' => 'SEARCH',
            'campaign' => ['name' => 'Spring leads', 'budget' => 50, 'bidding' => 'MAXIMIZE_CONVERSIONS', 'target_cpa' => 12.5, 'start_date' => now()->addDay()->toDateString(), 'end_date' => null, 'geo_mode' => 'PRESENCE', 'search_partners' => true, 'display_expansion' => false, 'goals' => null],
            'targeting' => [
                'locations' => [['id' => '2682', 'name' => 'Saudi Arabia', 'negative' => false], ['id' => '1012088', 'name' => 'Jeddah', 'negative' => true]],
                'radius' => [['latitude' => 24.7136, 'longitude' => 46.6753, 'radius' => 15, 'unit' => 'KILOMETERS']],
                'schedule' => [['day' => 'MONDAY', 'start_hour' => 9, 'end_hour' => 18, 'bid_adjustment' => 20]],
                'devices' => ['TABLET' => -100, 'MOBILE' => 25],
                'ages' => ['AGE_RANGE_65_UP'], 'genders' => [], 'parental' => [], 'incomes' => ['INCOME_RANGE_0_50'],
                'audiences' => [['id' => 'customers/1234567890/userLists/55', 'kind' => 'user_list', 'name' => 'Visitors'], ['id' => 'customers/1234567890/userInterests/80432', 'kind' => 'user_interest', 'name' => 'Running']],
                'audience_mode' => 'observation',
                'negative_keywords' => [['text' => 'free', 'match_type' => 'BROAD']],
            ],
            'ad_group' => ['name' => '', 'keywords' => [['text' => 'running shoes', 'match_type' => 'PHRASE'], ['text' => 'buy running shoes', 'match_type' => 'EXACT']]],
            'ad' => ['final_url' => 'https://acme.test/shoes', 'path1' => 'shoes', 'path2' => 'sale', 'headlines' => ['Running Shoes', 'Free Delivery', 'Shop Acme'], 'descriptions' => ['Top running shoes for every runner.', 'Order today, delivered tomorrow.']],
        ], $overrides);
    }

    private function images(): array
    {
        Storage::fake('local');
        $make = function (string $name, int $w, int $h) {
            Storage::disk('local')->put("gallery/{$name}", 'png-bytes');

            return $this->insert(MediaAsset::class, ['user_id' => 7, 'type' => 'image', 'disk' => 'local', 'path' => "gallery/{$name}", 'url' => "https://cdn.test/{$name}", 'original_name' => $name, 'extension' => 'png', 'mime_type' => 'image/png', 'size' => 2048, 'width' => $w, 'height' => $h])->id;
        };

        return ['marketing' => [$make('wide.png', 1200, 628)], 'square' => [$make('square.png', 1200, 1200)], 'logo' => [$make('logo.png', 512, 512)]];
    }

    private function ops(array $mutate, string $kind): array
    {
        return array_values(array_map(fn ($o) => $o[$kind]['create'], array_filter($mutate, fn ($o) => isset($o[$kind]))));
    }

    public function test_bootstrap_reads_customer_goals_and_type_matrix(): void
    {
        $boot = $this->builder()->bootstrap();

        $this->assertSame('SAR', $boot['account']['currency']);
        $this->assertSame('Asia/Riyadh', $boot['account']['timezone']);
        $this->assertSame(15, $boot['vat_rate']);
        $this->assertTrue($boot['account']['active']);
        $this->assertSame([['category' => 'PURCHASE', 'origin' => 'WEBSITE', 'biddable' => true]], $boot['conversion_goals']);
        $this->assertSame(['MAXIMIZE_CONVERSIONS', 'MAXIMIZE_CONVERSION_VALUE'], $boot['types']['PERFORMANCE_MAX']['bidding']);
        Http::assertSent(fn ($r) => $r->hasHeader('developer-token', 'dev-token') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_location_suggestions_are_mapped(): void
    {
        $this->assertSame([['id' => '2682', 'name' => 'Saudi Arabia', 'type' => 'Country', 'country' => 'SA', 'reach' => 1000]], $this->builder()->locations('Saudi', 'en'));
    }

    public function test_search_campaign_is_created_in_one_atomic_mutate(): void
    {
        $result = $this->builder()->submit($this->searchInput(), 'key-search-0000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertCount(1, $this->mutates);
        $m = $this->mutates[0];

        // Order: budget, campaign, campaign criteria, ad group, ad group criteria, ad.
        $kinds = array_map('array_key_first', $m);
        $this->assertSame('campaignBudgetOperation', $kinds[0]);
        $this->assertSame('campaignOperation', $kinds[1]);
        $this->assertSame('adGroupAdOperation', end($kinds));
        $this->assertLessThan(array_search('adGroupCriterionOperation', $kinds), array_search('adGroupOperation', $kinds));

        $budget = $this->ops($m, 'campaignBudgetOperation')[0];
        $this->assertSame(50_000_000, $budget['amountMicros']);
        $this->assertFalse($budget['explicitlyShared']);

        $c = $this->ops($m, 'campaignOperation')[0];
        $this->assertSame('SEARCH', $c['advertisingChannelType']);
        $this->assertSame('PAUSED', $c['status']);
        $this->assertSame(['targetCpaMicros' => 12_500_000], $c['maximizeConversions']);
        $this->assertTrue($c['networkSettings']['targetSearchNetwork']);
        $this->assertFalse($c['networkSettings']['targetContentNetwork']);
        $this->assertSame('PRESENCE', $c['geoTargetTypeSetting']['positiveGeoTargetType']);
        $this->assertSame(now()->addDay()->toDateString() . ' 00:00:00', $c['startDateTime']);
        $this->assertArrayNotHasKey('endDateTime', $c);
        $this->assertSame('DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING', $c['containsEuPoliticalAdvertising']);

        $criteria = $this->ops($m, 'campaignCriterionOperation');
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'negative' => true, 'location' => ['geoTargetConstant' => 'geoTargetConstants/1012088']], $criteria);
        // Search no longer takes campaign-level language targeting (Google, Sept 2026).
        $this->assertEmpty(array_filter($criteria, fn ($c) => isset($c['language'])));
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'device' => ['type' => 'TABLET'], 'bidModifier' => 0.0], $criteria);
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'device' => ['type' => 'MOBILE'], 'bidModifier' => 1.25], $criteria);
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'negative' => true, 'keyword' => ['text' => 'free', 'matchType' => 'BROAD']], $criteria);
        $proximity = collect($criteria)->firstWhere('proximity')['proximity'];
        $this->assertSame(['latitudeInMicroDegrees' => 24713600, 'longitudeInMicroDegrees' => 46675300], $proximity['geoPoint']);
        $schedule = collect($criteria)->firstWhere('adSchedule');
        $this->assertSame(['dayOfWeek' => 'MONDAY', 'startHour' => 9, 'startMinute' => 'ZERO', 'endHour' => 18, 'endMinute' => 'ZERO'], $schedule['adSchedule']);
        $this->assertSame(1.2, $schedule['bidModifier']);

        $group = $this->ops($m, 'adGroupOperation')[0];
        $this->assertSame('SEARCH_STANDARD', $group['type']);
        $this->assertSame([['targetingDimension' => 'AUDIENCE', 'bidOnly' => true]], $group['targetingSetting']['targetRestrictions']);

        $agc = $this->ops($m, 'adGroupCriterionOperation');
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'keyword' => ['text' => 'buy running shoes', 'matchType' => 'EXACT']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'negative' => true, 'ageRange' => ['type' => 'AGE_RANGE_65_UP']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'negative' => true, 'incomeRange' => ['type' => 'INCOME_RANGE_0_50']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'userList' => ['userList' => 'customers/1234567890/userLists/55']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'userInterest' => ['userInterestCategory' => 'customers/1234567890/userInterests/80432']], $agc);

        $ad = $this->ops($m, 'adGroupAdOperation')[0]['ad'];
        $this->assertSame(['https://acme.test/shoes'], $ad['finalUrls']);
        $this->assertCount(3, $ad['responsiveSearchAd']['headlines']);
        $this->assertSame('sale', $ad['responsiveSearchAd']['path2']);

        $campaign = AdCampaign::first();
        $this->assertSame('92', (string) $campaign->ad_campaign_id);
        $this->assertSame('google', $campaign->platform);
        $this->assertSame('SEARCH', $campaign->advertising_channel_type);
        $this->assertSame(1, AdAdGroup::count());
        $this->assertSame(1, Ad::count());
        $this->assertSame('92', $result['campaign_id']);
    }

    public function test_replay_does_not_create_again_and_failure_resumes(): void
    {
        $this->failMutate = true;
        $failed = $this->builder()->submit($this->searchInput(), 'key-retry-00000001');
        $this->assertFalse($failed['success']);
        $this->assertSame('campaign', $failed['step']);
        $this->assertStringContainsString('A campaign with this name already exists. (operations.create.name)', $failed['message']);
        $this->assertSame(0, AdCampaign::count());

        $this->failMutate = false;
        $this->assertTrue($this->builder()->submit($this->searchInput(), 'key-retry-00000001')['success']);
        $replay = $this->builder()->submit($this->searchInput(), 'key-retry-00000001');
        $this->assertTrue($replay['replayed']);
        $this->assertCount(2, $this->mutates);
        $this->assertSame(1, AdCampaign::count());
    }

    public function test_custom_conversion_goals_are_applied_to_the_new_campaign(): void
    {
        $input = $this->searchInput(['campaign' => ['goals' => [['category' => 'PURCHASE', 'origin' => 'WEBSITE', 'biddable' => false], ['category' => 'SUBMIT_LEAD_FORM', 'origin' => 'WEBSITE', 'biddable' => true]]]]);

        $this->assertTrue($this->builder()->submit($input, 'key-goals-00000001')['success']);

        Http::assertSent(function ($r) {
            if (! str_ends_with($r->url(), 'campaignConversionGoals:mutate')) {
                return false;
            }
            $ops = collect($r->data()['operations'])->pluck('update')->keyBy('resourceName');

            return $ops->count() === 2
                && $ops['customers/1234567890/campaignConversionGoals/92~PURCHASE~WEBSITE']['biddable'] === false
                && $ops['customers/1234567890/campaignConversionGoals/92~SUBMIT_LEAD_FORM~WEBSITE']['biddable'] === true;
        });
    }

    public function test_display_campaign_uploads_images_and_builds_a_responsive_display_ad(): void
    {
        $images = $this->images();
        $input = $this->searchInput([
            'campaign_type' => 'DISPLAY',
            'campaign' => ['bidding' => 'MAXIMIZE_CLICKS', 'target_cpa' => null, 'cpc_ceiling' => 1.5],
            'targeting' => ['placements' => ['news.example.com'], 'excluded_placements' => ['bad.example.com'], 'topics' => [['id' => '13']], 'content_labels' => ['PARKED_DOMAIN'], 'devices' => ['CONNECTED_TV' => -100, 'TABLET' => null, 'MOBILE' => null],
                'audiences' => [['id' => 'customers/1234567890/customAudiences/77', 'kind' => 'custom_audience', 'name' => 'Runners']], 'audience_mode' => 'targeting'],
            'ad_group' => ['keywords' => [['text' => 'marathon', 'match_type' => 'EXACT']]],
            'ad' => ['path1' => null, 'path2' => null, 'long_headlines' => ['The best running shoes in the Kingdom'], 'business_name' => 'Acme', 'images' => $images],
        ]);

        $result = $this->builder()->submit($input, 'key-display-000001');
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $uploads = collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), 'assets:mutate'));
        $this->assertCount(3, $uploads);
        $this->assertSame(base64_encode('png-bytes'), $uploads->first()[0]->data()['operations'][0]['create']['imageAsset']['data']);

        $m = $this->mutates[0];
        $c = $this->ops($m, 'campaignOperation')[0];
        $this->assertSame('DISPLAY', $c['advertisingChannelType']);
        $this->assertSame(['cpcBidCeilingMicros' => 1_500_000], $c['targetSpend']);
        $this->assertTrue($c['networkSettings']['targetContentNetwork']);
        $criteria = $this->ops($m, 'campaignCriterionOperation');
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'negative' => true, 'contentLabel' => ['type' => 'PARKED_DOMAIN']], $criteria);
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'negative' => true, 'placement' => ['url' => 'bad.example.com']], $criteria);
        $this->assertContains(['campaign' => 'customers/1234567890/campaigns/-2', 'device' => ['type' => 'CONNECTED_TV'], 'bidModifier' => 0.0], $criteria);

        $this->assertSame('DISPLAY_STANDARD', $this->ops($m, 'adGroupOperation')[0]['type']);
        $this->assertFalse($this->ops($m, 'adGroupOperation')[0]['targetingSetting']['targetRestrictions'][0]['bidOnly']);
        $agc = $this->ops($m, 'adGroupCriterionOperation');
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'keyword' => ['text' => 'marathon', 'matchType' => 'BROAD']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'placement' => ['url' => 'news.example.com']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'topic' => ['topicConstant' => 'topicConstants/13']], $agc);
        $this->assertContains(['adGroup' => 'customers/1234567890/adGroups/-3', 'status' => 'ENABLED', 'customAudience' => ['customAudience' => 'customers/1234567890/customAudiences/77']], $agc);

        $rda = $this->ops($m, 'adGroupAdOperation')[0]['ad']['responsiveDisplayAd'];
        $this->assertCount(1, $rda['marketingImages']);
        $this->assertCount(1, $rda['squareMarketingImages']);
        $this->assertCount(1, $rda['logoImages']);
        $this->assertSame(['text' => 'The best running shoes in the Kingdom'], $rda['longHeadline']);
        $this->assertSame('Acme', $rda['businessName']);

        // A retry with the same key reuses the uploaded assets.
        $this->assertTrue($this->builder()->submit($input, 'key-display-000001')['replayed']);
        $this->assertCount(3, collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), 'assets:mutate')));
    }

    public function test_performance_max_creates_asset_group_with_linked_assets(): void
    {
        $images = $this->images();
        $input = $this->searchInput([
            'campaign_type' => 'PERFORMANCE_MAX',
            'campaign' => ['bidding' => 'MAXIMIZE_CONVERSION_VALUE', 'target_cpa' => null, 'target_roas' => 400],
            'ad' => ['long_headlines' => ['The best running shoes in the Kingdom'], 'business_name' => 'Acme', 'images' => $images],
        ]);
        unset($input['ad']['path1'], $input['ad']['path2']);
        $input['targeting'] = ['locations' => [['id' => '2682', 'name' => 'Saudi Arabia', 'negative' => false]], 'languages' => ['1019'], 'schedule' => [['day' => 'MONDAY', 'start_hour' => 9, 'end_hour' => 18, 'bid_adjustment' => 20]]];
        $input['ad_group'] = [];

        $result = $this->builder()->submit($input, 'key-pmax-000000001');
        $this->assertTrue($result['success'], $result['message'] ?? '');

        $m = $this->mutates[0];
        $c = $this->ops($m, 'campaignOperation')[0];
        $this->assertSame('PERFORMANCE_MAX', $c['advertisingChannelType']);
        $this->assertFalse($c['brandGuidelinesEnabled']);
        $this->assertSame(['targetRoas' => 4.0], $c['maximizeConversionValue']);
        $this->assertArrayNotHasKey('networkSettings', $c);
        $this->assertArrayNotHasKey('bidModifier', collect($this->ops($m, 'campaignCriterionOperation'))->firstWhere('adSchedule'));
        $this->assertEmpty($this->ops($m, 'adGroupOperation'));

        $kinds = array_map('array_key_first', $m);
        $this->assertLessThan(array_search('assetGroupOperation', $kinds), array_search('assetOperation', $kinds));
        $this->assertLessThan(array_search('assetGroupAssetOperation', $kinds), array_search('assetGroupOperation', $kinds));
        $fields = collect($this->ops($m, 'assetGroupAssetOperation'))->countBy('fieldType')->all();
        $this->assertSame(['HEADLINE' => 3, 'LONG_HEADLINE' => 1, 'DESCRIPTION' => 2, 'BUSINESS_NAME' => 1, 'MARKETING_IMAGE' => 1, 'SQUARE_MARKETING_IMAGE' => 1, 'LOGO' => 1], $fields);
        $this->assertSame(['https://acme.test/shoes'], $this->ops($m, 'assetGroupOperation')[0]['finalUrls']);
    }

    public function test_unsupported_selections_are_rejected_before_calling_google(): void
    {
        $pmax = $this->searchInput(['campaign_type' => 'PERFORMANCE_MAX', 'campaign' => ['bidding' => 'MAXIMIZE_CONVERSIONS']]);
        $result = $this->builder()->submit($pmax, 'key-reject-0000001');
        $this->assertFalse($result['success']);
        $this->assertSame('validation', $result['step']);

        $foreign = $this->searchInput(['targeting' => ['audiences' => [['id' => 'customers/999/userLists/1', 'kind' => 'user_list', 'name' => 'Other']]]]);
        $this->assertFalse($this->builder()->submit($foreign, 'key-reject-0000002')['success']);

        $customOnSearch = $this->searchInput(['targeting' => ['audiences' => [['id' => 'customers/1234567890/customAudiences/1', 'kind' => 'custom_audience', 'name' => 'C']]]]);
        $this->assertFalse($this->builder()->submit($customOnSearch, 'key-reject-0000003')['success']);

        $tv = $this->searchInput(['targeting' => ['devices' => ['CONNECTED_TV' => 10]]]);
        $this->assertFalse($this->builder()->submit($tv, 'key-reject-0000004')['success']);

        $this->assertCount(0, $this->mutates);
    }

    public function test_expired_token_is_refreshed(): void
    {
        $this->account->update(['expires_at' => now()->subMinute()]);

        $this->builder()->conversionGoals();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'googleAds:search') && $r->hasHeader('Authorization', 'Bearer fresh'));
        $this->assertSame('fresh', $this->account->fresh()->access_token);
    }

    public function test_request_rules_follow_ad_limits_per_type(): void
    {
        $rules = fn (array $data) => Validator::make($data, (new GoogleAdBuilderRequest())->merge($data)->rules());
        $base = $this->searchInput() + ['idempotency_key' => 'key-rules-00000001'];

        $this->assertFalse($rules($base)->fails(), json_encode($rules($base)->errors()->all()));

        $tooLong = $base;
        $tooLong['ad']['headlines'][0] = str_repeat('x', 31);
        $this->assertTrue($rules($tooLong)->errors()->has('ad.headlines.0'));

        $twoHeadlines = $base;
        $twoHeadlines['ad']['headlines'] = ['One', 'Two'];
        $this->assertTrue($rules($twoHeadlines)->errors()->has('ad.headlines'));

        $badBidding = $base;
        $badBidding['campaign_type'] = 'PERFORMANCE_MAX';
        $badBidding['campaign']['bidding'] = 'MANUAL_CPC';
        $this->assertTrue($rules($badBidding)->errors()->has('campaign.bidding'));

        $noKeywords = $base;
        $noKeywords['ad_group']['keywords'] = [];
        $this->assertTrue($rules($noKeywords)->errors()->has('ad_group.keywords'));
    }

    public function test_lifetime_budget_becomes_a_campaign_total_budget(): void
    {
        $end = now()->addDays(10)->toDateString();
        $input = $this->searchInput(['campaign' => ['budget' => 500, 'budget_type' => 'lifetime', 'end_date' => $end]]);

        $result = $this->builder()->submit($input, 'key-lifetime-00001');
        $this->assertTrue($result['success'], $result['message'] ?? '');

        $budget = $this->ops($this->mutates[0], 'campaignBudgetOperation')[0];
        $this->assertSame('CUSTOM_PERIOD', $budget['period']);
        $this->assertSame(500_000_000, $budget['totalAmountMicros']);
        $this->assertArrayNotHasKey('amountMicros', $budget);
        $this->assertFalse($budget['explicitlyShared']);
        $this->assertSame($end . ' 23:59:59', $this->ops($this->mutates[0], 'campaignOperation')[0]['endDateTime']);
        $this->assertSame('total', AdCampaign::first()->budget_mode);
    }

    public function test_lifetime_budget_needs_dates_and_a_supported_type(): void
    {
        $noEnd = $this->searchInput(['campaign' => ['budget_type' => 'lifetime']]);
        $this->assertSame('validation', $this->builder()->submit($noEnd, 'key-lifetime-00002')['step']);

        $display = $this->searchInput(['campaign_type' => 'DISPLAY', 'campaign' => ['budget_type' => 'lifetime', 'bidding' => 'MAXIMIZE_CLICKS', 'end_date' => now()->addDays(5)->toDateString()]]);
        $this->assertSame(__('google_builder.server.lifetime_unsupported'), $this->builder()->submit($display, 'key-lifetime-00003')['message']);
        $this->assertCount(0, $this->mutates);

        $rules = fn (array $data) => Validator::make($data, (new GoogleAdBuilderRequest())->merge($data)->rules());
        $base = $this->searchInput(['campaign' => ['budget_type' => 'lifetime']]) + ['idempotency_key' => 'key-rules-00000002'];
        $this->assertTrue($rules($base)->errors()->has('campaign.end_date'));
    }

    public function test_extra_display_and_search_targeting_is_mapped(): void
    {
        $images = $this->images();
        $input = $this->searchInput([
            'campaign_type' => 'DISPLAY',
            'campaign' => ['bidding' => 'MAXIMIZE_CLICKS', 'target_cpa' => null],
            'targeting' => [
                'devices' => ['TABLET' => null, 'MOBILE' => null],
                'audience_exclusions' => [['id' => 'customers/1234567890/userLists/66', 'name' => 'Buyers']],
                'ip_exclusions' => ['203.0.113.10', '198.51.100.0/24'],
                'excluded_topics' => [['id' => '44']],
                'app_categories' => [['id' => '60008']],
                'operating_systems' => [['id' => '630152']],
                'carriers' => [['id' => '70092']],
                'frequency_cap' => ['cap' => 3, 'unit' => 'WEEK'],
                'optimized_targeting' => true,
                'audiences' => [
                    ['id' => 'customers/1234567890/lifeEvents/1001', 'kind' => 'life_event', 'name' => 'Recently moved'],
                    ['id' => 'customers/1234567890/detailedDemographics/2002', 'kind' => 'detailed_demographic', 'name' => 'Homeowners'],
                ],
            ],
            'ad' => ['path1' => null, 'path2' => null, 'long_headlines' => ['The best running shoes in the Kingdom'], 'business_name' => 'Acme', 'images' => $images],
        ]);

        $result = $this->builder()->submit($input, 'key-extra-00000001');
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $m = $this->mutates[0];
        $camp = 'customers/1234567890/campaigns/-2';
        $grp = 'customers/1234567890/adGroups/-3';

        $c = $this->ops($m, 'campaignOperation')[0];
        $this->assertSame([['key' => ['level' => 'CAMPAIGN', 'eventType' => 'IMPRESSION', 'timeUnit' => 'WEEK', 'timeLength' => 1], 'cap' => 3]], $c['frequencyCaps']);
        $this->assertTrue($this->ops($m, 'adGroupOperation')[0]['optimizedTargetingEnabled']);

        $cc = $this->ops($m, 'campaignCriterionOperation');
        $this->assertContains(['campaign' => $camp, 'negative' => true, 'userList' => ['userList' => 'customers/1234567890/userLists/66']], $cc);
        $this->assertContains(['campaign' => $camp, 'negative' => true, 'ipBlock' => ['ipAddress' => '198.51.100.0/24']], $cc);
        $this->assertContains(['campaign' => $camp, 'negative' => true, 'mobileAppCategory' => ['mobileAppCategoryConstant' => 'mobileAppCategoryConstants/60008']], $cc);
        $this->assertContains(['campaign' => $camp, 'operatingSystemVersion' => ['operatingSystemVersionConstant' => 'operatingSystemVersionConstants/630152']], $cc);
        $this->assertContains(['campaign' => $camp, 'carrier' => ['carrierConstant' => 'carrierConstants/70092']], $cc);

        $agc = $this->ops($m, 'adGroupCriterionOperation');
        $this->assertContains(['adGroup' => $grp, 'negative' => true, 'topic' => ['topicConstant' => 'topicConstants/44']], $agc);
        $this->assertContains(['adGroup' => $grp, 'status' => 'ENABLED', 'lifeEvent' => ['lifeEventId' => '1001']], $agc);
        $this->assertContains(['adGroup' => $grp, 'status' => 'ENABLED', 'extendedDemographic' => ['extendedDemographicId' => '2002']], $agc);
    }

    public function test_performance_max_signals_are_linked_to_the_asset_group(): void
    {
        $input = $this->searchInput([
            'campaign_type' => 'PERFORMANCE_MAX',
            'campaign' => ['bidding' => 'MAXIMIZE_CONVERSIONS'],
            'ad' => ['long_headlines' => ['The best running shoes in the Kingdom'], 'business_name' => 'Acme', 'images' => $this->images()],
        ]);
        unset($input['ad']['path1'], $input['ad']['path2']);
        $input['targeting'] = [
            'locations' => [['id' => '2682', 'name' => 'Saudi Arabia', 'negative' => false]],
            'search_themes' => ['running shoes', 'marathon training'],
            'audience_signal' => ['id' => 'customers/1234567890/audiences/77', 'name' => 'Runners'],
        ];
        $input['ad_group'] = [];

        $this->assertTrue($this->builder()->submit($input, 'key-signals-000001')['success']);
        $m = $this->mutates[0];
        $signals = $this->ops($m, 'assetGroupSignalOperation');
        $this->assertSame([
            ['assetGroup' => 'customers/1234567890/assetGroups/-4', 'searchTheme' => ['text' => 'running shoes']],
            ['assetGroup' => 'customers/1234567890/assetGroups/-4', 'searchTheme' => ['text' => 'marathon training']],
            ['assetGroup' => 'customers/1234567890/assetGroups/-4', 'audience' => ['audience' => 'customers/1234567890/audiences/77']],
        ], $signals);
        $kinds = array_map('array_key_first', $m);
        $this->assertLessThan(array_search('assetGroupSignalOperation', $kinds), array_search('assetGroupOperation', $kinds));
    }

    public function test_options_a_campaign_type_does_not_support_are_rejected(): void
    {
        $freqOnSearch = $this->searchInput(['targeting' => ['frequency_cap' => ['cap' => 3, 'unit' => 'DAY']]]);
        $result = $this->builder()->submit($freqOnSearch, 'key-gate-000000001');
        $this->assertSame('validation', $result['step']);
        $this->assertStringContainsString('frequency_cap', $result['message']);

        $themesOnSearch = $this->searchInput(['targeting' => ['search_themes' => ['shoes']]]);
        $this->assertFalse($this->builder()->submit($themesOnSearch, 'key-gate-000000002')['success']);

        $foreignExclusion = $this->searchInput(['targeting' => ['audience_exclusions' => [['id' => 'customers/999/userLists/1', 'name' => 'X']]]]);
        $this->assertFalse($this->builder()->submit($foreignExclusion, 'key-gate-000000003')['success']);
        $this->assertCount(0, $this->mutates);

        $rules = fn (array $data) => (new GoogleAdBuilderRequest())->merge($data);
        $base = $this->searchInput(['targeting' => ['ip_exclusions' => ['999.1.1.1']]]) + ['idempotency_key' => 'key-rules-00000003'];
        $request = $rules($base);
        $v = Validator::make($base, $request->rules());
        $request->withValidator($v);
        $v->fails();
        $this->assertTrue($v->errors()->has('targeting.ip_exclusions.0'));
    }

    public function test_option_lists_and_life_events_are_read_from_google(): void
    {
        $os = $this->builder()->optionList('operating_systems');
        $this->assertSame([['id' => '630000', 'name' => 'Android'], ['id' => '630152', 'name' => 'iOS +']], $os);

        $segments = collect($this->builder()->interests());
        $this->assertSame('life_event', $segments->firstWhere('name', 'Recently moved')['kind']);
    }
    private function youtubeInput(): array
    {
        $images = $this->images();
        $input = $this->searchInput([
            'campaign_type' => 'DEMAND_GEN',
            'goal' => null,
            'campaign' => ['bidding' => 'TARGET_CPC', 'target_cpa' => null, 'target_cpc' => 0.4],
            'ad' => ['long_headlines' => ['The best running shoes in the Kingdom'], 'business_name' => 'Acme', 'images' => ['logo' => $images['logo']], 'videos' => ['dQw4w9WgXcQ', 'M7lc1UVf-VE'], 'call_to_action' => 'SHOP_NOW', 'breadcrumb1' => 'shoes', 'breadcrumb2' => 'sale'],
        ]);
        unset($input['ad']['path1'], $input['ad']['path2']);
        $input['ad_group'] = ['name' => 'Runners'];
        $input['targeting'] = [
            'locations' => [['id' => '2682', 'name' => 'Saudi Arabia', 'negative' => false], ['id' => '1012088', 'name' => 'Jeddah', 'negative' => true]],
            'languages' => ['1019'],
            'channels' => ['youtubeInStream', 'youtubeShorts'],
            'ages' => ['AGE_RANGE_65_UP'], 'genders' => ['FEMALE'], 'parental' => [], 'incomes' => [],
            'audiences' => [
                ['id' => 'customers/1234567890/userLists/55', 'kind' => 'user_list', 'name' => 'Visitors'],
                ['id' => 'customers/1234567890/lifeEvents/1001', 'kind' => 'life_event', 'name' => 'Recently moved'],
                ['id' => 'customers/1234567890/detailedDemographics/2002', 'kind' => 'detailed_demographic', 'name' => 'Homeowners'],
                ['id' => 'customers/1234567890/customAudiences/77', 'kind' => 'custom_audience', 'name' => 'Runners'],
            ],
            'audience_exclusions' => [['id' => 'customers/1234567890/userLists/66', 'name' => 'Buyers']],
            'optimized_targeting' => true,
        ];

        return $input;
    }

    public function test_youtube_demand_gen_campaign_is_created_with_grouped_audience(): void
    {
        $result = $this->builder()->submit($this->youtubeInput(), 'key-youtube-000001');
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $m = $this->mutates[0];
        $grp = 'customers/1234567890/adGroups/-3';
        $aud = 'customers/1234567890/audiences/-5';

        $c = $this->ops($m, 'campaignOperation')[0];
        $this->assertSame('DEMAND_GEN', $c['advertisingChannelType']);
        $this->assertSame(['upgradedTargeting' => true], $c['demandGenCampaignSettings']);
        $this->assertSame(['targetCpcMicros' => 400_000], $c['targetCpc']);
        $this->assertArrayNotHasKey('geoTargetTypeSetting', $c);
        $this->assertArrayNotHasKey('networkSettings', $c);
        $this->assertEmpty($this->ops($m, 'campaignCriterionOperation'));

        $audience = $this->ops($m, 'audienceOperation')[0];
        $this->assertSame('CUSTOMER', $audience['scope']);
        $this->assertSame([
            ['age' => ['ageRanges' => [['minAge' => 18, 'maxAge' => 24], ['minAge' => 25, 'maxAge' => 34], ['minAge' => 35, 'maxAge' => 44], ['minAge' => 45, 'maxAge' => 54], ['minAge' => 55, 'maxAge' => 64]], 'includeUndetermined' => true]],
            ['gender' => ['genders' => ['MALE'], 'includeUndetermined' => true]],
            ['audienceSegments' => ['segments' => [
                ['userList' => ['userList' => 'customers/1234567890/userLists/55']],
                ['lifeEvent' => ['lifeEvent' => 'customers/1234567890/lifeEvents/1001']],
                ['detailedDemographic' => ['detailedDemographic' => 'customers/1234567890/detailedDemographics/2002']],
                ['customAudience' => ['customAudience' => 'customers/1234567890/customAudiences/77']],
            ]]],
        ], $audience['dimensions']);
        $this->assertSame(['exclusions' => [['userList' => ['userList' => 'customers/1234567890/userLists/66']]]], $audience['exclusionDimension']);

        $group = $this->ops($m, 'adGroupOperation')[0];
        $this->assertArrayNotHasKey('type', $group);
        $this->assertSame(['useAudienceGrouped' => true], $group['audienceSetting']);
        $this->assertTrue($group['optimizedTargetingEnabled']);
        $this->assertSame(['youtubeInStream' => true, 'youtubeInFeed' => false, 'youtubeShorts' => true, 'discover' => false, 'gmail' => false, 'display' => false, 'maps' => false], $group['demandGenAdGroupSettings']['channelControls']['selectedChannels']);

        $agc = $this->ops($m, 'adGroupCriterionOperation');
        $this->assertContains(['adGroup' => $grp, 'negative' => true, 'location' => ['geoTargetConstant' => 'geoTargetConstants/1012088']], $agc);
        $this->assertContains(['adGroup' => $grp, 'language' => ['languageConstant' => 'languageConstants/1019']], $agc);
        $this->assertContains(['adGroup' => $grp, 'audience' => ['audience' => $aud]], $agc);

        $kinds = array_map('array_key_first', $m);
        $this->assertLessThan(array_search('adGroupOperation', $kinds), array_search('audienceOperation', $kinds));

        $assets = $this->ops($m, 'assetOperation');
        $this->assertSame(['youtubeVideoId' => 'dQw4w9WgXcQ'], $assets[0]['youtubeVideoAsset']);
        $this->assertSame(['callToAction' => 'SHOP_NOW'], $assets[2]['callToActionAsset']);

        $ad = $this->ops($m, 'adGroupAdOperation')[0]['ad'];
        $dg = $ad['demandGenVideoResponsiveAd'];
        $this->assertCount(2, $dg['videos']);
        $this->assertCount(1, $dg['logoImages']);
        $this->assertCount(1, $dg['callToActions']);
        $this->assertSame(['text' => 'Acme'], $dg['businessName']);
        $this->assertSame(['shoes', 'sale'], [$dg['breadcrumb1'], $dg['breadcrumb2']]);
        $this->assertSame(['https://acme.test/shoes'], $ad['finalUrls']);

        $this->assertSame('youtube', AdCampaign::first()->platform);
        $this->assertSame('DEMAND_GEN_VIDEO_RESPONSIVE_AD', Ad::first()->type);
    }

    public function test_youtube_rejects_targeting_demand_gen_does_not_support(): void
    {
        $noChannels = $this->youtubeInput();
        $noChannels['targeting']['channels'] = [];
        $this->assertSame('validation', $this->builder()->submit($noChannels, 'key-yt-reject-0001')['step']);

        $badVideo = $this->youtubeInput();
        $badVideo['ad']['videos'] = ['https://example.com/clip'];
        $this->assertSame('validation', $this->builder()->submit($badVideo, 'key-yt-reject-0002')['step']);

        $schedule = $this->youtubeInput();
        $schedule['targeting']['schedule'] = [['day' => 'MONDAY', 'start_hour' => 9, 'end_hour' => 18]];
        $this->assertStringContainsString('schedule', $this->builder()->submit($schedule, 'key-yt-reject-0003')['message']);

        $keywords = $this->youtubeInput();
        $keywords['ad_group']['keywords'] = [['text' => 'shoes', 'match_type' => 'BROAD']];
        $this->assertStringContainsString('keywords', $this->builder()->submit($keywords, 'key-yt-reject-0004')['message']);

        $allGenders = $this->youtubeInput();
        $allGenders['targeting']['genders'] = ['MALE', 'FEMALE', 'UNDETERMINED'];
        $this->assertSame('validation', $this->builder()->submit($allGenders, 'key-yt-reject-0005')['step']);

        $searchLanguages = $this->searchInput(['targeting' => ['languages' => ['1019']]]);
        $this->assertStringContainsString('languages', $this->builder()->submit($searchLanguages, 'key-yt-reject-0006')['message']);

        $this->assertCount(0, $this->mutates);
    }

    public function test_youtube_request_rules_and_video_links(): void
    {
        $this->assertSame('dQw4w9WgXcQ', GoogleAdBuilderRequest::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'));
        $this->assertSame('dQw4w9WgXcQ', GoogleAdBuilderRequest::youtubeId('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('M7lc1UVf-VE', GoogleAdBuilderRequest::youtubeId('https://youtube.com/shorts/M7lc1UVf-VE'));

        $data = $this->youtubeInput() + ['idempotency_key' => 'key-rules-00000009'];
        $data['ad']['images'] = ['logo' => [1]];
        $v = Validator::make($data, (new GoogleAdBuilderRequest())->merge($data)->rules());
        $this->assertFalse($v->fails(), json_encode($v->errors()->all()));

        $long = $data;
        $long['ad']['headlines'][0] = str_repeat('x', 41);
        $this->assertTrue(Validator::make($long, (new GoogleAdBuilderRequest())->merge($long)->rules())->errors()->has('ad.headlines.0'));

        $noCpc = $data;
        $noCpc['campaign']['target_cpc'] = null;
        $this->assertTrue(Validator::make($noCpc, (new GoogleAdBuilderRequest())->merge($noCpc)->rules())->errors()->has('campaign.target_cpc'));
    }

    public function test_youtube_targets_use_standalone_target_cpa_and_roas(): void
    {
        // The case that failed live: Maximise conversions + target CPA 10 on Demand Gen.
        $cpa = $this->youtubeInput();
        $cpa['campaign'] = array_merge($cpa['campaign'], ['bidding' => 'MAXIMIZE_CONVERSIONS', 'target_cpa' => 10, 'target_cpc' => null]);
        $this->assertTrue($this->builder()->submit($cpa, 'key-yt-bid-0000001')['success']);
        $c = $this->ops($this->mutates[0], 'campaignOperation')[0];
        $this->assertSame(['targetCpaMicros' => 10_000_000], $c['targetCpa']);
        $this->assertArrayNotHasKey('maximizeConversions', $c);

        $roas = $this->youtubeInput();
        $roas['campaign'] = array_merge($roas['campaign'], ['bidding' => 'MAXIMIZE_CONVERSION_VALUE', 'target_roas' => 350, 'target_cpc' => null]);
        $this->assertTrue($this->builder()->submit($roas, 'key-yt-bid-0000002')['success']);
        $c = $this->ops($this->mutates[1], 'campaignOperation')[0];
        $this->assertSame(['targetRoas' => 3.5], $c['targetRoas']);
        $this->assertArrayNotHasKey('maximizeConversionValue', $c);

        // Without a target, plain Maximise conversions (as before).
        $plain = $this->youtubeInput();
        $plain['campaign'] = array_merge($plain['campaign'], ['bidding' => 'MAXIMIZE_CONVERSIONS', 'target_cpa' => null, 'target_cpc' => null]);
        $this->assertTrue($this->builder()->submit($plain, 'key-yt-bid-0000003')['success']);
        $this->assertArrayHasKey('maximizeConversions', $this->ops($this->mutates[2], 'campaignOperation')[0]);
    }

    public function test_builder_saves_the_creative_the_edit_page_reads(): void
    {
        $this->assertTrue($this->builder()->submit($this->youtubeInput(), 'key-creative-00001')['success']);

        $creative = AdCreative::first();
        $this->assertSame('youtube', $creative->platform);
        $this->assertSame('Acme', $creative->business_name);
        $this->assertSame('https://acme.test/shoes', $creative->url);
        $this->assertSame((string) AdAdGroup::first()->id, (string) $creative->ad_adgroup_id);
        $this->assertSame((string) $creative->id, (string) Ad::first()->ad_creative_id);
        $this->assertCount(2, json_decode($creative->descriptions, true));
    }

    public function test_delete_and_pause_use_the_full_campaign_resource_name(): void
    {
        $campaign = AdCampaign::create(['ad_campaign_id' => '24383575678', 'platform' => 'google', 'user_id' => 7, 'name' => 'Old', 'status' => true]);
        $service = new class {
            use \App\Services\AdServices\Concerns\GoogleAdsApiTrait;

            public function boot(SocialAccount $account): static
            {
                $this->account = $account;
                $this->config = 'https://googleads.googleapis.com/v24/';
                $this->apiService = app(\App\Services\ApiService::class);
                $this->header = ['Authorization' => 'Bearer tok'];

                return $this;
            }
        };
        $service->boot($this->account->fresh());

        $this->assertTrue($service->updateStatus($campaign->id, 'PAUSED')['success']);
        Http::assertSent(fn ($r) => ($r->data()['operations'][0]['update']['resourceName'] ?? null) === 'customers/1234567890/campaigns/24383575678');

        $this->assertTrue($service->destroy('google', $campaign->id)['success']);
        Http::assertSent(fn ($r) => ($r->data()['operations'][0] ?? null) === ['remove' => 'customers/1234567890/campaigns/24383575678']);
        $this->assertNull(AdCampaign::find($campaign->id));
    }
}
