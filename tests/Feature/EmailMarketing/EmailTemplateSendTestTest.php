<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\SenderIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Send Test Email uses the current in-editor subject/body from the
 * request, not what's saved in the DB (so a seller can test before ever
 * clicking Save), and is gated on the exact same "active subaccount + a
 * verified sender" pattern already established in
 * EmailCampaignController::fetchSuppressionGroups() and
 * SendGridCampaignService::preflight().
 */
class EmailTemplateSendTestTest extends TestCase
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

    private function template(User $user): EmailTemplate
    {
        return EmailTemplate::create([
            'user_id' => $user->id, 'name' => 'T', 'subject' => 'Hello', 'body' => '<p>hi {{first_name}}</p>', 'status' => 'draft',
        ]);
    }

    public function test_fails_without_an_active_subaccount(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $template = $this->template($user);

        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/send-test', [
            'recipient_email' => 'me@example.com', 'subject' => 'Hello', 'body' => '<p>hi</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_fails_without_a_verified_sender(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        $template = $this->template($user);

        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/send-test', [
            'recipient_email' => 'me@example.com', 'subject' => 'Hello', 'body' => '<p>hi</p>',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_succeeds_and_sends_the_current_in_editor_content_with_sample_personalization(): void
    {
        $user = User::factory()->create();
        $subaccount = EmailSubaccount::create(['user_id' => $user->id, 'status' => 'active', 'api_key' => ['key' => 'SG.test']]);
        SenderIdentity::create([
            'user_id' => $user->id, 'email_subaccount_id' => $subaccount->id, 'sendgrid_sender_id' => '1',
            'nickname' => 'n', 'from_name' => 'Socialeaz', 'from_email' => 'noreply@example.com',
            'address' => 'a', 'city' => 'c', 'country' => 'US', 'status' => 'verified',
        ]);
        $template = $this->template($user);

        Http::fake(['api.sendgrid.com/v3/mail/send' => Http::response([], 202)]);

        $response = $this->actingAs($user)->post('/email/templates/' . $template->id . '/send-test', [
            'recipient_email' => 'me@example.com',
            'subject'         => 'Hello {{first_name}}',
            'body'            => '<p>hi {{first_name}}, this is {{email}}</p>',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
                && $request['personalizations'][0]['to'][0]['email'] === 'me@example.com'
                && $request['from']['email'] === 'noreply@example.com'
                && $request['subject'] === 'Hello John'
                && str_contains($request['content'][0]['value'], 'hi John, this is me@example.com');
        });
    }
}
