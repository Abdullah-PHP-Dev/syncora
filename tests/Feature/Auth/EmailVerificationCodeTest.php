<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Auth\BrandedVerifyEmail;
use App\Services\Auth\EmailVerification;
use App\Services\PlatformEmails\TemplateRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** Six-digit code + magic link: issuing, both ways to verify, expiry, limits, templates. */
class EmailVerificationCodeTest extends TestCase
{
    use CreatesNotificationTables;

    private User $user;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        Mail::fake();
        config(['notifications.verification_since' => now()->subDay()->toDateString()]);
        $this->user = User::factory()->unverified()->create(['name' => 'Huda', 'email' => 'huda@example.com', 'locale' => 'en']);
        $this->user->assignRole('seller');
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(Role::findOrCreate('admin', 'web'));
    }

    /** Sends the email and returns [code, link, html] from what the person receives. */
    private function sendAndRead(?User $user = null): array
    {
        $user ??= $this->user;
        Notification::fake();
        $user->sendEmailVerificationNotification();
        $html = null;
        Notification::assertSentTo($user, BrandedVerifyEmail::class, function ($n) use ($user, &$html) {
            $html = $n->toMail($user)->render();

            return true;
        });
        preg_match('#letter-spacing:10px;[^>]*>(\d{6})<#', $html, $code);
        preg_match('#href="([^"]*/email/verify/link/[A-Za-z0-9]{64})"#', $html, $link);

        // The email links to APP_URL in the reader's language; tests address the same route locally.
        $token = basename(html_entity_decode($link[1] ?? ''));

        return [$code[1] ?? null, $token !== '' ? route('verification.link', ['token' => $token]) : '', $html];
    }

    public function test_the_email_carries_a_code_and_a_link_but_only_hashes_are_stored(): void
    {
        [$code, $link, $html] = $this->sendAndRead();

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->assertNotSame('', $link);
        $this->assertStringContainsString('Verify My Email', $html);
        $this->assertStringContainsString('expires after 10 minutes', $html);
        $row = EmailVerificationCode::firstOrFail();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row->getAttributes()));
        $this->assertStringNotContainsString(basename($link), json_encode($row->getAttributes()));
    }

    public function test_the_right_code_verifies_and_kills_the_link(): void
    {
        [$code, $link] = $this->sendAndRead();

        $this->actingAs($this->user)->postJson(route('verification.code'), ['code' => $code])
            ->assertOk()->assertJsonPath('verified', true)->assertJsonPath('redirect', route('admin.connections.index'));
        $this->assertTrue($this->user->fresh()->hasVerifiedEmail());
        $this->assertSame(0, EmailVerificationCode::count());
        // The other credential no longer works; verifying again is harmless.
        $this->get($link)->assertOk()->assertSee('already verified');
        $this->postJson(route('verification.code'), ['code' => $code])->assertOk()->assertJsonPath('verified', true);
    }

    public function test_wrong_codes_are_counted_and_lock_after_five(): void
    {
        [$code] = $this->sendAndRead();
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->actingAs($this->user);

        foreach (range(1, 4) as $i) {
            $this->postJson(route('verification.code'), ['code' => $wrong])->assertStatus(422)->assertJsonPath('error', 'invalid');
        }
        $this->postJson(route('verification.code'), ['code' => $wrong])->assertStatus(422)->assertJsonPath('error', 'locked');
        // Even the right code is refused now: a new email is needed.
        $this->postJson(route('verification.code'), ['code' => $code])->assertStatus(422)->assertJsonPath('error', 'locked');
        $this->assertFalse($this->user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_code_is_refused(): void
    {
        [$code] = $this->sendAndRead();
        $this->travel(EmailVerification::CODE_MINUTES + 1)->minutes();

        $this->actingAs($this->user)->postJson(route('verification.code'), ['code' => $code])
            ->assertStatus(422)->assertJsonPath('error', 'expired')->assertJsonPath('message', 'Your verification code has expired. Press Resend Code to get a new one.');
    }

    public function test_resend_replaces_the_code_and_waits_sixty_seconds(): void
    {
        [$old] = $this->sendAndRead();
        $this->actingAs($this->user)->postJson(route('verification.send'))->assertStatus(429)->assertJsonPath('ok', false);

        $this->travel(61)->seconds();
        Notification::fake();
        $this->postJson(route('verification.send'))->assertOk()->assertJsonPath('wait', 60);
        $new = null;
        Notification::assertSentTo($this->user, BrandedVerifyEmail::class, function ($n) use (&$new) {
            preg_match('#letter-spacing:10px;[^>]*>(\d{6})<#', $n->toMail($this->user)->render(), $m);
            $new = $m[1];

            return true;
        });
        if ($old !== $new) {
            $this->postJson(route('verification.code'), ['code' => $old])->assertStatus(422)->assertJsonPath('error', 'invalid');
        }
        $this->postJson(route('verification.code'), ['code' => $new])->assertOk();
    }

    public function test_opening_the_link_never_verifies_only_confirming_does(): void
    {
        [, $link] = $this->sendAndRead();

        // A mail scanner (no session) opens it twice: nothing changes.
        $this->get($link)->assertOk()->assertSee('Confirm my email')->assertSee('hu***@example.com');
        $this->get($link)->assertOk();
        $this->assertFalse($this->user->fresh()->hasVerifiedEmail());

        // The person confirms on another device (not signed in): verified, asked to sign in.
        $this->post($link)->assertOk()->assertSee('Email verified successfully!')->assertSee(route('login'), false);
        $this->assertTrue($this->user->fresh()->hasVerifiedEmail());
        // Used again: harmless.
        $this->post($link)->assertOk()->assertSee('no longer valid');
    }

    public function test_confirming_while_signed_in_continues_to_onboarding(): void
    {
        [, $link] = $this->sendAndRead();

        $this->actingAs($this->user)->post($link)->assertRedirect(route('admin.connections.index'))->assertSessionHas('connect_notice.type', 'success');
    }

    public function test_an_expired_or_tampered_link_is_refused(): void
    {
        [, $link] = $this->sendAndRead();
        $this->get(substr($link, 0, -1).'x')->assertOk()->assertSee('no longer valid');
        $this->travel(EmailVerification::LINK_MINUTES + 1)->minutes();
        $this->post($link)->assertOk()->assertSee('This link has expired');
        $this->assertFalse($this->user->fresh()->hasVerifiedEmail());
    }

    public function test_a_code_only_verifies_its_own_account(): void
    {
        [$code] = $this->sendAndRead();
        $other = User::factory()->unverified()->create(['email' => 'other@example.com']);
        $other->assignRole('seller');
        $this->sendAndRead($other);

        $this->actingAs($other)->postJson(route('verification.code'), ['code' => $code]);
        $this->assertFalse($this->user->fresh()->hasVerifiedEmail());
    }

    public function test_changing_the_email_sends_a_new_code_to_the_new_address(): void
    {
        [$old] = $this->sendAndRead();
        $this->travel(61)->seconds();
        Notification::fake();

        $this->actingAs($this->user)->postJson(route('verification.change-email'), ['email' => 'huda.new@example.com'])
            ->assertOk()->assertJsonPath('maskedEmail', 'hu******@example.com');
        $this->assertSame('huda.new@example.com', $this->user->fresh()->email);
        Notification::assertSentTo($this->user->fresh(), BrandedVerifyEmail::class);
        $this->postJson(route('verification.code'), ['code' => $old])->assertStatus(422);
        $this->postJson(route('verification.change-email'), ['email' => $this->superAdmin->email])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_admin_template_is_what_people_receive_and_must_keep_the_code_and_link(): void
    {
        $url = fn (string $action) => route('admin.platform-emails.'.$action, ['key' => 'auth.verify_email', 'locale' => 'en']);
        $content = app(TemplateRegistry::class)->defaults('auth.verify_email', 'en');
        $this->assertSame([], \App\Services\PlatformEmails\TemplateValidator::missingRequired('auth.verify_email', $content));

        // Publishing without the code is refused.
        $broken = $content;
        $broken['blocks'] = array_values(array_filter($content['blocks'], fn ($b) => $b['type'] !== 'code'));
        $this->actingAs($this->superAdmin)->postJson($url('publish'), $broken)->assertStatus(422)->assertJsonValidationErrors('variables');

        $content['subject'] = 'Confirm {{ customer_name }} – code {{ verification_otp }}';
        $this->postJson($url('publish'), $content)->assertOk();
        [$code, , $html] = $this->sendAndRead();
        $this->assertStringContainsString("Confirm Huda – code {$code}", $html);

        // Arabic readers keep the Arabic default (RTL), never the English custom text.
        $this->user->forceFill(['locale' => 'ar'])->save();
        $this->travel(61)->seconds();
        [, , $ar] = $this->sendAndRead($this->user->fresh());
        $this->assertStringContainsString('dir="rtl"', $ar);
        $this->assertStringContainsString('تأكيد بريدي الإلكتروني', $ar);
    }

    public function test_the_screen_renders_in_english_and_arabic(): void
    {
        $this->sendAndRead();
        $this->actingAs($this->user)->get(route('verification.notice'))->assertOk()
            ->assertSee('dir="ltr"', false)->assertSee('Verify your email address')->assertSee('hu***@example.com');
        app()->setLocale('ar');
        $this->get(route('verification.notice'))
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee('تحقق من بريدك الإلكتروني');
    }

    public function test_masking(): void
    {
        $this->assertSame('ab******@example.com', EmailVerification::mask('abdullah@example.com'));
        $this->assertSame('a***@x.io', EmailVerification::mask('ab@x.io'));
    }
}
