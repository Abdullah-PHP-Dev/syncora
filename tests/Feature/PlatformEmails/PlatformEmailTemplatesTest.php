<?php

namespace Tests\Feature\PlatformEmails;

use App\Mail\BrandedPlatformMail;
use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use App\Models\PlatformEmails\PlatformEmailTemplate;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Services\PlatformEmails\TemplateRegistry;
use App\Services\PlatformEmails\TemplateRenderer;
use App\Services\PlatformEmails\TemplateValidator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesAdminSettingsTable;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** Administrator email templates: access, editing workflow, safe rendering and real sends. */
class PlatformEmailTemplatesTest extends TestCase
{
    use CreatesAdminSettingsTable, CreatesNotificationTables;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        $this->createAdminSettingsTable();
        Mail::fake();
        $this->superAdmin = User::factory()->create(['name' => 'Platform Admin', 'email' => 'root@example.com']);
        $this->superAdmin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('customer_support', 'web');
    }

    private function url(string $name, string $key = 'publishing.post_failed', string $locale = 'en', array $extra = []): string
    {
        return route('admin.platform-emails.'.$name, ['key' => $key, 'locale' => $locale] + $extra);
    }

    private function content(array $overrides = []): array
    {
        return array_replace(app(TemplateRegistry::class)->defaults('publishing.post_failed', 'en'), $overrides);
    }

    private function failedPost(): void
    {
        Notify::event('publishing.post_failed', ['workspace' => $this->owner->id, 'dedup' => (string) microtime(true),
            'params' => ['platform' => 'Instagram', 'excerpt' => 'Spring offer', 'reason' => 'Bad ratio'], 'url' => '/admin/posts']);
        // A grouped event: held for its window, then sent by the every-minute task.
        $this->travel(6)->minutes();
        $this->artisan('notifications:send-due')->assertSuccessful();
    }

    public function test_only_super_administrators_can_reach_any_template_screen_or_action(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        foreach ([$this->owner, $support] as $user) {
            $this->actingAs($user)->get(route('admin.platform-emails.index'))->assertForbidden();
            $this->get($this->url('edit'))->assertForbidden();
            $this->postJson($this->url('publish'), $this->content())->assertForbidden();
            $this->postJson($this->url('preview'), $this->content())->assertForbidden();
            $this->putJson(route('admin.platform-emails.branding.update'), [])->assertForbidden();
        }
        $this->assertSame(0, PlatformEmailTemplate::count());
        $this->actingAs($this->superAdmin)->get(route('admin.platform-emails.index'))->assertOk()->assertSee('auth.verify_email');
        $this->get(route('admin.platform-emails.index', ['q' => 'post_failed']))->assertOk()->assertSee('publishing.post_failed');
        $this->get(route('admin.platform-emails.index', ['module' => 'billing', 'locale' => 'ar']))->assertOk()->assertDontSee('publishing.post_failed');
        $this->get($this->url('edit', 'billing.payment_failed', 'ar'))->assertOk()->assertSee('platform-email-editor', false);
        // The pages sit inside the #app Vue root: literal "{{ plan }}" text must not be compiled by Vue
        // (found in a headless-browser run: it threw ReferenceError and emptied the table).
        $this->get(route('admin.platform-emails.index', ['q' => 'payment_succeeded']))->assertSee('{{ plan }}', false)->assertSee('account-page" v-pre', false);
        $this->get(route('admin.platform-emails.branding'))->assertOk()->assertSee('account-page" v-pre', false);
        $this->get(route('admin.platform-emails.logs'))->assertSee('account-page" v-pre', false);
        $this->get(route('admin.platform-emails.logs'))->assertOk();
        $this->get($this->url('edit', 'no.such_event'))->assertNotFound();
    }

    public function test_every_built_in_default_is_a_valid_template_in_both_languages(): void
    {
        $registry = app(TemplateRegistry::class);
        $this->assertCount(62, $registry->all()); // +1: support.new_signup (team alert)
        foreach (array_keys($registry->all()) as $key) {
            foreach (TemplateRegistry::LOCALES as $locale) {
                $content = app(TemplateValidator::class)->validate($key, $registry->defaults($key, $locale));
                $this->assertNotSame('', $content['subject'], "$key/$locale has no subject");
                $this->assertDoesNotMatchRegularExpression('/(?<![\w\/]):[a-z_]{3,}/', $content['subject'].' '.$content['heading'], "$key/$locale left a raw :placeholder");
            }
        }
    }

    public function test_rendering_escapes_values_and_never_runs_code_or_unsafe_links(): void
    {
        $renderer = app(TemplateRenderer::class);
        $vars = ['customer_name' => '<script>alert(1)</script>', 'action_url' => 'javascript:alert(1)', 'excerpt' => '**not bold**'];

        $html = $renderer->text('Hi {{ customer_name }}, **done** [open]({{ action_url }}) *x* {{ missing }} {{ excerpt }}', $vars);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html, 'a link that is not http(s)/mailto is dropped');
        $this->assertStringContainsString('<strong>done</strong>', $html);
        $this->assertStringContainsString('**not bold**', $html, 'values never become formatting');
        $this->assertNull($renderer->url('{{ action_url }}', $vars));
        $this->assertSame('https://x.test/a?b=1', $renderer->url('{{ action_url }}', ['action_url' => 'https://x.test/a?b=1']));
        $this->assertSame('Hi', $renderer->plain('Hi {{ nobody }}', []));
        // Blade syntax in a template is just text: nothing is compiled or executed.
        $this->assertSame('{{-- x --}} @php echo 1; @endphp', $renderer->plain('{{-- x --}} @php echo 1; @endphp', []));
    }

    public function test_saving_rejects_unknown_variables_unsafe_links_and_scripts(): void
    {
        $this->actingAs($this->superAdmin);
        $this->postJson($this->url('draft'), $this->content(['subject' => 'Hi {{ card_number }}']))->assertJsonValidationErrors('variables');
        $this->postJson($this->url('draft'), $this->content(['cta_url' => 'javascript:alert(1)']))->assertJsonValidationErrors('cta_url');
        $this->postJson($this->url('draft'), $this->content(['blocks' => [['type' => 'html', 'html' => '<p onclick="x()">Hello</p><script>alert(1)</script><form action="/x"></form>']]]))->assertOk();

        $html = PlatformEmailTemplate::firstOrFail()->draft['blocks'][0]['html'];
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_a_published_template_is_what_customers_receive_in_its_language_only(): void
    {
        $this->actingAs($this->superAdmin)->postJson($this->url('draft'), $this->content(['subject' => 'DRAFT {{ platform }}']))->assertOk();
        $this->failedPost();
        Mail::assertNotSent(PlatformNotificationMail::class, fn ($m) => str_contains($m->envelope()->subject, 'DRAFT'));

        $this->postJson($this->url('publish'), $this->content([
            'subject' => 'Heads up {{ customer_name }}: {{ platform }} said no',
            'blocks' => [['type' => 'text', 'text' => 'Because **{{ reason }}**.'], ['type' => 'details']],
        ]))->assertOk()->assertJsonPath('version', 1);
        Mail::fake();
        $this->failedPost();

        // English readers get the new template; the Arabic reader keeps the built-in Arabic (never English text).
        $mail = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com'))->first();
        $this->assertSame('Heads up Rawda: Instagram said no', $mail->envelope()->subject);
        $this->assertStringContainsString('Because <strong>Bad ratio</strong>.', $mail->render());
        $this->assertSame('v1', $mail->templateVersion);
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('omar@example.com') && str_starts_with($m->envelope()->subject, 'إجراء مطلوب: لم يتم نشر منشورك المجدول'));
        $this->assertSame('Heads up Rawda: Instagram said no', NotificationDelivery::where('to_address', 'rawda@example.com')->latest('id')->value('subject'));
    }

    public function test_switching_an_email_off_stops_only_the_email(): void
    {
        $this->actingAs($this->superAdmin)->postJson($this->url('toggle'), ['enabled' => false])->assertOk()->assertJsonPath('active', false);
        $this->failedPost();

        Mail::assertNothingSent();
        $this->assertTrue(UserNotification::where('user_id', $this->owner->id)->exists(), 'the in-app notification still arrives');
        $this->assertSame('disabled by administrator', NotificationDelivery::where('channel', 'email')->value('error'));
        // Account-access emails can't be switched off.
        $this->postJson($this->url('toggle', 'auth.password_reset'), ['enabled' => false])->assertStatus(422);
    }

    public function test_reset_and_version_history_restore_without_losing_anything(): void
    {
        $this->actingAs($this->superAdmin);
        $this->postJson($this->url('publish'), $this->content(['subject' => 'First {{ platform }}']))->assertOk();
        $this->postJson($this->url('reset'))->assertOk()->assertJsonPath('version', 2);
        $this->failedPost();
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && $m->envelope()->subject === "Action required: your scheduled post didn't publish");

        $versions = $this->getJson($this->url('versions'))->assertOk()->json('versions');
        $this->assertSame(['reset', 'published'], array_column($versions, 'action'));
        $this->postJson($this->url('restore', extra: ['version' => $versions[1]['id']]))->assertOk()->assertJsonPath('version', 3)->assertJsonPath('custom', true);
        $this->assertSame('First {{ platform }}', PlatformEmailTemplate::firstOrFail()->content['subject']);
        $this->assertSame(3, PlatformEmailTemplate::firstOrFail()->version);
    }

    public function test_preview_and_test_send_use_sample_data_and_touch_nothing_else(): void
    {
        $this->actingAs($this->superAdmin);
        $preview = $this->postJson($this->url('preview', locale: 'ar'), app(TemplateRegistry::class)->defaults('publishing.post_failed', 'ar'))->assertOk();
        $this->assertStringContainsString('dir="rtl"', $preview->json('html'));
        $this->assertStringContainsString('Instagram', $preview->json('html'));

        // Typing triggers many previews; they must not use up the test-send allowance (found in a live browser run).
        for ($i = 0; $i < 15; $i++) {
            $this->postJson($this->url('preview'), $this->content())->assertOk();
        }
        $this->postJson($this->url('test'), $this->content() + ['email' => 'qa@example.com'])->assertOk();
        Mail::assertSent(BrandedPlatformMail::class, fn ($m) => $m->hasTo('qa@example.com') && str_starts_with($m->envelope()->subject, '[Test]'));
        $this->assertSame(0, NotificationEvent::count(), 'no business event, no notification');
        $this->assertSame(0, PlatformEmailTemplate::count(), 'preview and test never save');
        $this->postJson($this->url('test'), $this->content() + ['email' => 'not-an-email'])->assertJsonValidationErrors('email');
    }

    public function test_copy_to_the_other_language_creates_an_unpublished_draft(): void
    {
        $this->actingAs($this->superAdmin)->postJson($this->url('duplicate'), ['to' => 'ar'])->assertOk();
        $ar = PlatformEmailTemplate::where('locale', 'ar')->firstOrFail();
        $this->assertNotNull($ar->draft);
        $this->assertNull($ar->content, 'customers keep getting the built-in Arabic until it is published');
    }

    public function test_account_emails_and_branding_follow_the_administrator(): void
    {
        $this->actingAs($this->superAdmin)->putJson(route('admin.platform-emails.branding.update'), [
            'app_name' => 'Socialeaz', 'sender_name' => 'Socialeaz Alerts', 'sender_email' => 'alerts@socialeaz.com', 'reply_to' => 'help@socialeaz.com',
            'support_email' => 'help@socialeaz.com', 'primary_color' => '#112233', 'secondary_color' => '#161B2B', 'social' => ['x' => 'https://x.com/socialeaz', 'tiktok' => 'javascript:bad'],
        ])->assertRedirect();
        $this->postJson($this->url('publish', 'auth.password_reset'), array_replace(app(TemplateRegistry::class)->defaults('auth.password_reset', 'en'),
            ['subject' => 'Reset for {{ customer_name }}']))->assertOk();

        // The reset notification builds its email exactly as the mail channel would send it.
        $m = (new \App\Notifications\Auth\BrandedResetPassword('reset-token-123'))->toMail($this->owner);
        $html = $m->render();
        $this->assertInstanceOf(BrandedPlatformMail::class, $m);
        $this->assertTrue($m->hasTo('rawda@example.com'));
        $this->assertSame('Reset for Rawda', $m->envelope()->subject);
        $this->assertSame('Socialeaz Alerts', $m->envelope()->from->name);
        $this->assertStringContainsString('#112233', $html, 'branding colour');
        $this->assertStringContainsString('reset-token-123', $html, 'the real reset link');
        $this->assertStringContainsString('https://x.com/socialeaz', $html);
        $this->assertStringNotContainsString('javascript:bad', $html, 'unsafe social link dropped');
    }

    public function test_create_walks_module_event_language_into_the_editor(): void
    {
        $this->actingAs($this->owner)->get(route('admin.platform-emails.create'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('admin.platform-emails.index'))->assertSee(route('admin.platform-emails.create'), false);
        $this->get(route('admin.platform-emails.create'))->assertOk()->assertSee('publishing.post_failed')->assertSee('auth.verify_email');

        $this->post(route('admin.platform-emails.create.start'), ['key' => 'publishing.post_failed', 'locale' => 'ar'])
            ->assertRedirect(route('admin.platform-emails.edit', ['key' => 'publishing.post_failed', 'locale' => 'ar']));
        $this->post(route('admin.platform-emails.create.start'), ['key' => 'made.up_event', 'locale' => 'en'])->assertNotFound();
        $this->assertSame(0, PlatformEmailTemplate::count(), 'choosing an event stores nothing until the admin saves');
    }
}
