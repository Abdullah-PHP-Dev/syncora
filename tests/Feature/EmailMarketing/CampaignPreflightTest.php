<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Models\EmailMarketing\SenderIdentity;
use App\Models\User;
use App\Services\EmailMarketingServices\SendGridCampaignService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * The spec's explicit "cannot send until every pre-flight item passes"
 * requirement - each check is a real, individually-toggleable piece of
 * infrastructure state, not a single readiness boolean.
 */
class CampaignPreflightTest extends TestCase
{
    use CreatesEmailMarketingTables;

    private function baseCampaign(User $user, array $overrides = []): EmailCampaign
    {
        return EmailCampaign::create(array_merge([
            'user_id'    => $user->id,
            'name'       => 'Test Campaign',
            'subject'    => 'Hello',
            'from_name'  => 'Test',
            'from_email' => 'test@example.com',
            'body'       => '<p>hi</p>',
            'status'     => 'draft',
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmailMarketingTables();
    }

    public function test_preflight_fails_without_an_active_subaccount(): void
    {
        $user = User::factory()->create();
        $campaign = $this->baseCampaign($user);

        $result = app(SendGridCampaignService::class)->preflight($campaign);

        $this->assertFalse($result['ready']);
        $this->assertFalse(collect($result['checks'])->firstWhere('label', 'SendGrid subaccount active')['pass']);
    }

    public function test_preflight_fails_without_a_verified_sender_even_with_everything_else_ready(): void
    {
        $user = User::factory()->create();
        EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);

        $list = EmailList::create(['user_id' => $user->id, 'name' => 'List']);
        $subscriber = EmailSubscriber::create(['user_id' => $user->id, 'email' => 'a@example.com', 'status' => 'subscribed']);
        $list->subscribers()->attach($subscriber->id);

        $campaign = $this->baseCampaign($user, [
            'audience_type' => 'list',
            'audience_id'   => $list->id,
            'email_list_id' => $list->id,
        ]);

        $result = app(SendGridCampaignService::class)->preflight($campaign);

        $this->assertFalse($result['ready']);
        $this->assertFalse(collect($result['checks'])->firstWhere('label', 'Sender verified')['pass']);
        $this->assertTrue(collect($result['checks'])->firstWhere('label', 'Audience contains recipients')['pass']);
    }

    public function test_preflight_fails_with_an_empty_list_even_with_a_verified_sender(): void
    {
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        SenderIdentity::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id, 'sendgrid_sender_id' => '1',
            'nickname' => 'n', 'from_name' => 'F', 'from_email' => 'f@example.com',
            'address' => 'a', 'city' => 'c', 'country' => 'US', 'status' => 'verified',
        ]);
        $list = EmailList::create(['user_id' => $user->id, 'name' => 'Empty List']);

        $campaign = $this->baseCampaign($user, [
            'audience_type'      => 'list',
            'audience_id'        => $list->id,
            'email_list_id'      => $list->id,
            'sender_identity_id' => SenderIdentity::first()->id,
        ]);

        $result = app(SendGridCampaignService::class)->preflight($campaign);

        $this->assertFalse($result['ready']);
        $this->assertFalse(collect($result['checks'])->firstWhere('label', 'Audience contains recipients')['pass']);
    }

    public function test_preflight_fails_without_an_unsubscribe_group_even_with_everything_else_ready(): void
    {
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        SenderIdentity::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id, 'sendgrid_sender_id' => '1',
            'nickname' => 'n', 'from_name' => 'F', 'from_email' => 'f@example.com',
            'address' => 'a', 'city' => 'c', 'country' => 'US', 'status' => 'verified',
        ]);
        $list = EmailList::create(['user_id' => $user->id, 'name' => 'List']);
        $subscriber = EmailSubscriber::create(['user_id' => $user->id, 'email' => 'a@example.com', 'status' => 'subscribed']);
        $list->subscribers()->attach($subscriber->id);

        $campaign = $this->baseCampaign($user, [
            'audience_type'      => 'list',
            'audience_id'        => $list->id,
            'email_list_id'      => $list->id,
            'sender_identity_id' => SenderIdentity::first()->id,
            'suppression_group_id' => null,
        ]);

        $result = app(SendGridCampaignService::class)->preflight($campaign);

        $this->assertFalse($result['ready']);
        $this->assertFalse(collect($result['checks'])->firstWhere('label', 'Unsubscribe group selected')['pass']);

        $campaign->update(['suppression_group_id' => 4242]);
        $result = app(SendGridCampaignService::class)->preflight($campaign);

        $this->assertTrue($result['ready']);
        $this->assertTrue(collect($result['checks'])->firstWhere('label', 'Unsubscribe group selected')['pass']);
    }

    /**
     * Real bug found live this session: the schedule call used POST,
     * which SendGrid's API rejects with an empty-body 405 (surfaced to a
     * seller as an opaque "json could not be unmarshalled" error) -
     * PUT is the correct method. This pins the fix so a future edit
     * can't silently revert it back to POST.
     */
    public function test_sendorschedule_calls_the_schedule_endpoint_with_put_not_post(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        SenderIdentity::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id, 'sendgrid_sender_id' => '1',
            'nickname' => 'n', 'from_name' => 'F', 'from_email' => 'f@example.com',
            'address' => 'a', 'city' => 'c', 'country' => 'US', 'status' => 'verified',
        ]);
        $list = EmailList::create(['user_id' => $user->id, 'name' => 'List', 'sendgrid_list_id' => 'sg-list-1']);
        $subscriber = EmailSubscriber::create(['user_id' => $user->id, 'email' => 'a@example.com', 'status' => 'subscribed']);
        $list->subscribers()->attach($subscriber->id);

        $campaign = $this->baseCampaign($user, [
            'audience_type' => 'list', 'audience_id' => $list->id, 'email_list_id' => $list->id,
            'sender_identity_id' => SenderIdentity::first()->id, 'suppression_group_id' => 4242,
        ]);

        Http::fake([
            'api.sendgrid.com/v3/marketing/singlesends' => Http::response(['id' => 'ss-1'], 200),
            'api.sendgrid.com/v3/marketing/singlesends/ss-1/schedule' => Http::response(['status' => 'scheduled'], 200),
        ]);

        $result = app(SendGridCampaignService::class)->sendOrSchedule($campaign);

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/schedule') && $request->method() === 'PUT');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/schedule') && $request->method() === 'POST');
    }

    public function test_sendorschedule_refuses_to_call_sendgrid_when_preflight_fails(): void
    {
        Http::preventStrayRequests();
        // No fake responses registered at all - if sendOrSchedule() ever
        // actually calls SendGrid despite a failing preflight, this test
        // fails via Http::preventStrayRequests() rather than silently
        // passing.
        $user = User::factory()->create();
        $campaign = $this->baseCampaign($user);

        $result = app(SendGridCampaignService::class)->sendOrSchedule($campaign);

        $this->assertFalse($result['success']);
        $this->assertEquals('draft', $campaign->fresh()->status);
    }
}
