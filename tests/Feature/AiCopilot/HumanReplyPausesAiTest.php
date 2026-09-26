<?php

namespace Tests\Feature\AiCopilot;

use App\Models\Messaging\Conversation;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * The other half of ProcessAiCopilotReplyTest's "a conversation a human
 * agent has taken over is never auto replied to" test - this confirms
 * ChatController::store() (a human sending a reply) is what actually
 * sets ai_paused_at in the first place, and that resumeAi() clears it.
 * Goes through the real route + middleware stack (minus the subscription
 * check, which needs unrelated billing fixtures - same exemption already
 * used for FaqAuthorizationTest/TicketAuthorizationTest) rather than
 * calling the controller directly, since the pause flag is set inside the
 * same request that performs the real platform send.
 */
class HumanReplyPausesAiTest extends TestCase
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
        // MessagingManagerService::send() makes a real outbound HTTP call
        // to the platform - faked so this test never touches the network,
        // regardless of whether it succeeds (ChatController::store() sets
        // the pause flag either way).
        Http::fake();
    }

    private function sellerWithConversation(): array
    {
        $seller = User::factory()->create();
        $seller->assignRole(Role::findOrCreate('seller', 'web'));

        $account = SocialAccount::create([
            'user_id' => $seller->id, 'platform' => 'facebook', 'platform_account_id' => 'page_' . $seller->id,
            'access_token' => 'test-token',
        ]);
        $conversation = Conversation::create([
            'social_account_id' => $account->id, 'platform' => 'facebook',
            'customer_external_id' => 'cust_' . $seller->id, 'status' => 'open',
        ]);

        return [$seller, $conversation];
    }

    public function test_a_human_agents_reply_pauses_the_ai_copilot_on_that_conversation(): void
    {
        [$seller, $conversation] = $this->sellerWithConversation();
        $this->assertNull($conversation->ai_paused_at);

        $this->actingAs($seller)->postJson('/platform/chats', [
            'conversation_id' => $conversation->id,
            'body'            => 'Thanks for reaching out, let me help with that.',
        ])->assertOk();

        $this->assertNotNull($conversation->fresh()->ai_paused_at);
    }

    public function test_resume_ai_clears_the_pause_flag(): void
    {
        [$seller, $conversation] = $this->sellerWithConversation();
        $conversation->update(['ai_paused_at' => now()]);

        $this->actingAs($seller)
            ->postJson("/platform/chats/{$conversation->id}/resume-ai")
            ->assertOk();

        $this->assertNull($conversation->fresh()->ai_paused_at);
    }

    public function test_a_seller_cannot_resume_ai_on_another_sellers_conversation(): void
    {
        [, $conversation] = $this->sellerWithConversation();
        $intruder = User::factory()->create();
        $intruder->assignRole(Role::findOrCreate('seller', 'web'));

        $this->actingAs($intruder)
            ->postJson("/platform/chats/{$conversation->id}/resume-ai")
            ->assertForbidden();
    }
}
