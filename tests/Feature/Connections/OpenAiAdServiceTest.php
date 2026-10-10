<?php

namespace Tests\Feature\Connections;

use App\Models\Admin\Ad;
use App\Models\Admin\AdAdGroup;
use App\Models\Admin\AdCampaign;
use App\Models\Admin\AdCreative;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\OpenAiAdService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ChatGPT Ads campaigns: create (campaign > ad group > ad), manage, sync, reporting. */
class OpenAiAdServiceTest extends TestCase
{
    use CreatesConnectionTables;

    private const API = 'api.ads.openai.com/v1';

    private User $user;

    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'openai', 'platform_account_id' => 'adacct_1', 'name' => 'Acme', 'access_token' => 'sk-ads-key', 'has_ads_permission' => true, 'account_type' => 'ad_account']);
        Storage::fake('r2');
    }

    private function createAdTables(): void
    {
        $common = function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->string('platform')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('ad_campaign_id_local')->nullable();
            $table->timestamps();
        };
        Schema::create('ad_campaigns', function ($table) use ($common) {
            $common($table);
            $table->string('ad_campaign_id')->nullable();
            $table->string('budget_mode')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('bidding_strategy')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
        });
        Schema::create('ad_adgroups', function ($table) use ($common) {
            $common($table);
            $table->unsignedBigInteger('ad_campaign_id');
            $table->string('ad_adgroup_id')->nullable();
            $table->string('billing_event')->nullable();
            $table->string('bid_price')->nullable();
        });
        Schema::create('ad_creatives', function ($table) use ($common) {
            $common($table);
            $table->unsignedBigInteger('ad_campaign_id');
            $table->unsignedBigInteger('ad_adgroup_id');
            $table->string('headline')->nullable();
            $table->string('message')->nullable();
            $table->string('url')->nullable();
            $table->string('type')->nullable();
        });
        Schema::create('ads', function ($table) use ($common) {
            $common($table);
            $table->unsignedBigInteger('ad_campaign_id');
            $table->unsignedBigInteger('ad_adgroup_id');
            $table->unsignedBigInteger('ad_creative_id');
            $table->string('ad_id')->nullable();
            $table->string('type')->nullable();
            $table->string('text')->nullable();
        });
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Spring sale',
            'budget' => '500',
            'start_time' => '2026-11-01',
            'end_time' => null,
            'bidding_type' => 'clicks',
            'billing_event' => 'click',
            'max_bid' => '1.5',
            'title' => 'Fresh bread by 8am',
            'body' => 'Order tonight, wake up to sourdough.',
            'target_link' => 'https://bakery.example/offer',
            'image' => UploadedFile::fake()->image('ad.png'),
            'launch' => 'active',
        ];
    }

    private function localCampaign(): AdCampaign
    {
        return AdCampaign::create(['platform' => 'openai', 'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'ad_campaign_id' => 'cmp_1', 'name' => 'Spring', 'status' => 'ACTIVE']);
    }

    public function test_store_creates_campaign_ad_group_and_chat_card_ad(): void
    {
        Http::fake([
            self::API . '/upload' => Http::response(['file_id' => 'file_9']),
            self::API . '/campaigns' => Http::response(['id' => 'cmp_1', 'status' => 'active']),
            self::API . '/ad_groups' => Http::response(['id' => 'adg_1', 'name' => 'Spring sale - Ad group']),
            self::API . '/ads' => Http::response(['id' => 'ad_1', 'review_status' => 'in_review']),
        ]);

        $data = (new OpenAiAdService)->store('openai', $this->form())->getData(true);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('in review', $data['message']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/campaigns')
            && $r['budget'] === ['lifetime_spend_limit_micros' => 500_000_000]
            && $r['status'] === 'active' && $r['bidding_type'] === 'clicks' && is_int($r['start_time'])
            && $r->hasHeader('Authorization', 'Bearer sk-ads-key'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/ad_groups')
            && $r['campaign_id'] === 'cmp_1' && $r['bidding_config'] === ['billing_event_type' => 'click', 'max_bid_micros' => 1_500_000]);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/ads') && $r['ad_group_id'] === 'adg_1'
            && $r['creative'] === ['type' => 'chat_card', 'title' => 'Fresh bread by 8am', 'body' => 'Order tonight, wake up to sourdough.', 'target_url' => 'https://bakery.example/offer', 'file_id' => 'file_9']);

        $campaign = AdCampaign::sole();
        $this->assertSame([$campaign->id, 'cmp_1', 'ACTIVE', 'openai'], [$data['campaign_id'], $campaign->ad_campaign_id, $campaign->status, $campaign->platform]);
        $this->assertSame('adg_1', AdAdGroup::sole()->ad_adgroup_id);
        $this->assertSame('https://bakery.example/offer', AdCreative::sole()->url);
        $this->assertSame(['ad_1', 'IN_REVIEW'], [Ad::sole()->ad_id, Ad::sole()->status]);
        $this->assertCount(1, Storage::disk('r2')->allFiles('uploads/openai-ads'));
    }

    public function test_a_failed_ad_archives_the_half_built_campaign(): void
    {
        Http::fake([
            self::API . '/upload' => Http::response(['file_id' => 'file_9']),
            self::API . '/campaigns' => Http::response(['id' => 'cmp_1', 'status' => 'active']),
            self::API . '/ad_groups' => Http::response(['id' => 'adg_1']),
            self::API . '/ads' => Http::response(['error' => ['message' => 'Landing page not allowed']], 400),
            self::API . '/campaigns/cmp_1/archive' => Http::response(['id' => 'cmp_1', 'status' => 'archived']),
        ]);

        $response = (new OpenAiAdService)->store('openai', $this->form());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Landing page not allowed', $response->getData(true)['message']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/campaigns/cmp_1/archive'));
        $this->assertSame(0, AdCampaign::count());
    }

    public function test_store_needs_a_connected_account(): void
    {
        $this->account->delete();
        Http::fake();

        $response = (new OpenAiAdService)->store('openai', $this->form());

        $this->assertStringContainsString('Connect a ChatGPT Ads account', $response->getData(true)['message']);
        Http::assertNothingSent();
    }

    public function test_pause_and_activate(): void
    {
        $campaign = $this->localCampaign();
        Http::fake(['*' => Http::response(['id' => 'cmp_1'])]);
        request()->headers->set('Accept', 'application/json');

        $this->assertTrue((new OpenAiAdService)->updateStatus($campaign->id, 'PAUSED')->getData(true)['success']);
        $this->assertSame('PAUSED', $campaign->fresh()->status);
        (new OpenAiAdService)->updateStatus($campaign->id, 'ACTIVE');
        $this->assertSame('ACTIVE', $campaign->fresh()->status);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/campaigns/cmp_1/pause'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/campaigns/cmp_1/activate'));
    }

    public function test_another_sellers_campaign_is_not_found(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $campaign = AdCampaign::create(['platform' => 'openai', 'user_id' => $other->id, 'ad_campaign_id' => 'cmp_x', 'status' => 'ACTIVE']);
        Http::fake();

        $this->assertSame(404, (new OpenAiAdService)->updateStatus($campaign->id, 'PAUSED')->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_delete_archives_on_openai_and_removes_local_rows(): void
    {
        $campaign = $this->localCampaign();
        $group = AdAdGroup::create(['ad_campaign_id' => $campaign->id, 'ad_adgroup_id' => 'adg_1', 'platform' => 'openai']);
        Ad::create(['ad_campaign_id' => $campaign->id, 'ad_adgroup_id' => $group->id, 'ad_creative_id' => 0, 'ad_id' => 'ad_1']);
        Http::fake(['*' => Http::response(['id' => 'cmp_1', 'status' => 'archived'])]);

        $response = (new OpenAiAdService)->destroy('openai', $campaign->id);

        $this->assertTrue($response->isRedirect());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/campaigns/cmp_1/archive'));
        $this->assertSame([0, 0, 0], [AdCampaign::count(), AdAdGroup::count(), Ad::count()]);
    }

    public function test_sync_imports_and_updates_campaigns(): void
    {
        $this->localCampaign();
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 'cmp_1', 'name' => 'Spring renamed', 'status' => 'paused', 'budget' => ['lifetime_spend_limit_micros' => 250_000_000]],
            ['id' => 'cmp_2', 'name' => 'Made in ads.openai.com', 'status' => 'active', 'bidding_type' => 'impressions'],
        ], 'has_more' => false])]);

        $result = (new OpenAiAdService)->syncCampaigns();

        $this->assertSame([true, 2, 1, 1], [$result['success'], $result['synced'], $result['created'], $result['updated']]);
        $this->assertSame(['Spring renamed', 'PAUSED', 250.0], [AdCampaign::where('ad_campaign_id', 'cmp_1')->value('name'), AdCampaign::where('ad_campaign_id', 'cmp_1')->value('status'), (float) AdCampaign::where('ad_campaign_id', 'cmp_1')->value('budget')]);
        $this->assertSame('ACTIVE', AdCampaign::where('ad_campaign_id', 'cmp_2')->value('status'));
    }

    public function test_insights_totals_and_per_campaign_rows(): void
    {
        $campaign = $this->localCampaign();
        Http::fake(['*' => Http::response(['data' => [
            ['campaign_id' => 'cmp_1', 'impressions' => 1000, 'clicks' => 40, 'spend' => 20.0, 'ctr' => 0.04, 'cpc' => 0.5],
            ['campaign_id' => 'cmp_other', 'impressions' => 1000, 'clicks' => 10, 'spend' => 5.0],
        ]])]);

        $result = (new OpenAiAdService)->insights();

        $this->assertSame(['impressions' => 2000, 'clicks' => 50, 'spend' => 25.0, 'ctr' => 0.025, 'cpc' => 0.5], $result['totals']);
        $this->assertSame(40, $result['campaigns'][(string) $campaign->id]['clicks']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/ad_account/insights?aggregation_level=campaign')
            && str_contains(urldecode($r->url()), 'fields[]=spend'));
    }
}
