<?php

namespace Tests\Feature\Support;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

/**
 * Pins the real bug found in this audit: admin.faqs.* / admin.help-center.*
 * used to sit inside a route group gated solely by the 'seller' middleware
 * (EnsureSeller: hasRole('seller') && !isTeamMember()), which by
 * construction can never be passed by an admin or customer_support
 * account - so System FAQ management was completely unreachable for the
 * only role it's built for, even though FaqController::authorizeAdmin()
 * and the sidebar's own role check were both already correct in
 * isolation. Fixed in routes/web.php (moved to role:admin). These tests
 * exercise the real route + middleware + controller + database chain end
 * to end, not just the controller in isolation, since the bug lived in
 * the routing layer.
 */
class FaqAuthorizationTest extends TestCase
{
    use CreatesSupportModuleTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportModuleTables();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function customerSupport(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('customer_support', 'web'));

        return $user;
    }

    private function seller(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('seller', 'web'));

        return $user;
    }

    public function test_admin_can_reach_and_fully_manage_system_faqs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/faqs')->assertOk();

        $store = $this->actingAs($admin)->postJson('/faqs', [
            'question' => 'How do I reset my password?',
            'answer'   => 'Go to settings and click reset.',
            'language' => 'en',
            'status'   => 'published',
        ]);
        $store->assertOk()->assertJson(['success' => true]);
        $faqId = $store->json('faq.id');
        $this->assertDatabaseHas('faqs', ['id' => $faqId, 'user_id' => null]);

        $this->actingAs($admin)->putJson("/faqs/{$faqId}", [
            'question' => 'How do I reset my password? (updated)',
            'answer'   => 'Updated answer.',
            'language' => 'en',
            'status'   => 'published',
        ])->assertOk();

        $this->actingAs($admin)->deleteJson("/faqs/{$faqId}")->assertOk();
        $this->assertDatabaseMissing('faqs', ['id' => $faqId]);
    }

    public function test_customer_support_cannot_manage_system_faqs(): void
    {
        // Route middleware now lets customer_support reach the controller
        // (previously it couldn't reach the route at all) - but
        // FaqController::authorizeAdmin() is intentionally admin-only per
        // its own docblock, so this must still be a 403, not a 200.
        $agent = $this->customerSupport();

        $this->actingAs($agent)->get('/faqs')->assertForbidden();
        $this->actingAs($agent)->postJson('/faqs', [
            'question' => 'x', 'answer' => 'y', 'language' => 'en', 'status' => 'draft',
        ])->assertForbidden();
    }

    public function test_seller_cannot_manage_system_faqs(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller)->get('/faqs')->assertForbidden();
        $this->actingAs($seller)->postJson('/faqs', [
            'question' => 'x', 'answer' => 'y', 'language' => 'en', 'status' => 'draft',
        ])->assertForbidden();

        $systemFaq = Faq::create(['question' => 'Q', 'answer' => 'A', 'language' => 'en', 'status' => 'published']);
        $this->actingAs($seller)->putJson("/faqs/{$systemFaq->id}", [
            'question' => 'hacked', 'answer' => 'hacked', 'language' => 'en', 'status' => 'published',
        ])->assertForbidden();
        $this->actingAs($seller)->deleteJson("/faqs/{$systemFaq->id}")->assertForbidden();
        $this->assertDatabaseHas('faqs', ['id' => $systemFaq->id, 'question' => 'Q']);
    }

    public function test_unauthenticated_visitor_cannot_reach_faq_management(): void
    {
        $this->get('/faqs')->assertRedirect('/login');
    }

    public function test_faq_answer_is_sanitized_on_create(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/faqs', [
            'question' => 'XSS test',
            'answer'   => '<p>Safe text</p><script>alert(1)</script><img src=x onerror="alert(2)">',
            'language' => 'en',
            'status'   => 'draft',
        ]);

        $response->assertOk();
        $faq = Faq::findOrFail($response->json('faq.id'));
        $this->assertStringNotContainsString('<script', $faq->answer);
        $this->assertStringNotContainsString('onerror', $faq->answer);
        $this->assertStringContainsString('Safe text', $faq->answer);
    }

    public function test_help_center_only_shows_published_system_faqs_to_a_seller(): void
    {
        Faq::create(['question' => 'Published one', 'answer' => 'A', 'language' => 'en', 'status' => 'published']);
        Faq::create(['question' => 'Draft one', 'answer' => 'A', 'language' => 'en', 'status' => 'draft']);

        $seller = $this->seller();

        // The controller branches on $request->ajax(), which checks the
        // X-Requested-With header specifically (set by window.axios in the
        // real app) - getJson() alone only sets Accept/Content-Type, so
        // without this the controller would render the full Blade view
        // instead of JSON.
        $response = $this->actingAs($seller)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/help-center');
        $response->assertOk();
        $questions = collect($response->json('faqs'))->pluck('question');

        $this->assertTrue($questions->contains('Published one'));
        $this->assertFalse($questions->contains('Draft one'));
    }

    // Note: KnowledgeBaseController's own cross-seller ownership check
    // (authorizeOwner()) is not exercised here - its routes additionally
    // require EnsureActiveSubscription, which needs real
    // subscriptions/plans fixtures out of scope for this audit (that
    // logic wasn't found broken; it was verified correct by direct code
    // review: user_id === Auth::id() checked before any write, category
    // ownership re-checked server-side rather than trusting the `exists`
    // rule alone).
}
