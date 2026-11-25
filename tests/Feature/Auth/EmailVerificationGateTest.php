<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** New sign-ups see the verification popup and can't use the app until their email is confirmed. */
class EmailVerificationGateTest extends TestCase
{
    use CreatesNotificationTables;

    private User $newSeller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        Mail::fake();
        config(['notifications.verification_since' => now()->subDay()->toDateString()]);
        Route::middleware(['web', 'auth'])->get('/_gate-app', fn () => 'app page');
        Route::middleware(['web'])->get('/_gate-public', fn () => 'public page');

        $this->newSeller = User::factory()->unverified()->create(['name' => 'New', 'email' => 'new@example.com']);
        $this->newSeller->assignRole('seller');
    }

    public function test_an_unverified_new_seller_is_sent_to_the_verification_popup(): void
    {
        $this->actingAs($this->newSeller)->get('/_gate-app')->assertRedirect(route('verification.notice'));
        $this->getJson('/_gate-app')->assertForbidden()->assertJsonPath('redirect', route('verification.notice'));
        $this->get('/_gate-public')->assertOk()->assertSee('public page');

        $this->get(route('verification.notice'))->assertOk()
            ->assertSee('id="email-verification"', false)->assertSee('ne***@example.com')->assertDontSee('new@example.com');
        $this->getJson(route('verification.notice'))->assertOk()->assertJsonPath('verified', false);
    }

    public function test_resend_works_while_locked(): void
    {
        Notification::fake();
        $this->actingAs($this->newSeller)->postJson(route('verification.send'))->assertOk()->assertJsonPath('wait', 60);
        Notification::assertSentTo($this->newSeller, \App\Notifications\Auth\BrandedVerifyEmail::class);
    }

    public function test_the_verification_link_unlocks_the_app_and_continues_to_connections(): void
    {
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $this->newSeller->id, 'hash' => sha1($this->newSeller->email)]);

        $this->actingAs($this->newSeller)->get($link)
            ->assertRedirect(route('admin.connections.index'))->assertSessionHas('connect_notice.type', 'success');
        $this->assertTrue($this->newSeller->fresh()->hasVerifiedEmail());
        $this->get('/_gate-app')->assertOk()->assertSee('app page');
        $this->getJson(route('verification.notice'))->assertJsonPath('verified', true);
    }

    public function test_older_accounts_staff_and_invited_teammates_are_never_blocked(): void
    {
        $old = User::factory()->unverified()->create(['created_at' => now()->subYear()]);
        $old->assignRole('seller');
        $staff = User::factory()->unverified()->create(); // staff account created by an administrator
        $staff->assignRole(\Spatie\Permission\Models\Role::findOrCreate('customer_support', 'web'));

        $this->actingAs($old)->get('/_gate-app')->assertOk();
        $this->actingAs($staff)->get('/_gate-app')->assertOk();
        // Invited teammate (created verified): may be sent to pick a workspace, never to the verification popup.
        $this->assertNotSame(route('verification.notice'), $this->actingAs($this->publisher)->get('/_gate-app')->headers->get('Location'));
    }
}
