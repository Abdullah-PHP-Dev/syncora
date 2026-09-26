<?php

namespace Tests\Feature\AiCopilot;

use App\Models\Faq;
use App\Models\KnowledgeGapReport;
use App\Models\User;
use App\Services\AiCopilotService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * QA audit finding: Purifier sanitization was added by inspection to
 * BusinessProfileController::update() and KnowledgeGapController::
 * convertToFaq() (both write into a Faq.answer field, same stored-XSS
 * surface FaqController/KnowledgeBaseController already guard), but
 * neither had an automated test proving it actually runs - "the code
 * calls Purifier::clean()" is not the same as verified. These close that
 * evidence gap.
 */
class SecuritySanitizationTest extends TestCase
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

    private function seller(): User
    {
        $seller = User::factory()->create();
        $seller->assignRole(Role::findOrCreate('seller', 'web'));

        return $seller;
    }

    public function test_business_profile_policy_fields_are_sanitized_on_save(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();

        $response = $this->actingAs($seller)->putJson('/ai-copilot/business-profile', [
            'delivery_policy' => '<p>Safe text</p><script>alert(1)</script>',
            'return_policy'   => '<img src=x onerror="alert(2)">Also safe',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('business_profiles', ['user_id' => $seller->id]);
        $profile = $seller->businessProfile()->first();
        $this->assertStringNotContainsString('<script', $profile->delivery_policy);
        $this->assertStringNotContainsString('onerror', $profile->return_policy);
        $this->assertStringContainsString('Safe text', $profile->delivery_policy);
    }

    /**
     * QA finding: business_name/phone/address were never sanitized at all
     * (only delivery_policy/return_policy were) despite flowing into the
     * exact same v-html-rendered Faq.answer via
     * BusinessProfileFaqSyncService - a self-XSS gap (a seller attacking
     * their own Help Center view via their own profile fields).
     */
    public function test_business_profile_single_line_fields_are_sanitized_on_save(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();

        $response = $this->actingAs($seller)->putJson('/ai-copilot/business-profile', [
            'business_name' => '<script>alert(1)</script>Acme Co',
            'phone'         => '<b>555-0100</b>',
            'address'       => '123 Main St<script>alert(3)</script>',
        ]);

        $response->assertOk();

        $profile = $seller->businessProfile()->first();
        $this->assertStringNotContainsString('<script', $profile->business_name);
        $this->assertStringNotContainsString('<b>', $profile->phone);
        $this->assertStringNotContainsString('<script', $profile->address);
        $this->assertStringContainsString('Acme Co', $profile->business_name);
        $this->assertStringContainsString('555-0100', $profile->phone);

        // Plain strip_tags(), not Purifier's paragraph-wrapping 'default'
        // profile - these fields are re-populated verbatim into this same
        // edit form's inputs, which would otherwise show literal
        // "<p>...</p>" the next time the seller opens the settings page.
        $this->assertStringNotContainsString('<p>', $profile->business_name);
    }

    public function test_converting_a_knowledge_gap_sanitizes_the_answer(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();
        $gap = KnowledgeGapReport::create([
            'user_id' => $seller->id, 'question' => 'Q', 'question_hash' => KnowledgeGapReport::normalize('Q'),
            'occurrence_count' => 1, 'last_occurred_at' => now(), 'status' => 'new',
        ]);

        $response = $this->actingAs($seller)->postJson(
            route('admin.ai-copilot.knowledge-gaps.convert-to-faq', $gap),
            ['answer' => '<script>alert(1)</script>Safe answer text']
        );

        $response->assertOk();
        $faq = Faq::findOrFail($response->json('faq.id'));
        $this->assertStringNotContainsString('<script', $faq->answer);
        $this->assertStringContainsString('Safe answer text', $faq->answer);
    }

    /**
     * QA audit finding: a failed Gemini call logged the raw response body
     * unredacted - the request URL carries the API key as a query
     * parameter, and if Google's error payload ever echoes the requested
     * URL back, the key would land in plaintext log files. Fixed by
     * redacting any key=... pattern before logging.
     */
    public function test_a_failed_gemini_call_never_logs_the_api_key(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['message' => 'Bad request to https://generativelanguage.googleapis.com/v1beta/models/x?key=super-secret-real-key']],
            400
        )]);
        Log::spy();

        $faq = Faq::create(['question' => 'Q', 'answer' => 'A', 'language' => 'en', 'status' => 'published']);
        app(AiCopilotService::class)->embedFaq($faq);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return !str_contains($context['body'] ?? '', 'super-secret-real-key')
                && str_contains($context['body'] ?? '', 'key=[redacted]');
        })->once();
    }
}
