<?php

namespace Tests\Feature\AiCopilot;

use App\Models\BusinessProfile;
use App\Models\Faq;
use App\Models\User;
use App\Services\AiCopilotService;
use App\Services\BusinessProfileFaqSyncService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * BusinessProfileFaqSyncService's whole reason to exist (see its
 * docblock): a Business Profile never gets read directly by
 * AiCopilotService - it's projected into ordinary Faq rows so
 * findBestMatch() needs zero special-cased retrieval logic for structured
 * data. These tests confirm that projection is idempotent (updates/
 * removes the right row, doesn't duplicate), that the synced row actually
 * participates in real ranking, and that it never crosses tenants.
 */
class BusinessProfileFaqSyncTest extends TestCase
{
    use CreatesAiCopilotTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAiCopilotTables();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);
    }

    public function test_saving_a_profile_creates_one_faq_row_per_populated_field(): void
    {
        $seller = User::factory()->create();

        $profile = BusinessProfile::create([
            'user_id' => $seller->id,
            'business_hours' => ['mon' => '9:00-18:00', 'closed' => ['sun']],
            'phone' => '+1 555 0100',
            'delivery_policy' => null,
        ]);

        app(BusinessProfileFaqSyncService::class)->sync($profile);

        $this->assertSame(2, Faq::where('user_id', $seller->id)->where('source', 'business_profile')->count());
        $hoursFaq = Faq::where('user_id', $seller->id)->where('source_key', 'business_hours')->firstOrFail();
        $this->assertStringContainsString('Monday: 9:00-18:00', $hoursFaq->answer);
        $this->assertStringContainsString('Sunday: Closed', $hoursFaq->answer);
        // <br>, not a literal newline - see formatHours()'s own comment:
        // every other Faq::answer in this app is real HTML, and the Help
        // Center renders faq.answer directly via v-html with no further
        // newline conversion.
        $this->assertStringContainsString('Monday: 9:00-18:00<br>', $hoursFaq->answer);
        $this->assertStringNotContainsString("\n", $hoursFaq->answer);
        $this->assertSame('published', $hoursFaq->status);
        $this->assertNotNull($hoursFaq->fresh()->embedding);
    }

    public function test_clearing_a_field_removes_its_synced_faq_row_without_touching_others(): void
    {
        $seller = User::factory()->create();
        $sync = app(BusinessProfileFaqSyncService::class);

        $profile = BusinessProfile::create(['user_id' => $seller->id, 'phone' => '+1 555 0100', 'address' => '1 Main St']);
        $sync->sync($profile);
        $this->assertSame(2, Faq::where('user_id', $seller->id)->where('source', 'business_profile')->count());

        $profile->update(['phone' => null]);
        $sync->sync($profile);

        $this->assertSame(1, Faq::where('user_id', $seller->id)->where('source', 'business_profile')->count());
        $this->assertDatabaseMissing('faqs', ['user_id' => $seller->id, 'source_key' => 'phone']);
        $this->assertDatabaseHas('faqs', ['user_id' => $seller->id, 'source_key' => 'address']);
    }

    public function test_re_saving_the_same_field_updates_the_existing_row_instead_of_duplicating_it(): void
    {
        $seller = User::factory()->create();
        $sync = app(BusinessProfileFaqSyncService::class);

        $profile = BusinessProfile::create(['user_id' => $seller->id, 'phone' => '+1 555 0100']);
        $sync->sync($profile);

        $profile->update(['phone' => '+1 555 0199']);
        $sync->sync($profile);

        $this->assertSame(1, Faq::where('user_id', $seller->id)->where('source_key', 'phone')->count());
        $this->assertDatabaseHas('faqs', ['user_id' => $seller->id, 'source_key' => 'phone', 'answer' => '+1 555 0199']);
    }

    public function test_a_synced_business_hours_faq_actually_wins_findbestmatch_for_a_matching_question(): void
    {
        $seller = User::factory()->create();

        $profile = BusinessProfile::create(['user_id' => $seller->id, 'business_hours' => ['mon' => '9:00-18:00']]);
        app(BusinessProfileFaqSyncService::class)->sync($profile);

        // Same fixed embedding vector as the customer message below
        // (semantic 1.0) plus identical non-stopword tokens to the synced
        // question ("what are your business hours") -> keyword 1.0 too.
        $result = app(AiCopilotService::class)->findBestMatch('what are your business hours', $seller->id);

        $this->assertSame('business_hours', $result['faq']?->source_key);
        $this->assertTrue($result['auto_reply_eligible']);
    }

    public function test_a_sellers_business_profile_never_leaks_into_another_sellers_match(): void
    {
        $sellerA = User::factory()->create();
        $sellerB = User::factory()->create();

        $profileA = BusinessProfile::create(['user_id' => $sellerA->id, 'business_hours' => ['mon' => '9:00-18:00']]);
        app(BusinessProfileFaqSyncService::class)->sync($profileA);

        // Seller B has no profile and no FAQs at all.
        $result = app(AiCopilotService::class)->findBestMatch('what are your business hours', $sellerB->id);

        $this->assertNull($result['faq']);
        $this->assertSame('no_match', $result['status']);
    }
}
