<?php

namespace Tests\Feature\AiCopilot;

use App\Jobs\Messaging\ProcessAiCopilotReply;
use App\Models\AiCopilotSetting;
use App\Models\CopilotMessage;
use App\Models\Faq;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AiCopilotService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * Pins the actual gap this phase closes: nothing previously called
 * AiCopilotService::findBestMatch() when a message arrived, and nothing
 * ever sent a reply automatically - see this job's own docblock. These
 * tests exercise the full branch (auto-reply / suggested-only / no-match)
 * against a seller's own configured thresholds, not the hardcoded BRD
 * defaults, plus the two safety gates (AI disabled, conversation paused).
 *
 * Gemini is faked throughout - AiCopilotService::embed() is the only
 * external call this job makes before a (also faked, via
 * MessagingManagerService's underlying Http client) platform send.
 */
class ProcessAiCopilotReplyTest extends TestCase
{
    use CreatesAiCopilotTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAiCopilotTables();
    }

    private function sellerWithConversation(): array
    {
        $seller = User::factory()->create();
        $account = SocialAccount::create([
            'user_id' => $seller->id, 'platform' => 'facebook', 'platform_account_id' => 'page_' . $seller->id,
            // FacebookMessengerService::sendMessage() requires a non-null
            // string token to build its Graph API call, even though
            // Http::fake() below means it's never actually sent anywhere.
            'access_token' => 'test-token',
        ]);
        $conversation = Conversation::create([
            'social_account_id' => $account->id, 'platform' => 'facebook',
            'customer_external_id' => 'cust_' . $seller->id, 'status' => 'open',
        ]);

        return [$seller, $conversation];
    }

    private function inboundMessage(Conversation $conversation, string $body): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id, 'direction' => 'inbound',
            'sender_type' => 'customer', 'type' => 'text', 'body' => $body, 'status' => 'delivered',
        ]);
    }

    private function publishedFaq(int $sellerId, string $question, string $answer): Faq
    {
        return Faq::create([
            'user_id' => $sellerId, 'question' => $question, 'answer' => $answer,
            'language' => 'en', 'status' => 'published',
        ]);
    }

    public function test_high_confidence_match_is_sent_automatically_when_thresholds_and_auto_reply_allow_it(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]]),
            '*' => Http::response(['id' => 'ext_123'], 200),
        ]);

        [$seller, $conversation] = $this->sellerWithConversation();

        // Identical wording to the customer message -> keyword overlap 1.0.
        // Same fixed embedding vector as the message -> semantic 1.0.
        // Composite: 50 (semantic) + 20 (keyword) + 10 (neutral historical)
        // + 5 (neutral context, no prior messages) = 85.
        $faq = $this->publishedFaq($seller->id, 'what are your business hours', 'We are open 9 to 5.');
        app(AiCopilotService::class)->embedFaq($faq);

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
            'confidence_threshold_auto' => 80, 'confidence_threshold_suggested' => 50,
        ]);

        $message = $this->inboundMessage($conversation, 'what are your business hours');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        // Scoped to this test's own conversation - the shared :memory: DB
        // isn't reset between test methods (same convention as
        // CreatesSupportModuleTables), so a bare firstOrFail() could pick
        // up a leftover row from an earlier test in this class.
        $log = CopilotMessage::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(85, $log->confidence);
        $this->assertSame('auto_replied', $log->resolution_type);
        $this->assertTrue($log->was_sent);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id, 'direction' => 'outbound',
            'sender_type' => 'bot', 'body' => 'We are open 9 to 5.', 'status' => 'sent',
        ]);
    }

    /**
     * QA finding: FaqController/KnowledgeBaseController's real save path
     * wraps every answer in real HTML via Purifier (even a plain one-line
     * answer becomes "<p>...</p>" - confirmed live against the actual
     * controller before this fix). ProcessAiCopilotReply sends
     * suggested_reply verbatim as the real outbound message body to a
     * real customer on Facebook/Instagram/etc - without converting HTML
     * back to plain text first, a customer would have received literal,
     * visible "<p>...</p>" tags in their DM.
     */
    public function test_auto_reply_strips_html_from_the_faq_answer_before_sending(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]]),
            '*' => Http::response(['id' => 'ext_123'], 200),
        ]);

        [$seller, $conversation] = $this->sellerWithConversation();

        // Same shape Purifier's 'default' profile (AutoFormat.AutoParagraph)
        // actually produces for a plain-text answer - see FaqController's
        // validated() / this fix's own AiCopilotService::htmlToPlainText().
        $faq = $this->publishedFaq($seller->id, 'what are your business hours', '<p>We are open 9 to 5.</p>');
        app(AiCopilotService::class)->embedFaq($faq);

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
            'confidence_threshold_auto' => 80, 'confidence_threshold_suggested' => 50,
        ]);

        $message = $this->inboundMessage($conversation, 'what are your business hours');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        $sent = \App\Models\Messaging\Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->firstOrFail();
        $this->assertSame('We are open 9 to 5.', $sent->body);
        $this->assertStringNotContainsString('<p>', $sent->body);
    }

    public function test_mid_confidence_match_is_logged_as_suggested_but_never_sent(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embedding' => ['values' => [1, 0, 0]]])]);
        Http::preventStrayRequests();

        [$seller, $conversation] = $this->sellerWithConversation();

        // Same fixed vector (semantic 1.0 = 50pts) but deliberately
        // dissimilar wording from the customer message, so keyword overlap
        // is 0. Composite: 50 + 0 + 10 + 5 = 65 - above the 50 suggestion
        // threshold, below the 80 auto-reply threshold.
        $faq = $this->publishedFaq($seller->id, 'delivery timeframe details', 'Two to three business days.');
        app(AiCopilotService::class)->embedFaq($faq);

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
            'confidence_threshold_auto' => 80, 'confidence_threshold_suggested' => 50,
        ]);

        $message = $this->inboundMessage($conversation, 'totally unrelated wording here');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        // Scoped to this test's own conversation - the shared :memory: DB
        // isn't reset between test methods (same convention as
        // CreatesSupportModuleTables), so a bare firstOrFail() could pick
        // up a leftover row from an earlier test in this class.
        $log = CopilotMessage::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(65, $log->confidence);
        $this->assertSame('suggested', $log->resolution_type);
        $this->assertFalse($log->was_sent);

        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'direction' => 'outbound']);
    }

    public function test_low_confidence_is_logged_as_no_match_and_never_sent(): void
    {
        // FAQ gets one embedding vector, the customer message gets an
        // orthogonal one - cosine similarity 0. Composite: 0 + 0
        // (unrelated wording too) + 10 + 5 = 15, below the suggestion
        // threshold entirely.
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['embedding' => ['values' => [1, 0, 0]]])
            ->push(['embedding' => ['values' => [0, 1, 0]]]);
        Http::preventStrayRequests();

        [$seller, $conversation] = $this->sellerWithConversation();

        $faq = $this->publishedFaq($seller->id, 'what are your business hours', 'We are open 9 to 5.');
        app(AiCopilotService::class)->embedFaq($faq);

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
            'confidence_threshold_auto' => 80, 'confidence_threshold_suggested' => 50,
        ]);

        $message = $this->inboundMessage($conversation, 'totally unrelated question');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        // Scoped to this test's own conversation - the shared :memory: DB
        // isn't reset between test methods (same convention as
        // CreatesSupportModuleTables), so a bare firstOrFail() could pick
        // up a leftover row from an earlier test in this class.
        $log = CopilotMessage::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(15, $log->confidence);
        $this->assertSame('no_match', $log->resolution_type);
        $this->assertFalse($log->was_sent);
    }

    public function test_a_seller_with_ai_disabled_never_gets_a_reply_or_a_log_row(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        [, $conversation] = $this->sellerWithConversation();

        // No AiCopilotSetting row at all - forSeller()'s default is
        // ai_enabled=false, so the job must bail before making any Gemini
        // call or writing any audit row.
        $message = $this->inboundMessage($conversation, 'what are your business hours');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        $this->assertSame(0, CopilotMessage::where('conversation_id', $conversation->id)->count());
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'direction' => 'outbound']);
    }

    public function test_a_conversation_a_human_agent_has_taken_over_is_never_auto_replied_to(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        [$seller, $conversation] = $this->sellerWithConversation();
        $conversation->update(['ai_paused_at' => now()]);

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
            'confidence_threshold_auto' => 1, 'confidence_threshold_suggested' => 1,
        ]);

        $message = $this->inboundMessage($conversation, 'what are your business hours');

        (new ProcessAiCopilotReply($message->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        $this->assertSame(0, CopilotMessage::where('conversation_id', $conversation->id)->count());
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id, 'direction' => 'outbound']);
    }

    public function test_a_stale_message_is_skipped_in_favor_of_the_customers_newer_one(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        [$seller, $conversation] = $this->sellerWithConversation();

        AiCopilotSetting::create([
            'user_id' => $seller->id, 'ai_enabled' => true, 'auto_reply_enabled' => true,
        ]);

        $first = $this->inboundMessage($conversation, 'first message');
        $this->inboundMessage($conversation, 'second, newer message');

        // Simulates the first message's queued job finally running after
        // the customer already sent a second one.
        (new ProcessAiCopilotReply($first->id))->handle(app(AiCopilotService::class), app(\App\Services\MessagingServices\MessagingManagerService::class));

        $this->assertSame(0, CopilotMessage::where('conversation_id', $conversation->id)->count());
    }
}
