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
