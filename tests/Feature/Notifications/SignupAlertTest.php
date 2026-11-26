<?php

namespace Tests\Feature\Notifications;

use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\NotificationEvent;
use App\Models\User;
use App\Services\Notifications\Emitters\SignupNotifications;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** Every self-service signup emails the team (support@socialeaz.com) with the new customer's details. */
class SignupAlertTest extends TestCase
{
    use CreatesNotificationTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'mobile')) { // legacy column, not in the test schema
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->string('mobile')->nullable());
        }
        Mail::fake();
    }

    public function test_signup_through_the_register_form_alerts_support_with_the_customer_details(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('subscription_cycles')) {
            (require database_path('migrations/2026_08_18_000800_create_subscription_cycles_table.php'))->up();
        }
        \Illuminate\Support\Facades\DB::table('bundles')->insert(['slug' => 'free-trial', 'name_en' => 'Free Trial', 'name_ar' => 'تجربة مجانية', 'price' => 0,
            'is_free' => true, 'is_active' => true, 'meta' => json_encode(['trial_days' => 14]), 'created_at' => now(), 'updated_at' => now()]);
        $this->post(route('register'), [
            'name' => 'Sara Ahmed', 'email' => 'sara.signup@example.com', 'mobile' => '+966551234567',
            'password' => 'Secret-Pass-2026!', 'password_confirmation' => 'Secret-Pass-2026!',
        ], ['User-Agent' => 'Mozilla/5.0 (Macintosh; Mac OS X 14_0) Chrome/130.0 Safari/537.36'])
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'sara.signup@example.com')->firstOrFail();
        $event = NotificationEvent::where('event', 'support.new_signup')->firstOrFail();
        $this->assertSame(['support@socialeaz.com'], $event->payload['emails']);
        $this->assertSame('+966551234567', $event->payload['params']['mobile']);
        $this->assertSame('#'.$user->id, $event->payload['params']['account_id']);
        $this->assertSame('Chrome · macOS', $event->payload['params']['device']);
        $this->assertSame('Free Trial', $event->payload['params']['plan']);

        Mail::assertSent(PlatformNotificationMail::class, function (PlatformNotificationMail $mail) {
            $html = $mail->render();

            // Not "from yourself" (Hostinger files that as spam); Reply reaches the customer.
            return $mail->hasTo('support@socialeaz.com') && $mail->hasFrom('alerts@socialeaz.com')
                && $mail->hasReplyTo('sara.signup@example.com') && ! $mail->hasFrom('support@socialeaz.com')
                && ! str_contains($mail->envelope()->subject, '🎉')
                && str_contains($mail->envelope()->subject, 'Sara Ahmed')
                && str_contains($html, 'sara.signup@example.com') && str_contains($html, '+966551234567')
                && str_contains($html, 'New customer') && str_contains($html, 'Free Trial') && str_contains($html, 'mailto:sara.signup@example.com');
        });
    }

    public function test_the_addresses_come_from_config_and_an_empty_list_turns_it_off(): void
    {
        $user = User::factory()->create(['name' => 'Omar', 'email' => 'omar.signup@example.com']);

        config(['notifications.staff_alerts.signup' => 'Ops@Socialeaz.com, not-an-email, sales@socialeaz.com']);
        $this->assertSame(['ops@socialeaz.com', 'sales@socialeaz.com'], SignupNotifications::recipients());

        config(['notifications.staff_alerts.signup' => '']);
        SignupNotifications::registered($user);
        $this->assertFalse(NotificationEvent::where('event', 'support.new_signup')->exists());
        Mail::assertNothingSent();
    }

    public function test_the_alert_is_sent_once_per_account_in_arabic_too(): void
    {
        $user = User::factory()->create(['name' => 'نورة', 'email' => 'noura.signup@example.com']);
        app()->setLocale('ar');
        SignupNotifications::registered($user);
        SignupNotifications::registered($user); // repeated call: same dedup key

        $this->assertSame(1, NotificationEvent::where('event', 'support.new_signup')->count());
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }
}
