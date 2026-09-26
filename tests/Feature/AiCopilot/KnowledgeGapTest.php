<?php

namespace Tests\Feature\AiCopilot;

use App\Jobs\Messaging\ProcessAiCopilotReply;
use App\Models\AiCopilotSetting;
use App\Models\Faq;
use App\Models\KnowledgeGapReport;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AiCopilotService;
use App\Services\MessagingServices\MessagingManagerService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * ProcessAiCopilotReply::recordKnowledgeGap() (the no_match branch) and
 * KnowledgeGapController - the "make the audit trail actually useful"
 * half of this phase. A gap is a genuinely new concept (no prior test
 * coverage), so this pins: dedup-by-normalized-question instead of
 * duplicate rows, tenant isolation, the convert-to-FAQ draft-only rule,
 * and cross-seller IDOR on both mutation actions.
 */
class KnowledgeGapTest extends TestCase
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

    private function conversationFor(User $seller): Conversation
    {
        $account = SocialAccount::create([
            'user_id' => $seller->id, 'platform' => 'facebook', 'platform_account_id' => 'page_' . $seller->id,
            'access_token' => 'test-token',
        ]);

        return Conversation::create([
            'social_account_id' => $account->id, 'platform' => 'facebook',
            'customer_external_id' => 'cust_' . $seller->id, 'status' => 'open',
        ]);
    }

    private function triggerNoMatch(User $seller, Conversation $conversation, string $question): void
    {
        AiCopilotSetting::firstOrCreate(['user_id' => $seller->id], ['ai_enabled' => true, 'auto_reply_enabled' => true]);

        $message = Message::create([
            'conversation_id' => $conversation->id, 'direction' => 'inbound',
            'sender_type' => 'customer', 'type' => 'text', 'body' => $question, 'status' => 'delivered',
        ]);

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(MessagingManagerService::class));
    }

    public function test_a_repeated_unanswered_question_increments_the_same_gap_instead_of_duplicating(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $seller = $this->seller();
        $conversation = $this->conversationFor($seller);

        $this->triggerNoMatch($seller, $conversation, 'Do you ship to the moon?');
        $this->triggerNoMatch($seller, $conversation, 'do you ship to the moon');

        $this->assertSame(1, KnowledgeGapReport::where('user_id', $seller->id)->count());
        $gap = KnowledgeGapReport::where('user_id', $seller->id)->firstOrFail();
        $this->assertSame(2, $gap->occurrence_count);
        $this->assertSame('new', $gap->status);
    }

    public function test_two_sellers_with_the_identical_question_get_two_separate_gap_rows(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $sellerA = $this->seller();
        $sellerB = $this->seller();

        $this->triggerNoMatch($sellerA, $this->conversationFor($sellerA), 'Do you ship to the moon?');
        $this->triggerNoMatch($sellerB, $this->conversationFor($sellerB), 'Do you ship to the moon?');

        $this->assertSame(1, KnowledgeGapReport::where('user_id', $sellerA->id)->count());
        $this->assertSame(1, KnowledgeGapReport::where('user_id', $sellerB->id)->count());
    }

    public function test_a_recurring_question_does_not_reopen_a_gap_already_turned_into_a_faq(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $seller = $this->seller();
        $conversation = $this->conversationFor($seller);

        $this->triggerNoMatch($seller, $conversation, 'Do you ship to the moon?');
        $gap = KnowledgeGapReport::where('user_id', $seller->id)->firstOrFail();
        $gap->update(['status' => 'faq_created']);

        $this->triggerNoMatch($seller, $conversation, 'Do you ship to the moon?');

        $this->assertSame('faq_created', $gap->fresh()->status);
        $this->assertSame(2, $gap->fresh()->occurrence_count);
    }

    public function test_converting_a_gap_creates_a_draft_embedded_faq_and_marks_the_gap_resolved(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);

        $seller = $this->seller();
        $gap = KnowledgeGapReport::create([
            'user_id' => $seller->id, 'question' => 'Do you ship to the moon?',
            'question_hash' => KnowledgeGapReport::normalize('Do you ship to the moon?'),
            'occurrence_count' => 3, 'last_occurred_at' => now(), 'status' => 'new',
        ]);

        $response = $this->actingAs($seller)->postJson(
            route('admin.ai-copilot.knowledge-gaps.convert-to-faq', $gap),
            ['answer' => 'Not yet, but we do ship worldwide on Earth.']
        );

        $response->assertOk();
        $faqId = $response->json('faq.id');

        $this->assertDatabaseHas('faqs', [
            'id' => $faqId, 'user_id' => $seller->id, 'question' => 'Do you ship to the moon?',
            'status' => 'draft',
        ]);
        $this->assertNotNull(Faq::find($faqId)->embedding);

        $gap->refresh();
        $this->assertSame('faq_created', $gap->status);
        $this->assertSame($faqId, $gap->suggested_faq_id);
    }

    public function test_a_seller_cannot_convert_or_ignore_another_sellers_gap(): void
    {
        $owner = $this->seller();
        $intruder = $this->seller();

        $gap = KnowledgeGapReport::create([
            'user_id' => $owner->id, 'question' => 'Private question', 'question_hash' => KnowledgeGapReport::normalize('Private question'),
            'occurrence_count' => 1, 'last_occurred_at' => now(), 'status' => 'new',
        ]);

        $this->actingAs($intruder)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.convert-to-faq', $gap), ['answer' => 'hacked'])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.ignore', $gap))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.under-review', $gap))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.resolve', $gap))
            ->assertForbidden();

        $this->assertSame('new', $gap->fresh()->status);
    }

    public function test_ignoring_a_gap_marks_it_ignored(): void
    {
        $seller = $this->seller();
        $gap = KnowledgeGapReport::create([
            'user_id' => $seller->id, 'question' => 'Q', 'question_hash' => KnowledgeGapReport::normalize('Q'),
            'occurrence_count' => 1, 'last_occurred_at' => now(), 'status' => 'new',
        ]);

        $this->actingAs($seller)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.ignore', $gap))
            ->assertOk();

        $this->assertSame('ignored', $gap->fresh()->status);
    }

    /**
     * QA audit finding: 'under_review' and 'resolved' existed in the
     * status enum and the view's badge styling but nothing ever set them
     * - these two actions close that gap.
     */
    public function test_a_gap_can_be_marked_under_review_then_resolved(): void
    {
        $seller = $this->seller();
        $gap = KnowledgeGapReport::create([
            'user_id' => $seller->id, 'question' => 'Q', 'question_hash' => KnowledgeGapReport::normalize('Q'),
            'occurrence_count' => 1, 'last_occurred_at' => now(), 'status' => 'new',
        ]);

        $this->actingAs($seller)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.under-review', $gap))
            ->assertOk();
        $this->assertSame('under_review', $gap->fresh()->status);

        $this->actingAs($seller)
            ->postJson(route('admin.ai-copilot.knowledge-gaps.resolve', $gap))
            ->assertOk();
        $this->assertSame('resolved', $gap->fresh()->status);
    }
}
