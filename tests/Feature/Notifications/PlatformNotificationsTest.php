<?php

namespace Tests\Feature\Notifications;

use App\Jobs\Notifications\SendNotificationEmail;
use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\EmailSuppression;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use App\Services\Notifications\NotificationCatalogue;
use App\Services\Notifications\NotificationText;
use App\Services\Notifications\Notify;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** The core pipeline: catalogue, recipients, preferences, dedup, grouping, quiet hours, suppression, locale. */
class PlatformNotificationsTest extends TestCase
{
    use CreatesNotificationTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        Mail::fake();
    }

    private function failedPost(array $params = [], string $dedup = '1'): ?NotificationEvent
    {
        return Notify::event('publishing.post_failed', ['workspace' => $this->owner->id, 'dedup' => $dedup,
            'params' => $params + ['platform' => 'Instagram', 'excerpt' => 'Spring offer', 'reason' => 'Bad ratio'], 'url' => '/admin/posts']);
    }

    /** Grouped events wait for their window; the every-minute task then sends them. */
    private function sendDue(int $minutes = 6): void
    {
        $this->travel($minutes)->minutes();
        $this->artisan('notifications:send-due')->assertSuccessful();
    }

    public function test_every_catalogue_event_has_english_and_arabic_wording(): void
    {
        foreach (array_keys(app(NotificationCatalogue::class)->all()) as $event) {
            foreach (['en', 'ar'] as $locale) {
                $lines = NotificationText::eventLines($event, $locale);
                $this->assertNotEmpty($lines['title'] ?? null, "$event has no $locale title");
                $this->assertNotSame($event, NotificationText::label($event, $locale), "$event has no $locale settings label");
            }
            $this->assertArrayHasKey($event, \Illuminate\Support\Facades\Lang::get('notifications.events', [], 'ar'), "$event missing in lang/ar");
        }
    }

    public function test_recipients_follow_module_permissions_and_exclude_the_actor(): void
    {
        Notify::event('publishing.post_failed', ['workspace' => $this->owner->id, 'actor' => $this->publisher->id, 'dedup' => 'x',
            'params' => ['platform' => 'X', 'excerpt' => 'e', 'reason' => 'r']]);
        Mail::assertSentCount(0); // grouped event: held for its 5-minute window
        $this->sendDue();

        $told = UserNotification::pluck('user_id')->sort()->values()->all();
        // Owner + admin (full permissions); Sara caused it; Lina has no publishing access.
        $this->assertSame([$this->owner->id, $this->admin->id], $told);
        Mail::assertQueuedCount(0);
        Mail::assertSentCount(2);
    }

    public function test_the_same_event_twice_is_one_event_and_one_email_each(): void
    {
        $this->assertNotNull($this->failedPost());
        $this->assertNull($this->failedPost(), 'second emit with the same dedup key is ignored');
        $this->sendDue();
        $this->artisan('notifications:send-due'); // a second run sends nothing more

        $this->assertSame(1, NotificationEvent::count());
        $this->assertSame(1, UserNotification::where('user_id', $this->owner->id)->count());
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com'));
        Mail::assertSentCount(3); // owner, admin, publisher
    }

    public function test_a_retried_email_job_never_sends_twice(): void
    {
        Bus::fake([SendNotificationEmail::class]);
        $this->failedPost();
        $delivery = NotificationDelivery::where('channel', 'email')->where('user_id', $this->owner->id)->firstOrFail();
        $delivery->update(['scheduled_for' => now()]);

        (new SendNotificationEmail($delivery->id))->handle(app(\App\Services\Notifications\RecipientResolver::class));
        (new SendNotificationEmail($delivery->id))->handle(app(\App\Services\Notifications\RecipientResolver::class));

        Mail::assertSentCount(1);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_repeated_failures_in_the_window_become_one_grouped_email(): void
    {
        Bus::fake([SendNotificationEmail::class]);
        foreach ([1, 2, 3] as $i) {
            $this->failedPost(['excerpt' => "Post $i"], (string) $i);
        }
        $mine = NotificationDelivery::where('channel', 'email')->where('user_id', $this->owner->id)->orderBy('id')->get();
        $this->assertCount(3, $mine);
        $this->travel(6)->minutes();

        foreach ($mine as $delivery) {
            (new SendNotificationEmail($delivery->id))->handle(app(\App\Services\Notifications\RecipientResolver::class));
        }

        Mail::assertSent(PlatformNotificationMail::class, 1 + 0); // only for the owner in this loop
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->envelope()->subject === "Action required: 3 posts didn't publish");
        $this->assertSame(['grouped', 'grouped', 'sent'], $mine->map->fresh()->pluck('status')->sort()->values()->all());
        // One bell item that counts them too.
        $this->assertSame(3, UserNotification::where('user_id', $this->owner->id)->value('group_count'));
    }

    public function test_preferences_turn_email_off_but_mandatory_and_failure_safe_rules_hold(): void
    {
        $prefs = app(PreferenceResolver::class);
        $prefs->save($this->owner, 0, 'event', 'publishing.post_failed', false, 'off');
        $prefs->save($this->owner, 0, 'category', 'billing', false, 'off');

        $this->failedPost();
        Notify::event('billing.payment_failed', ['workspace' => $this->owner->id, 'dedup' => 'p', 'params' => ['plan' => 'Growth', 'amount' => 'SAR 1', 'reason' => 'x']]);

        $this->assertSame('skipped', NotificationDelivery::where('user_id', $this->owner->id)->where('channel', 'email')
            ->whereHas('notificationEvent', fn ($q) => $q->where('event', 'publishing.post_failed'))->value('status'));
        // Failure-safe: with email off, the bell item is kept even though in-app was switched off too.
        $this->assertTrue(UserNotification::where('user_id', $this->owner->id)->where('event', 'publishing.post_failed')->exists());
        // Mandatory billing alert ignores the category "off".
        $this->assertTrue(NotificationDelivery::where('user_id', $this->owner->id)->where('channel', 'email')
            ->whereHas('notificationEvent', fn ($q) => $q->where('event', 'billing.payment_failed'))->exists());
    }

    public function test_quiet_hours_hold_high_priority_and_digest_normal_but_never_delay_critical(): void
    {
        Bus::fake([SendNotificationEmail::class]);
        $this->travelTo(now('Asia/Riyadh')->setTime(23, 30)->utc());
        $this->owner->forceFill(['timezone' => 'Asia/Riyadh', 'notification_settings' => ['quiet_hours' => ['enabled' => true, 'start' => '22:00', 'end' => '08:00']]])->save();

        $this->failedPost(); // P1
        Notify::event('crm.deal_won', ['workspace' => $this->owner->id, 'users' => [$this->owner->id], 'dedup' => 'w', 'params' => ['name' => 'Sofa', 'value' => 'SAR 1']]); // P2
        Notify::event('billing.subscription_expired', ['workspace' => $this->owner->id, 'dedup' => 'e', 'params' => ['plan' => 'Growth']]); // P0

        $byEvent = NotificationDelivery::with('notificationEvent')->where('user_id', $this->owner->id)->where('channel', 'email')->get()->keyBy(fn ($d) => $d->notificationEvent->event);
        $this->assertSame('queued', $byEvent['publishing.post_failed']->status);
        $this->assertSame('08:00', $byEvent['publishing.post_failed']->scheduled_for->setTimezone('Asia/Riyadh')->format('H:i'));
        $this->assertSame('digested', $byEvent['crm.deal_won']->status);
        $this->assertTrue($byEvent['billing.subscription_expired']->scheduled_for->lte(now()->addMinute()));
    }

    public function test_suppressed_addresses_are_never_sent_to(): void
    {
        EmailSuppression::add('rawda@example.com', 'hard_bounce', 'test');
        $this->failedPost();
        $this->sendDue();

        $this->assertSame('suppressed', NotificationDelivery::where('user_id', $this->owner->id)->where('channel', 'email')->value('status'));
        Mail::assertNotSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com'));
    }

    public function test_each_reader_gets_their_own_language_whoever_acted(): void
    {
        app()->setLocale('en');
        $this->failedPost();
        $this->sendDue();

        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('omar@example.com') && $m->envelope()->subject === 'إجراء مطلوب: لم يتم نشر منشورك المجدول');
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && $m->envelope()->subject === "Action required: your scheduled post didn't publish");
        $html = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('omar@example.com'))->first()->render();
        $this->assertStringContainsString('dir="rtl"', $html);
    }

    public function test_credentials_never_reach_the_stored_payload(): void
    {
        Notify::event('security.api_token_created', ['users' => [$this->owner->id], 'dedup' => 't',
            'params' => ['name' => 'Zapier', 'access_token' => 'secret-1', 'refresh_token' => 'secret-2', 'password' => 'hunter2']]);

        $this->assertSame(['name' => 'Zapier'], NotificationEvent::firstOrFail()->payload['params']);
    }

    public function test_notifications_are_not_raised_from_inside_notification_jobs(): void
    {
        Notify::$processing = true;
        $this->assertNull($this->failedPost());
        Notify::$processing = false;
        $this->assertSame(0, NotificationEvent::count());
    }

    public function test_unsubscribe_link_only_on_optional_mail_and_signed(): void
    {
        $this->failedPost();
        Notify::event('billing.payment_failed', ['workspace' => $this->owner->id, 'dedup' => 'p', 'params' => ['plan' => 'G', 'amount' => '1', 'reason' => 'x']]);
        $this->sendDue(11); // payment_failed waits 10 minutes (EventGuards re-checks it is still unpaid)

        $optional = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && str_contains($m->envelope()->subject, 'post'))->first();
        $mandatory = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && str_contains($m->envelope()->subject, 'payment'))->first();
        $this->assertArrayHasKey('List-Unsubscribe', $optional->headers()->text);
        $this->assertArrayNotHasKey('List-Unsubscribe', $mandatory->headers()->text);

        $url = trim($optional->headers()->text['List-Unsubscribe'], '<>');
        $this->post($url)->assertOk();
        $this->assertSame('off', app(PreferenceResolver::class)->for($this->owner->fresh(), app(NotificationCatalogue::class)->get('publishing.post_failed'), $this->owner->id)['email']);
        $this->post($url.'x')->assertForbidden();
    }
}
