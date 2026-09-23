<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * "Second seller gets 403/can't see the first's data" shape mirrored from
 * TeamWorkspaceTest - every Email Marketing controller resolves its
 * records via where('user_id', Auth::id()) plus an abort_unless ownership
 * check on route-model-bound records (SocialAccount's own established
 * pattern), never trusting a user_id from the request.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesEmailMarketingTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \App\Http\Middleware\EnsureSeller::class,
            \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    }

    public function test_a_seller_cannot_see_or_delete_another_sellers_list(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $list = EmailList::create(['user_id' => $owner->id, 'name' => 'Owner-only list']);

        $this->actingAs($intruder)
            ->get('/email/lists')
            ->assertOk()
            ->assertDontSee('Owner-only list');

        $this->actingAs($intruder)
            ->delete('/email/lists/' . $list->id)
            ->assertForbidden();

        $this->assertDatabaseHas('email_lists', ['id' => $list->id]);
    }

    public function test_a_seller_cannot_view_or_send_another_sellers_campaign(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $campaign = EmailCampaign::create([
            'user_id' => $owner->id,
            'name'    => 'Owner campaign',
            'subject' => 'Hi',
            'from_name' => 'Owner',
            'from_email' => 'owner@example.com',
            'body' => '<p>hi</p>',
            'status' => 'draft',
        ]);

        $this->actingAs($intruder)->get('/email/campaigns/' . $campaign->id)->assertForbidden();
        $this->actingAs($intruder)->post('/email/campaigns/' . $campaign->id . '/send')->assertForbidden();

        $this->assertDatabaseHas('email_campaigns', ['id' => $campaign->id, 'status' => 'draft']);
    }

    public function test_each_sellers_subaccount_lookup_never_crosses_users(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        EmailSubaccount::create(['user_id' => $ownerA->id, 'status' => 'active', 'api_key' => ['key' => 'SG.a']]);
        EmailSubaccount::create(['user_id' => $ownerB->id, 'status' => 'active', 'api_key' => ['key' => 'SG.b']]);

        $found = EmailSubaccount::where('user_id', $ownerA->id)->first();

        $this->assertEquals('SG.a', $found->apiKeyValue());
        $this->assertNotEquals('SG.b', $found->apiKeyValue());
    }
}
