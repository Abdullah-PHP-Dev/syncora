<?php

namespace Tests\Feature\AiCopilot;

use App\Models\CopilotMessage;
use App\Models\Messaging\Conversation as MessagingConversation;
use App\Models\SocialAccount;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\AiCopilot\Concerns\CreatesAiCopilotTables;
use Tests\TestCase;

/**
 * AiCopilotAnalyticsController is pure aggregation over CopilotMessage -
 * these tests hand-seed a known mix of resolution_type rows and assert
 * the KPI math against that exact, manually-computed expectation, plus
 * tenant isolation (another seller's rows must never affect the numbers).
 */
class AiCopilotAnalyticsTest extends TestCase
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

    private function conversationFor(User $seller): MessagingConversation
    {
        $account = SocialAccount::create([
            'user_id' => $seller->id, 'platform' => 'facebook', 'platform_account_id' => 'page_' . $seller->id,
        ]);

        return MessagingConversation::create([
            'social_account_id' => $account->id, 'platform' => 'facebook',
            'customer_external_id' => 'cust_' . $seller->id, 'status' => 'open',
        ]);
    }

    private function seedCopilotMessage(User $seller, MessagingConversation $conversation, string $resolutionType, int $confidence): CopilotMessage
    {
        return CopilotMessage::create([
            'conversation_id' => $conversation->id, 'user_id' => $seller->id,
            'confidence' => $confidence, 'resolution_type' => $resolutionType, 'was_sent' => $resolutionType === 'auto_replied',
        ]);
    }

    public function test_kpi_counts_and_resolution_rate_match_a_hand_seeded_mix_exactly(): void
    {
        $seller = $this->seller();
        $conversation = $this->conversationFor($seller);

        // 2 auto_replied (confidence 90, 80), 1 suggested (65), 1 no_match (10).
        $this->seedCopilotMessage($seller, $conversation, 'auto_replied', 90);
        $this->seedCopilotMessage($seller, $conversation, 'auto_replied', 80);
        $this->seedCopilotMessage($seller, $conversation, 'suggested', 65);
        $this->seedCopilotMessage($seller, $conversation, 'no_match', 10);

        $response = $this->actingAs($seller)->get(route('admin.ai-copilot.analytics.index'));

        $response->assertOk();
        $response->assertViewHas('total', 4);
        $response->assertViewHas('autoReplied', 2);
        $response->assertViewHas('suggested', 1);
        $response->assertViewHas('noMatch', 1);
        // resolution rate = 2/4 = 50%; average confidence = (90+80+65+10)/4 = 61.25 -> rounds to 61.
        $response->assertViewHas('resolutionRate', 50);
        $response->assertViewHas('averageConfidence', 61);
    }

    public function test_another_sellers_copilot_activity_never_affects_the_numbers(): void
    {
        $seller = $this->seller();
        $otherSeller = $this->seller();

        $this->seedCopilotMessage($seller, $this->conversationFor($seller), 'auto_replied', 90);

        // Ten rows for a different seller (same conversation - only the
        // CopilotMessage count matters here) - if scoping were wrong these
        // would swamp the assertion below.
        $otherConversation = $this->conversationFor($otherSeller);
        for ($i = 0; $i < 10; $i++) {
            $this->seedCopilotMessage($otherSeller, $otherConversation, 'no_match', 5);
        }

        $response = $this->actingAs($seller)->get(route('admin.ai-copilot.analytics.index'));

        $response->assertOk();
        $response->assertViewHas('total', 1);
        $response->assertViewHas('autoReplied', 1);
        $response->assertViewHas('noMatch', 0);
    }

    public function test_zero_activity_reports_zero_percentages_without_dividing_by_zero(): void
    {
        $seller = $this->seller();

        $response = $this->actingAs($seller)->get(route('admin.ai-copilot.analytics.index'));

        $response->assertOk();
        $response->assertViewHas('total', 0);
        $response->assertViewHas('resolutionRate', 0);
        $response->assertViewHas('averageConfidence', 0);
    }
}
