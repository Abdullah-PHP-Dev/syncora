<?php

namespace Tests\Feature\AiCopilot;

use App\Models\Faq;
use App\Models\User;
use App\Services\AiCopilotService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * Phase 3's actual root-cause fix: System FAQs were never embedded at all
 * (FaqController never called AiCopilotService::embedFaq(), confirmed via
 * grep before this phase), so Level-1 semantic search was impossible, not
 * just unused. These tests pin that fix, the Level 1/Level 2 isolation
 * (findBestSystemMatch() must never surface a seller's own Knowledge Base
 * FAQ), the no-hallucination floor, and the "Ask AI" endpoint's shape.
 */
class HelpCenterAiSearchTest extends TestCase
{
    use CreatesAiCopilotTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAiCopilotTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        return $admin;
    }

    private function seller(): User
    {
        $seller = User::factory()->create();
        $seller->assignRole(Role::findOrCreate('seller', 'web'));

        return $seller;
    }

    public function test_saving_a_system_faq_via_the_admin_controller_embeds_it(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/faqs', [
            'question' => 'How do I connect Instagram?',
            'answer'   => 'Go to Connections and click Instagram.',
            'language' => 'en',
            'status'   => 'published',
        ]);

        $response->assertOk();
        $faq = Faq::findOrFail($response->json('faq.id'));
        $this->assertNotNull($faq->embedding);
    }

    public function test_find_best_system_match_only_ever_returns_system_faqs_never_a_sellers_own(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();
        $copilot = app(AiCopilotService::class);

        // A seller's own Knowledge Base FAQ, worded identically to a
        // System FAQ - if Level 1/Level 2 scoping were wrong, this one
        // could win instead.
        $sellerFaq = Faq::create([
            'user_id' => $seller->id, 'question' => 'How do I connect Instagram?',
            'answer' => 'Ask your seller-side support.', 'language' => 'en', 'status' => 'published',
        ]);
        $copilot->embedFaq($sellerFaq);

        $systemFaq = Faq::create([
            'user_id' => null, 'question' => 'How do I connect Instagram?',
            'answer' => 'Go to Connections and click Instagram.', 'language' => 'en', 'status' => 'published',
        ]);
        $copilot->embedFaq($systemFaq);

        $result = $copilot->findBestSystemMatch('How do I connect Instagram?');

        $this->assertSame($systemFaq->id, $result['faq']?->id);
        $this->assertSame('Go to Connections and click Instagram.', $result['suggested_reply']);
    }

    public function test_a_low_confidence_system_search_never_fabricates_an_answer(): void
    {
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['embedding' => ['values' => [1, 0, 0]]])
            ->push(['embedding' => ['values' => [0, 1, 0]]]);

        $copilot = app(AiCopilotService::class);

        $faq = Faq::create([
            'user_id' => null, 'question' => 'How do I connect Instagram?',
            'answer' => 'Go to Connections and click Instagram.', 'language' => 'en', 'status' => 'published',
        ]);
        $copilot->embedFaq($faq);

        $result = $copilot->findBestSystemMatch('completely unrelated question');

        $this->assertSame('no_match', $result['status']);
        $this->assertNull($result['suggested_reply']);
    }

    public function test_ask_ai_endpoint_returns_a_confident_answer_for_a_matching_question(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();
        $faq = Faq::create([
            'user_id' => null, 'question' => 'How do I connect Instagram?',
            'answer' => 'Go to Connections and click Instagram.', 'language' => 'en', 'status' => 'published',
        ]);
        app(AiCopilotService::class)->embedFaq($faq);

        $response = $this->actingAs($seller)->postJson('/help-center/ask-ai', ['question' => 'How do I connect Instagram?']);

        $response->assertOk();
        $response->assertJson([
            'success'          => true,
            'status'           => 'suggested',
            'answer'           => 'Go to Connections and click Instagram.',
            'matched_question' => 'How do I connect Instagram?',
        ]);
    }

    public function test_ask_ai_endpoint_returns_no_match_without_any_system_faqs(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $seller = $this->seller();

        $response = $this->actingAs($seller)->postJson('/help-center/ask-ai', ['question' => 'anything at all']);

        $response->assertOk();
        $response->assertJson(['success' => true, 'status' => 'no_match', 'answer' => null]);
    }
}
