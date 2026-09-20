<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaign;
use App\Models\EmailMarketing\EmailEvent;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\SenderIdentity;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Guards the restyled campaign report page (Overview/Events/Statistics/
 * Recipients/Settings tabs, the ApexCharts engagement chart, and the
 * Duplicate action) against regressions - this is the kind of view that
 * silently 500s on a single undefined relation or model-method typo, and
 * nothing else in the suite renders it end to end.
 */
class CampaignShowPageTest extends TestCase
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

    public function test_a_sent_campaign_with_events_and_a_template_renders_the_report_page(): void
    {
        $user = User::factory()->create();

        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        $sender = SenderIdentity::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id, 'sendgrid_sender_id' => '1',
            'nickname' => 'n', 'from_name' => 'Socialeaz', 'from_email' => 'noreply@example.com',
            'address' => 'a', 'city' => 'c', 'country' => 'US', 'status' => 'verified',
        ]);

        $template = EmailTemplate::create(['user_id' => $user->id, 'name' => 'National Day Template', 'subject' => 'Hi', 'body' => '<p>hi</p>']);

        $list = EmailList::create(['user_id' => $user->id, 'name' => 'Main List']);
        $subscriber = EmailSubscriber::create(['user_id' => $user->id, 'email' => 'a@example.com', 'status' => 'subscribed']);
        $list->subscribers()->attach($subscriber->id);

        $campaign = EmailCampaign::create([
            'user_id' => $user->id, 'name' => 'National Day Campaign', 'subject' => 'Celebrate!',
            'preheader' => 'Special offers inside', 'from_name' => 'Socialeaz', 'from_email' => 'noreply@example.com',
            'body' => '<p>Happy National Day</p>', 'status' => 'sent', 'sent_at' => now()->subDay(),
            'audience_type' => 'list', 'audience_id' => $list->id, 'email_list_id' => $list->id,
            'email_template_id' => $template->id, 'sender_identity_id' => $sender->id,
            'campaign_type' => 'one_time', 'suppression_group_id' => 4242,
            'total_recipients' => 100, 'sent_count' => 100, 'delivered_count' => 95,
            'opened_count' => 40, 'clicked_count' => 15, 'bounced_count' => 3, 'unsubscribed_count' => 1,
            'complained_count' => 0,
        ]);

        EmailEvent::create([
            'user_id' => $user->id, 'email_campaign_id' => $campaign->id, 'sg_event_id' => 'evt-1',
            'event_type' => 'delivered', 'recipient_email' => 'a@example.com', 'event_at' => now()->subDay(),
        ]);
        EmailEvent::create([
            'user_id' => $user->id, 'email_campaign_id' => $campaign->id, 'sg_event_id' => 'evt-2',
            'event_type' => 'open', 'recipient_email' => 'a@example.com', 'event_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user)->get('/email/campaigns/' . $campaign->id);

        $response->assertOk();
        $response->assertSee('National Day Campaign');
        $response->assertSee('Special offers inside');
        $response->assertSee('National Day Template');
        $response->assertSee('a@example.com');
        $response->assertSee('Duplicate');
    }

    public function test_duplicate_creates_a_fresh_draft_with_delivery_state_reset(): void
    {
        $user = User::factory()->create();
        $list = EmailList::create(['user_id' => $user->id, 'name' => 'List']);

        $campaign = EmailCampaign::create([
            'user_id' => $user->id, 'name' => 'Original', 'subject' => 'S', 'from_name' => 'F', 'from_email' => 'f@example.com',
            'body' => '<p>b</p>', 'status' => 'sent', 'sent_at' => now(), 'sendgrid_single_send_id' => 'ss-1',
            'audience_type' => 'list', 'audience_id' => $list->id, 'email_list_id' => $list->id,
            'total_recipients' => 10, 'sent_count' => 10, 'delivered_count' => 9, 'opened_count' => 4,
        ]);

        $response = $this->actingAs($user)->post('/email/campaigns/' . $campaign->id . '/duplicate');

        $response->assertRedirect();
        $copy = EmailCampaign::where('name', 'Original (Copy)')->firstOrFail();
        $this->assertEquals('draft', $copy->status);
        $this->assertNull($copy->sendgrid_single_send_id);
        $this->assertNull($copy->sent_at);
        $this->assertEquals(0, $copy->sent_count);
        $this->assertEquals(0, $copy->delivered_count);
        $this->assertEquals(0, $copy->opened_count);
        $this->assertEquals($list->id, $copy->audience_id);
    }
}
