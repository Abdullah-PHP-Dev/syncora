<?php

namespace Tests\Feature\EmailMarketing;

use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\User;
use Tests\Feature\EmailMarketing\Concerns\CreatesEmailMarketingTables;
use Tests\TestCase;

/**
 * Scratch, throwaway test: dumps the raw HTML of the campaign create page
 * (with a real template selected) to a file for offline Playwright inspection.
 * Not meant to be kept in the suite.
 */
class ScratchPreviewDumpTest extends TestCase
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

    public function test_dump_create_page_html(): void
    {
        $user = User::factory()->create();

        $template = EmailTemplate::create([
            'user_id' => $user->id,
            'name' => 'Dump Template',
            'subject' => 'SocialEaz Promotion - Launch Offer',
            'body' => '<div style="padding:24px"><h1>Supercharge Your Social Media Marketing</h1><p>Managing multiple social accounts should not feel like a full-time job. SocialEaz lets you schedule posts, track performance, and grow your audience across every platform - all from one simple dashboard.</p><a href="#">Claim My 30% Discount</a></div>',
        ]);

        EmailList::create(['user_id' => $user->id, 'name' => 'Main List']);

        $response = $this->actingAs($user)->get('/email/campaigns/create?template=' . $template->id);

        $response->assertOk();

        file_put_contents('/private/tmp/claude-501/-Users-zaheerahmad-Sites-tawasa/4d125c36-de71-4ca8-90c4-7c7770486df2/scratchpad/campaign_create_dump.html', $response->getContent());

        $this->assertTrue(true);
    }
}
