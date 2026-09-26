<?php

namespace Tests\Feature\AiCopilot;

use App\Models\Faq;
use App\Services\AiCopilotService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * ai-copilot:reembed-faqs - the backstop for a FAQ whose embedding failed
 * at save time or was published without a content change (see the
 * command's own docblock; findBestMatch()/findBestSystemMatch() both
 * silently skip any FAQ with a null embedding with no other error
 * surfaced anywhere).
 */
class ReembedFaqsTest extends TestCase
{
    use CreatesAiCopilotTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAiCopilotTables();
    }

    private function faq(array $overrides = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'What are your hours?',
            'answer'   => 'We are open 9 to 5.',
            'language' => 'en',
            'status'   => 'published',
        ], $overrides));
    }

    public function test_a_published_faq_with_a_null_embedding_gets_embedded(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $faq = $this->faq();
        $this->assertNull($faq->embedding);

        $this->artisan('ai-copilot:reembed-faqs')->assertSuccessful();

        $this->assertNotNull($faq->fresh()->embedding);
        $this->assertSame(AiCopilotService::EMBEDDING_MODEL, $faq->fresh()->embedding_model);
    }

    public function test_a_draft_faq_is_left_alone(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $faq = $this->faq(['status' => 'draft']);

        $this->artisan('ai-copilot:reembed-faqs')->assertSuccessful();

        $this->assertNull($faq->fresh()->embedding);
    }

    public function test_a_faq_with_a_stale_embedding_model_is_re_embedded_even_though_embedding_is_already_set(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [9, 9, 9]]])]);

        $faq = $this->faq();
        $faq->forceFill(['embedding' => [1, 0, 0], 'embedding_model' => 'old-model-v1'])->saveQuietly();

        $this->artisan('ai-copilot:reembed-faqs')->assertSuccessful();

        $faq->refresh();
        $this->assertSame(AiCopilotService::EMBEDDING_MODEL, $faq->embedding_model);
        $this->assertSame([9, 9, 9], $faq->embedding);
    }

    public function test_a_faq_that_is_already_current_is_never_re_embedded(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $faq = $this->faq();
        $faq->forceFill(['embedding' => [1, 0, 0], 'embedding_model' => AiCopilotService::EMBEDDING_MODEL])->saveQuietly();

        // preventStrayRequests() alone proves no Gemini call was made -
        // this run should find zero matching rows at all.
        $this->artisan('ai-copilot:reembed-faqs')->assertSuccessful();

        $this->assertSame([1, 0, 0], $faq->fresh()->embedding);
    }

    public function test_the_limit_option_caps_how_many_faqs_are_processed_in_one_run(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $faqs = collect(range(1, 5))->map(fn ($i) => $this->faq(['question' => "Question {$i}"]));

        $this->artisan('ai-copilot:reembed-faqs', ['--limit' => 2])->assertSuccessful();

        $embeddedCount = $faqs->filter(fn ($faq) => $faq->fresh()->embedding !== null)->count();
        $this->assertSame(2, $embeddedCount);
    }

    public function test_a_failed_embed_is_counted_and_left_for_the_next_run(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'rate limited'], 429)]);

        $faq = $this->faq();

        $this->artisan('ai-copilot:reembed-faqs')
            ->expectsOutputToContain('Embedded 0 of 1 FAQs (1 failed')
            ->assertSuccessful();

        $this->assertNull($faq->fresh()->embedding);
    }
}
