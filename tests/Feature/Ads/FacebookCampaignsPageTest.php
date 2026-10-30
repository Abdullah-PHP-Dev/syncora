<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AdServices\FacebookCampaignsPageService;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Facebook campaigns page: per-account data, isolation, delivery toggle, duplicate, sync and insights. */
class FacebookCampaignsPageTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.facebook.base_url', 'https://graph.facebook.com/v22.0/act_{accountId}');
        Settings::set('ads.facebook.client_secret', 'app-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
    }

    private function account(string $externalId, array $attributes = [], ?User $owner = null): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'user_id' => ($owner ?? $this->user)->id, 'platform' => 'facebook', 'platform_account_id' => $externalId, 'name' => 'Account ' . $externalId,
            'account_type' => 'ad_account', 'access_token' => 'token-' . $externalId, 'expires_at' => now()->addDays(30),
            'is_token_valid' => true, 'has_ads_permission' => true,
        ], $attributes));
    }

    private function campaign(SocialAccount $account, array $attributes = []): AdCampaign
    {
        return AdCampaign::create(array_merge([
            'user_id' => $account->user_id, 'social_account_id' => $account->id, 'platform' => 'facebook', 'name' => 'Campaign',
            'status' => 'paused', 'objective' => 'OUTCOME_LEADS', 'ad_campaign_id' => '120' . random_int(1000, 9999), 'daily_budget' => 50,
        ], $attributes));
    }

    public function test_the_page_shows_only_the_selected_ad_account_and_switching_changes_it(): void
    {
        $a = $this->account('111');
        $b = $this->account('222');
        $this->campaign($a, ['name' => 'Riyadh leads']);
        $this->campaign($b, ['name' => 'Jeddah traffic', 'status' => 'active', 'daily_budget' => 80]);

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->assertOk()->viewData('data');
        $this->assertSame($a->id, $data['account']['id']);
        $this->assertSame(['Riyadh leads'], array_column($data['campaigns'], 'name'));

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook', 'account' => $b->id]))->assertOk()->viewData('data');
        $this->assertSame($b->id, $data['account']['id']);
        $this->assertSame(['Jeddah traffic'], array_column($data['campaigns'], 'name'));
        $this->assertSame(['total' => 1, 'active' => 1, 'paused' => 0], array_intersect_key($data['summary'], array_flip(['total', 'active', 'paused'])));
        $this->assertEquals(80, $data['summary']['daily_budget_total']);
        $this->assertSame('…222', $data['accounts'][1]['masked_id']);
        $this->assertTrue($data['accounts'][1]['selected']);
        $this->assertTrue($data['can_manage']);
    }

    public function test_another_sellers_account_and_campaigns_are_out_of_reach(): void
    {
        $mine = $this->account('111');
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $theirs = $this->account('999', [], $other);
        $theirCampaign = $this->campaign($theirs, ['name' => 'Not yours']);
        $this->actingAs($this->user);
        Http::fake();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook', 'account' => $theirs->id]))->assertOk()->viewData('data');
        $this->assertSame($mine->id, $data['account']['id']);
        $this->assertNotContains('Not yours', array_column($data['campaigns'], 'name'));

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'facebook', 'id' => $theirCampaign->id]), ['status' => 'ACTIVE'])->assertNotFound();
        $this->postJson(route('admin.ads.campaigns.duplicate', ['platform' => 'facebook', 'id' => $theirCampaign->id]))->assertNotFound();
        $this->deleteJson(route('admin.ads.campaigns.destroy', ['platform' => 'facebook', 'campaign' => $theirCampaign->id]))->assertNotFound();
        Http::assertNothingSent();
        $this->assertSame('paused', $theirCampaign->fresh()->status);
    }

    public function test_the_delivery_toggle_pauses_and_activates_on_meta(): void
    {
        $account = $this->account('111');
        $campaign = $this->campaign($account, ['ad_campaign_id' => '12001', 'status' => 'paused']);
        Http::fake(['graph.facebook.com/v22.0/12001' => Http::response(['success' => true])]);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'facebook', 'id' => $campaign->id]), ['status' => 'ACTIVE'])
            ->assertOk()->assertJson(['success' => true, 'status' => 'active']);

        $this->assertSame('active', $campaign->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['status'] === 'ACTIVE' && $r['access_token'] === 'token-111' && $r['appsecret_proof'] === hash_hmac('sha256', 'token-111', 'app-secret'));
    }

    public function test_a_meta_error_is_reported_and_nothing_changes_locally(): void
    {
        $campaign = $this->campaign($this->account('111'), ['ad_campaign_id' => '12001', 'status' => 'active']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'Budget too low to deliver.']], 400)]);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'facebook', 'id' => $campaign->id]), ['status' => 'PAUSED'])
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'Budget too low to deliver.']);
        $this->assertSame('active', $campaign->fresh()->status);
    }

    public function test_read_only_roles_and_expired_tokens_cannot_change_campaigns(): void
    {
        $readOnly = $this->account('111', ['metadata' => ['user_tasks' => ['ANALYZE']]]);
        $expired = $this->account('222', ['is_token_valid' => false]);
        $a = $this->campaign($readOnly, ['ad_campaign_id' => '12001']);
        $b = $this->campaign($expired, ['ad_campaign_id' => '12002']);
        Http::fake();

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'facebook', 'id' => $a->id]), ['status' => 'ACTIVE'])->assertForbidden();
        $this->postJson(route('admin.ads.campaigns.duplicate', ['platform' => 'facebook', 'id' => $a->id]))->assertForbidden();
        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'facebook', 'id' => $b->id]), ['status' => 'ACTIVE'])->assertForbidden();
        Http::assertNothingSent();

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook', 'account' => $readOnly->id]))->viewData('data');
        $this->assertFalse($data['can_manage']);
        $this->assertSame('role_read_only', $data['manage_blocker']);
        $this->assertTrue($data['accounts'][0]['read_only']);
    }

    public function test_a_workspace_member_with_read_access_gets_no_actions(): void
    {
        $this->campaign($this->account('111'));

        $data = (new FacebookCampaignsPageService($this->user->id, memberCanWrite: false))->build();

        $this->assertFalse($data['can_manage']);
        $this->assertSame('member_read_only', $data['manage_blocker']);
        $this->assertCount(1, $data['campaigns']);
    }

    public function test_duplicate_copies_on_meta_paused_and_imports_the_copy(): void
    {
        $campaign = $this->campaign($this->account('111'), ['ad_campaign_id' => '12001']);
        Http::fake([
            'graph.facebook.com/v22.0/12001/copies' => Http::response(['copied_campaign_id' => '12099']),
            'graph.facebook.com/v22.0/act_111/campaigns*' => Http::response(['data' => [['id' => '12099', 'name' => 'Campaign - Copy', 'status' => 'PAUSED', 'objective' => 'OUTCOME_LEADS']]]),
            'graph.facebook.com/*' => Http::response(['data' => []]),
        ]);

        $this->postJson(route('admin.ads.campaigns.duplicate', ['platform' => 'facebook', 'id' => $campaign->id]))->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/12001/copies') && $r['status_option'] === 'PAUSED' && $r['deep_copy'] === 'true');
        $this->assertDatabaseHas('ad_campaigns', ['ad_campaign_id' => '12099', 'status' => 'paused', 'user_id' => $this->user->id]);
    }

    public function test_sync_imports_ad_sets_ads_creatives_and_the_account_role(): void
    {
        $account = $this->account('111');
        Http::fake([
            'graph.facebook.com/v22.0/act_111/campaigns*' => Http::response(['data' => [['id' => '12001', 'name' => 'Leads KSA', 'status' => 'ACTIVE', 'objective' => 'OUTCOME_LEADS', 'daily_budget' => '5000']]]),
            'graph.facebook.com/v22.0/act_111/adsets*' => Http::response(['data' => [['id' => '23001', 'name' => 'Riyadh 25-34', 'status' => 'ACTIVE', 'campaign_id' => '12001', 'daily_budget' => '2500', 'optimization_goal' => 'LEAD_GENERATION']]]),
            'graph.facebook.com/v22.0/act_111/ads*' => Http::response(['data' => [['id' => '34001', 'name' => 'Reel A', 'status' => 'PAUSED', 'adset_id' => '23001', 'campaign_id' => '12001', 'creative' => ['id' => '45001', 'title' => 'Book today', 'body' => 'عرض خاص', 'call_to_action_type' => 'SIGN_UP']]]]),
            'graph.facebook.com/v22.0/act_111?*' => Http::response(['name' => 'Account', 'currency' => 'SAR', 'account_status' => 1, 'business' => ['id' => '7', 'name' => 'Socialeaz'], 'user_tasks' => ['ANALYZE', 'ADVERTISE']]),
        ]);

        $this->postJson(route('admin.ads.campaigns.sync', ['platform' => 'facebook']))->assertOk()
            ->assertJsonPath('data.synced', 1)->assertJsonPath('data.ad_sets', 1)->assertJsonPath('data.ads', 1)->assertJsonPath('data.creatives', 1);

        $data = $this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->viewData('data');
        $this->assertSame(['campaigns' => 1, 'adsets' => 1, 'ads' => 1, 'creatives' => 1], $data['counts']);
        $this->assertSame(1, $data['campaigns'][0]['adsets_count']);
        $this->assertEquals(25, $data['adsets'][0]['budget']);
        $this->assertSame('Riyadh 25-34', $data['ads'][0]['adset']);
        $this->assertSame('عرض خاص', $data['creatives'][0]['message']);
        $this->assertSame('Socialeaz', $data['account']['business_name']);
        $this->assertSame('SAR', $data['account']['currency']);
        $this->assertFalse($data['account']['read_only']);
    }

    public function test_insights_count_results_by_objective_and_compare_with_the_previous_period(): void
    {
        $account = $this->account('111');
        $leads = $this->campaign($account, ['ad_campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS']);
        $calls = 0;
        Http::fake(['graph.facebook.com/v22.0/act_111/insights*' => function () use (&$calls) {
            $calls++;

            return Http::response(['data' => $calls === 1
                ? [['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '720.20', 'impressions' => '104980', 'reach' => '50000', 'clicks' => '2050', 'actions' => [['action_type' => 'lead', 'value' => '128'], ['action_type' => 'link_click', 'value' => '1900']]]]
                : [['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '600', 'impressions' => '90000', 'clicks' => '1800', 'actions' => [['action_type' => 'lead', 'value' => '100']]]]]);
        }]);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'facebook', 'days' => 14]))->assertOk()->json();

        $this->assertSame(14, $res['days']);
        $this->assertEquals(720.2, $res['totals']['current']['spend']);
        $this->assertEquals(128, $res['totals']['current']['results']);
        $this->assertSame('lead', $res['totals']['current']['result_type']);
        $this->assertEquals(5.63, $res['totals']['current']['cost_per_result']);
        $this->assertEquals(600, $res['totals']['previous']['spend']);
        $this->assertEquals(128, $res['campaigns'][$leads->id]['results']);
        $this->assertNotNull($account->fresh()->metadata['insights_synced_at'] ?? null);
    }

    public function test_insights_include_daily_series_conversion_path_and_breakdowns(): void
    {
        $account = $this->account('111');
        $this->campaign($account, ['ad_campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS']);
        $until = now()->subDay()->toDateString();
        Http::fake(['graph.facebook.com/v22.0/act_111/insights*' => function (HttpRequest $r) use ($until) {
            $lead = fn ($n, $extra = []) => array_merge([['action_type' => 'lead', 'value' => (string) $n]], $extra);
            return Http::response(['data' => match (true) {
                ($r['time_increment'] ?? null) == 1 => [['date_start' => $until, 'spend' => '90', 'impressions' => '12000', 'clicks' => '300', 'inline_link_clicks' => '250', 'actions' => $lead(20, [['action_type' => 'landing_page_view', 'value' => '210']])]],
                ($r['breakdowns'] ?? null) === 'publisher_platform,platform_position' => [
                    ['publisher_platform' => 'instagram', 'platform_position' => 'reels', 'spend' => '300', 'impressions' => '40000', 'actions' => $lead(58)],
                    ['publisher_platform' => 'facebook', 'platform_position' => 'feed', 'spend' => '412', 'impressions' => '60000', 'actions' => $lead(62)],
                ],
                ($r['breakdowns'] ?? null) === 'region' => [['region' => 'Riyadh', 'spend' => '500', 'reach' => '30000', 'actions' => $lead(98)]],
                ($r['breakdowns'] ?? null) === 'age' => [['age' => '25-34', 'spend' => '400', 'actions' => $lead(92)]],
                default => [['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '720', 'impressions' => '100000', 'reach' => '50000', 'clicks' => '2000', 'actions' => $lead(128)]],
            }]);
        }]);

        $details = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'facebook', 'days' => 7]))->assertOk()->json('details');

        $this->assertSame('lead', $details['result_type']);
        $this->assertCount(7, $details['daily']);
        $this->assertEquals(['date' => $until, 'spend' => 90, 'results' => 20, 'impressions' => 12000, 'link_clicks' => 250, 'landing_page_views' => 210, 'purchase_value' => 0, 'conversions' => 20], end($details['daily']));
        $this->assertEquals(0, $details['daily'][0]['spend']); // days without delivery are zero-filled, not dropped
        $this->assertEquals(['impressions' => 12000, 'link_clicks' => 250, 'landing_page_views' => 210, 'results' => 20], $details['funnel']);
        $this->assertSame(['facebook|feed', 'instagram|reels'], array_column($details['placements'], 'key')); // by spend
        $this->assertEquals(58, $details['placements'][1]['results']);
        $this->assertSame('Riyadh', $details['regions'][0]['key']);
        $this->assertEquals(92, $details['ages'][0]['results']);
    }

    public function test_a_rejected_breakdown_leaves_the_rest_of_the_insights_intact(): void
    {
        $this->campaign($this->account('111'), ['ad_campaign_id' => '12001']);
        Http::fake(['graph.facebook.com/v22.0/act_111/insights*' => function (HttpRequest $r) {
            if (($r['breakdowns'] ?? null) === 'region') {
                return Http::response(['error' => ['message' => 'Breakdown not supported']], 400);
            }

            return Http::response(['data' => [['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '10', 'impressions' => '100', 'clicks' => '5', 'actions' => [['action_type' => 'lead', 'value' => '1']]]]]);
        }]);

        $res = $this->getJson(route('admin.ads.campaigns.insights', ['platform' => 'facebook', 'days' => 7]))->assertOk()->json();

        $this->assertTrue($res['success']);
        $this->assertNull($res['details']['regions']);
        $this->assertNotNull($res['details']['ages']);
    }

    private function fakeMetaAndGemini(array|int $gemini): void
    {
        Http::fake([
            'graph.facebook.com/v22.0/act_111/insights*' => Http::response(['data' => [['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '720.20', 'impressions' => '104980', 'reach' => '52000', 'clicks' => '2050', 'actions' => [['action_type' => 'lead', 'value' => '128']]]]]),
            'generativelanguage.googleapis.com/*' => is_int($gemini)
                ? Http::response(['error' => ['message' => 'overloaded']], $gemini)
                : Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($gemini)]]]]]]),
        ]);
    }

    public function test_ai_insights_use_one_credit_and_only_the_accounts_own_numbers(): void
    {
        Settings::set('gemini_api_key', 'gemini-key');
        $this->campaign($this->account('111'), ['ad_campaign_id' => '12001', 'name' => 'Ramadan leads']);
        $this->fakeMetaAndGemini(['insights' => [
            ['title' => 'Ramadan leads at SAR 5.63 per lead', 'text' => '128 leads from SAR 720.20.', 'action' => 'compare_campaigns'],
            ['title' => 'Second', 'text' => 'Text', 'action' => 'not-an-action'],
            ['title' => 'Third', 'text' => 'Text', 'action' => 'review_budget'],
            ['title' => 'Fourth is dropped', 'text' => 'Text', 'action' => 'none'],
        ]]);
        $url = route('admin.ads.campaigns.ai-insights', ['platform' => 'facebook']);

        $this->getJson($url . '?days=14')->assertOk()->assertJsonPath('data', null);

        $data = $this->postJson(route('admin.ads.campaigns.ai-insights.generate', ['platform' => 'facebook']), ['days' => 14, 'spend' => 999999])
            ->assertOk()->json('data');

        $this->assertCount(3, $data['insights']);
        $this->assertSame('compare_campaigns', $data['insights'][0]['action']);
        $this->assertSame('none', $data['insights'][1]['action']); // unknown actions are neutralised
        $this->assertSame(1, \DB::table('package_usage')->where(['user_id' => $this->user->id, 'feature_key' => 'ai_credits'])->count());
        Http::assertSent(function (HttpRequest $r) {
            if (!str_contains($r->url(), 'generativelanguage')) {
                return false;
            }
            $prompt = $r['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, '720.2') && str_contains($prompt, 'Ramadan leads') && !str_contains($prompt, '999999') && str_contains($r->url(), 'key=gemini-key');
        });

        // Reading them again is free.
        $this->getJson($url . '?days=14')->assertOk()->assertJsonPath('data.insights.0.title', 'Ramadan leads at SAR 5.63 per lead');
        $this->assertSame(1, \DB::table('package_usage')->where(['user_id' => $this->user->id, 'feature_key' => 'ai_credits'])->count());
    }

    public function test_a_failed_ai_call_refunds_the_credit(): void
    {
        Settings::set('gemini_api_key', 'gemini-key');
        $this->campaign($this->account('111'), ['ad_campaign_id' => '12001']);
        $this->fakeMetaAndGemini(400);

        $this->postJson(route('admin.ads.campaigns.ai-insights.generate', ['platform' => 'facebook']), ['days' => 7])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, \DB::table('package_usage')->where(['user_id' => $this->user->id, 'feature_key' => 'ai_credits'])->count());
    }
}

