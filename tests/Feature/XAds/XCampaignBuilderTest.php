<?php

namespace Tests\Feature\XAds;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdBuilderRun;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\Admin\AdMedia;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Services\AdServices\XCampaignBuilder;
use App\Support\Ads\XAdsApi;
use App\Support\Ads\XApiException;
use App\Support\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * XCampaignBuilder against a faked X Ads API: targeting -> criteria
 * mapping, the campaign -> line item -> targeting -> Tweet -> Promoted
 * Tweet -> activate order, idempotent replays and resuming after a failure.
 */
class XCampaignBuilderTest extends TestCase
{
    private const BASE = 'https://ads-api.x.com/12/';

    private SocialAccount $account;

    private bool $failPromoted = false;

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

        // OAuth 1.0a consumer pair (admin settings) without the settings table.
        $cache = new \ReflectionProperty(Settings::class, 'requestCache');
        $cache->setValue(null, ['ads.x.consumer_key' => 'ck', 'ads.x.consumer_secret' => 'cs']);

        $this->account = SocialAccount::create(['user_id' => 7, 'platform' => 'x', 'name' => 'Acme Ads', 'platform_account_id' => 'acc1', 'access_token' => 'tok', 'has_ads_permission' => true, 'metadata' => ['profile_id' => '555', 'screen_name' => 'acme']]);
        $this->fakeX();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Settings::class, 'requestCache'))->setValue(null, null);
        parent::tearDown();
    }

    private function builder(): XCampaignBuilder
    {
        return new XCampaignBuilder(new XAdsApi($this->account, self::BASE, 'secret'));
    }

    private function fakeX(): void
    {
        Http::fake(function (HttpRequest $request) {
            $path = substr(strtok($request->url(), '?'), strlen(self::BASE));
            $method = $request->method();

            return match (true) {
                $method === 'GET' && $path === 'accounts/acc1' => Http::response(['data' => ['name' => 'Acme Ads', 'timezone' => 'Asia/Riyadh', 'approval_status' => 'ACCEPTED']]),
                $path === 'accounts/acc1/funding_instruments' => Http::response(['data' => [['id' => 'fi1', 'description' => 'Visa 0650', 'currency' => 'SAR', 'entity_status' => 'ACTIVE', 'able_to_fund' => true]], 'next_cursor' => null]),
                $path === 'accounts/acc1/promotable_users' => Http::response(['data' => [['user_id' => '555', 'promotable_user_type' => 'FULL']], 'next_cursor' => null]),
                $path === 'line_items/placements' => Http::response(['data' => [['product_type' => 'PROMOTED_TWEETS', 'placements' => [['ALL_ON_TWITTER'], ['TWITTER_TIMELINE'], ['TWITTER_SEARCH'], ['TWITTER_TIMELINE', 'TWITTER_SEARCH']]]]]),
                $method === 'POST' && $path === 'accounts/acc1/campaigns' => Http::response(['data' => ['id' => 'camp1']]),
                $method === 'POST' && $path === 'accounts/acc1/line_items' => Http::response(['data' => ['id' => 'li1']]),
                $method === 'POST' && $path === 'batch/accounts/acc1/targeting_criteria' => Http::response(['data' => []]),
                $method === 'POST' && $path === 'accounts/acc1/tweet' => Http::response(['data' => ['id_str' => '9001', 'id' => 9001]]),
                $method === 'POST' && $path === 'accounts/acc1/promoted_tweets' => $this->failPromoted
                    ? Http::response(['errors' => [['code' => 'INVALID_PARAMETER', 'message' => 'Tweet is not promotable']]], 400)
                    : Http::response(['data' => [['id' => 'pt1', 'tweet_id' => '9001']]]),
                $method === 'PUT' => Http::response(['data' => ['entity_status' => 'ACTIVE']]),
                default => Http::response(['errors' => [['code' => 'NOT_FOUND', 'message' => "Unexpected {$method} {$path}"]]], 404),
            };
        });
    }

    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'objective' => 'WEBSITE_CLICKS',
            'status' => 'ACTIVE',
            'campaign' => ['mode' => 'new', 'name' => 'Launch', 'funding_instrument_id' => 'fi1', 'daily_budget' => 50, 'total_budget' => 500],
            'lineitem' => [
                'goal' => 'LINK_CLICKS', 'bid_strategy' => 'TARGET', 'bid_amount' => 1.25, 'pay_by' => null,
                'placement_mode' => 'custom', 'placements' => ['TWITTER_SEARCH', 'TWITTER_TIMELINE'],
                'start_time' => '2026-11-01T09:00', 'end_time' => '2026-11-30T23:00', 'standard_delivery' => true,
                'targeting' => [
                    'locations' => [['id' => 'loc-sa']], 'languages' => ['ar'], 'gender' => 'FEMALE', 'age' => 'AGE_25_TO_49',
                    'interests' => ['1001'], 'keywords' => [['value' => 'running shoes', 'type' => 'PHRASE_KEYWORD']],
                    'excluded_keywords' => [['value' => 'free', 'type' => 'BROAD_KEYWORD']], 'own_followers' => true,
                ],
            ],
            'ad' => ['as_user_id' => '555', 'source' => 'new', 'text' => 'New season is here', 'media_ids' => [], 'card' => 'none', 'url' => 'https://acme.test/shoes', 'conversation_settings' => 'EVERYONE'],
        ], $overrides);
    }

    public function test_targeting_maps_to_x_criteria(): void
    {
        $criteria = $this->builder()->criteria([
            'locations' => [['id' => 'loc1']], 'gender' => 'MALE', 'age' => 'AGE_OVER_18', 'languages' => ['en'],
            'keywords' => [['value' => 'shoes', 'type' => 'EXACT_KEYWORD']], 'excluded_keywords' => [['value' => 'cheap', 'type' => 'BROAD_KEYWORD']],
            'own_followers' => true, 'similar_followers' => ['42'], 'audiences' => ['ca1'], 'audience_lookalike' => true, 'excluded_audiences' => ['ca2'],
            'engagement' => ['type' => 'ENGAGEMENT', 'campaign_ids' => ['c9'], 'organic' => true],
        ]);
        $pairs = array_map(fn ($c) => "{$c['targeting_type']}:{$c['targeting_value']}:{$c['operator_type']}", $criteria);

        $this->assertSame([
            'LOCATION:loc1:EQ', 'LANGUAGE:en:EQ', 'GENDER:1:EQ', 'AGE:AGE_OVER_18:EQ', 'EXACT_KEYWORD:shoes:EQ', 'BROAD_KEYWORD:cheap:NE',
            'FOLLOWERS_OF_USER:555:EQ', 'SIMILAR_TO_FOLLOWERS_OF_USER:42:EQ', 'CUSTOM_AUDIENCE_EXPANDED:ca1:EQ', 'CUSTOM_AUDIENCE:ca2:NE',
            'ENGAGEMENT_TYPE:ENGAGEMENT:EQ', 'CAMPAIGN_ENGAGEMENT:c9:EQ', 'USER_ENGAGEMENT:555:EQ',
        ], $pairs);
    }

    public function test_submit_creates_everything_paused_then_activates_and_replays(): void
    {
        $result = $this->builder()->submit($this->input(), 'key-0000000000000001');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame('ACTIVE', $result['status']);
        $this->assertSame('li1', $result['line_item_id']);

        $calls = collect(Http::recorded())->map(fn ($pair) => $pair[0])->reject(fn ($r) => $r->method() === 'GET')->values();
        $paths = $calls->map(fn ($r) => $r->method() . ' ' . substr(strtok($r->url(), '?'), strlen(self::BASE)))->all();
        $this->assertSame([
            'POST accounts/acc1/campaigns', 'POST accounts/acc1/line_items', 'POST batch/accounts/acc1/targeting_criteria',
            'POST accounts/acc1/tweet', 'POST accounts/acc1/promoted_tweets', 'PUT accounts/acc1/line_items/li1', 'PUT accounts/acc1/campaigns/camp1',
        ], $paths);

        parse_str(parse_url($calls[0]->url(), PHP_URL_QUERY), $campaign);
        $this->assertSame(['PAUSED', '50000000', '500000000', 'LINE_ITEM'], [$campaign['entity_status'], $campaign['daily_budget_amount_local_micro'], $campaign['total_budget_amount_local_micro'], $campaign['budget_optimization']]);

        parse_str(parse_url($calls[1]->url(), PHP_URL_QUERY), $lineItem);
        $this->assertSame('PAUSED', $lineItem['entity_status']);
        $this->assertSame('TWITTER_SEARCH,TWITTER_TIMELINE', $lineItem['placements']);
        $this->assertSame('1250000', $lineItem['bid_amount_local_micro']);
        $this->assertSame('LINK_CLICK', $lineItem['pay_by'], 'TARGET + LINK_CLICKS must pay per link click');
        $this->assertSame('2026-11-01T06:00:00Z', $lineItem['start_time'], 'account time zone (Asia/Riyadh) -> UTC');
        $this->assertSame('PROMOTED_TWEETS', $lineItem['product_type']);
        $this->assertStringStartsWith('OAuth ', $calls[1]->header('Authorization')[0]);

        $batch = $calls[2]->data();
        $this->assertSame('Create', $batch[0]['operation_type']);
        $this->assertSame('li1', $batch[0]['params']['line_item_id']);
        $this->assertContains(['targeting_type' => 'BROAD_KEYWORD', 'targeting_value' => 'free', 'operator_type' => 'NE', 'line_item_id' => 'li1'], array_column($batch, 'params'));

        parse_str(parse_url($calls[3]->url(), PHP_URL_QUERY), $tweet);
        $this->assertSame(['555', 'true', 'New season is here https://acme.test/shoes'], [$tweet['as_user_id'], $tweet['nullcast'], $tweet['text']]);

        $this->assertSame(1, AdCampaign::where('platform', 'x')->where('ad_campaign_id', 'camp1')->count());
        $this->assertSame(1, AdAdGroup::where('ad_adgroup_id', 'li1')->count());
        $this->assertSame(1, Ad::where('ad_id', 'pt1')->count());

        // Same key again: replayed from the run log, nothing new sent to X.
        $before = count(Http::recorded());
        $again = $this->builder()->submit($this->input(), 'key-0000000000000001');
        $this->assertTrue($again['replayed']);
        $this->assertCount($before, Http::recorded());
    }

    public function test_retry_after_failure_resumes_without_duplicates(): void
    {
        $this->failPromoted = true;
        $first = $this->builder()->submit($this->input(['status' => 'PAUSED']), 'key-0000000000000002');

        $this->assertFalse($first['success']);
        $this->assertSame('promoted', $first['step']);
        $this->assertSame('Tweet is not promotable', $first['message']);
        $this->assertSame(['campaign', 'lineitem', 'creative'], array_column($first['created'], 'type'));

        $this->failPromoted = false;
        $second = $this->builder()->submit($this->input(['status' => 'PAUSED']), 'key-0000000000000002');
        $this->assertTrue($second['success']);
        $this->assertSame('PAUSED', $second['status']);

        $posts = collect(Http::recorded())->map(fn ($p) => $p[0])->filter(fn ($r) => $r->method() !== 'GET')
            ->map(fn ($r) => $r->method() . ' ' . substr(strtok($r->url(), '?'), strlen(self::BASE)))->countBy();
        $this->assertSame(1, $posts['POST accounts/acc1/campaigns']);
        $this->assertSame(1, $posts['POST accounts/acc1/line_items']);
        $this->assertSame(1, $posts['POST accounts/acc1/tweet']);
        $this->assertSame(2, $posts['POST accounts/acc1/promoted_tweets']);
        $this->assertArrayNotHasKey('PUT accounts/acc1/line_items/li1', $posts->all(), 'paused submissions are never activated');
    }

    public function test_rejects_invalid_placement_combination_before_calling_x(): void
    {
        $result = $this->builder()->submit($this->input(['lineitem' => ['placements' => ['TWITTER_SEARCH', 'TWITTER_PROFILE']]]), 'key-0000000000000003');

        $this->assertFalse($result['success']);
        $this->assertSame('validation', $result['step']);
        $this->assertFalse(collect(Http::recorded())->contains(fn ($p) => $p[0]->method() === 'POST'));
    }

    public function test_auth_errors_ask_to_reconnect(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['errors' => [['code' => 'UNAUTHORIZED_ACCESS', 'message' => 'This request is not properly authenticated']]], 401)]);

        try {
            (new XAdsApi($this->account, self::BASE, 'secret'))->get('accounts/acc1');
            $this->fail('Expected an XApiException');
        } catch (XApiException $e) {
            $this->assertTrue($e->isAuthError());
            $this->assertStringContainsString('This request is not properly authenticated', $e->getMessage());
        }
    }
}
