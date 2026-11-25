<?php

namespace Tests\Feature\Notifications;

use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\WalletTransaction;
use App\Services\Notifications\Emitters\TeamNotifications;
use App\Services\Notifications\Emitters\UsageNotifications;
use App\Services\Notifications\NotificationScanner;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Notifications\Concerns\CreatesNotificationTables;
use Tests\TestCase;

/** Module events reach the platform from the real state changes. */
class NotificationEmittersTest extends TestCase
{
    use CreatesNotificationTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNotificationTables();
        Mail::fake();
    }

    private function events(): array
    {
        return NotificationEvent::orderBy('id')->pluck('event')->all();
    }

    private function makePost(array $attributes): Post
    {
        // Inserted directly: creating posts through the composer runs plan allowances, not under test here.
        $id = \Illuminate\Support\Facades\DB::table('posts')->insertGetId($attributes + ['user_id' => $this->owner->id, 'content' => 'Spring offer', 'status' => 'publishing', 'created_at' => now(), 'updated_at' => now()]);

        return Post::findOrFail($id);
    }

    public function test_a_multi_platform_post_is_reported_once_when_every_channel_finished(): void
    {
        $ig = $this->makePost(['platform' => 'instagram', 'group_id' => 'g1']);
        $fb = $this->makePost(['platform' => 'facebook', 'group_id' => 'g1']);
        $li = $this->makePost(['platform' => 'linkedin', 'group_id' => 'g1']);

        $ig->update(['status' => 'completed']);
        $fb->update(['status' => 'failed', 'error_message' => 'Video too long']);
        $this->assertSame([], $this->events(), 'nothing until the last channel finishes');
        $li->update(['status' => 'completed']);

        $this->assertSame(['publishing.partially_published'], $this->events());
        $event = NotificationEvent::firstOrFail();
        $this->assertSame(['succeeded' => 2, 'total' => 3, 'failed' => 'Facebook'], array_intersect_key($event->payload['params'], array_flip(['succeeded', 'total', 'failed'])));
    }

    public function test_an_interrupted_post_is_never_called_failed(): void
    {
        $post = $this->makePost(['platform' => 'linkedin']);
        $post->update(['status' => 'failed', 'error_message' => 'Publishing was interrupted. Check the platform before posting it again.']);
        $expired = $this->makePost(['platform' => 'instagram']);
        $expired->update(['status' => 'failed', 'error_message' => 'Error validating access token: Session has expired']);

        $this->assertSame(['publishing.post_interrupted', 'publishing.post_failed_reconnect'], $this->events());
        $this->travel(6)->minutes(); // grouped events: sent by the every-minute task after their window
        $this->artisan('notifications:send-due')->assertSuccessful();
        $text = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com'))->first()->render();
        $this->assertStringContainsString('before posting again', $text);
    }

    public function test_receipts_and_payment_failures_come_from_verified_transaction_state(): void
    {
        $tx = WalletTransaction::create(['transaction_no' => 'TX-1', 'seller_id' => $this->owner->id, 'amount' => 199, 'category' => 'subscription',
            'status' => 'pending', 'meta' => ['bundle_name' => 'Growth']]);
        $tx->update(['status' => 'completed']);
        $this->assertSame([], $this->events(), 'completed but not yet fulfilled: no receipt');
        $tx->update(['fulfilled_at' => now()]);
        $declined = WalletTransaction::create(['transaction_no' => 'TX-2', 'seller_id' => $this->owner->id, 'amount' => 199, 'category' => 'subscription', 'status' => 'pending', 'meta' => []]);
        $declined->update(['status' => 'rejected', 'remarks' => 'Card declined']);

        $this->assertSame(['billing.payment_succeeded', 'billing.payment_failed'], $this->events());
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && $m->envelope()->subject === 'Payment received — your Growth plan is active');
        // Billing goes to the paying owner only, never the team.
        Mail::assertNotSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('omar@example.com'));
    }

    public function test_renewal_reminders_go_once_per_threshold(): void
    {
        $this->travelTo(now('Asia/Riyadh')->setTime(10, 0)->utc());
        Subscription::create(['user_id' => $this->owner->id, 'bundle_name' => 'Growth', 'status' => 'active', 'is_active' => true,
            'start_date' => today()->subMonth(), 'end_date' => today()->addDays(7)]);

        app(NotificationScanner::class)->billing();
        app(NotificationScanner::class)->billing();

        $this->assertSame(['billing.subscription_ending'], $this->events());
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && str_contains($m->render(), 'Your plan ends in 7 days'));
    }

    public function test_invitations_are_emailed_without_storing_the_one_time_link(): void
    {
        $row = \App\Models\SellerTeamMember::create(['owner_id' => $this->owner->id, 'email' => 'new@example.com', 'role' => 'member', 'permissions' => [],
            'expires_at' => now()->addDays(7), 'token_hash' => hash('sha256', 'secret-token')]);
        TeamNotifications::assigned($this->owner, 'new@example.com', collect([$row]), collect(), 'secret-token');

        $mail = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('new@example.com'))->first();
        $this->assertNotNull($mail);
        $this->assertStringContainsString('secret-token', $mail->render(), 'the email carries the accept link');
        $this->assertStringNotContainsString('secret-token', json_encode(NotificationEvent::firstOrFail()->payload), 'the database never does');
    }

    public function test_usage_threshold_fires_once_per_period(): void
    {
        $subscription = Subscription::create(['user_id' => $this->owner->id, 'status' => 'active', 'is_active' => true, 'start_date' => today()->subDays(3), 'end_date' => today()->addMonth()]);
        UsageNotifications::consumed($subscription, 'ai_credits', 70, 85, 100);
        UsageNotifications::consumed($subscription, 'ai_credits', 85, 90, 100);
        UsageNotifications::consumed($subscription, 'ai_credits', 90, 100, 100);

        $this->assertSame(['usage.threshold_reached', 'usage.limit_reached'], $this->events());
    }

    public function test_password_change_needs_the_current_password_and_sends_an_alert(): void
    {
        $this->owner->forceFill(['password' => bcrypt('old-password-1')])->saveQuietly();
        $url = route('admin.profiles.update', ['profile' => $this->owner->id]);

        $this->actingAs($this->owner)->putJson($url, ['name' => 'Rawda', 'password' => 'NewPassword-2026', 'password_confirmation' => 'NewPassword-2026'])
            ->assertJsonValidationErrors('current_password');
        $this->assertSame([], $this->events());

        $this->putJson($url, ['name' => 'Rawda', 'current_password' => 'old-password-1', 'password' => 'NewPassword-2026', 'password_confirmation' => 'NewPassword-2026'])->assertOk();
        $this->assertSame(['security.password_changed'], $this->events());
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('rawda@example.com') && $m->envelope()->subject === 'Your Socialeaz password was changed');
    }

    public function test_the_center_lists_reads_and_isolates_each_persons_notifications(): void
    {
        Notify::event('publishing.post_failed', ['workspace' => $this->owner->id, 'dedup' => '1', 'params' => ['platform' => 'X', 'excerpt' => 'e', 'reason' => 'r']]);
        $mine = UserNotification::where('user_id', $this->owner->id)->firstOrFail();
        $theirs = UserNotification::where('user_id', $this->admin->id)->firstOrFail();

        $feed = $this->actingAs($this->owner)->getJson(route('admin.notifications.feed'))->assertOk()->assertJsonPath('unread', 1);
        $this->assertSame("Your X post didn't publish", $feed->json('items.0.title'));
        $this->patchJson(route('admin.notifications.read', $theirs->id))->assertNotFound();
        $this->deleteJson(route('admin.notifications.dismiss', $theirs->id))->assertNotFound();
        $this->postJson(route('admin.notifications.read-all'))->assertOk();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at, 'reading mine never touches another person\'s');
        $this->getJson(route('admin.notifications.feed', ['filter' => 'unread']))->assertJsonCount(0, 'items');
    }

    public function test_settings_save_choices_but_refuse_to_turn_off_required_alerts(): void
    {
        $this->actingAs($this->owner)->putJson(route('admin.notifications.settings.update'), [
            'workspace' => 0, 'email_enabled' => '1', 'locale' => 'ar', 'timezone' => 'Asia/Riyadh',
            'events' => ['security.password_changed' => ['email' => 'off', 'in_app' => '0'], 'publishing.post_published' => ['email' => 'off', 'in_app' => '0'], 'not.an_event' => ['email' => 'off']],
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', ['user_id' => $this->owner->id, 'key' => 'publishing.post_published', 'email' => 'off']);
        $this->assertDatabaseMissing('notification_preferences', ['key' => 'security.password_changed']);
        $this->assertDatabaseMissing('notification_preferences', ['key' => 'not.an_event']);
        $this->assertSame('ar', $this->owner->fresh()->locale);
        // Overrides only for workspaces the person belongs to.
        $this->putJson(route('admin.notifications.settings.update'), ['workspace' => 999999])->assertNotFound();
    }

    public function test_email_links_open_only_for_their_recipient(): void
    {
        Notify::event('publishing.post_failed', ['workspace' => $this->owner->id, 'dedup' => '1', 'params' => ['platform' => 'X', 'excerpt' => 'e', 'reason' => 'r'], 'url' => '/admin/posts']);
        $event = NotificationEvent::firstOrFail();
        $link = \App\Services\Notifications\Links::email($event, $this->owner);

        $this->actingAs($this->admin)->get($link)->assertForbidden();
        $this->actingAs($this->owner)->get($link.'0')->assertForbidden();
        $this->actingAs($this->owner)->get($link)->assertRedirect();
        $this->assertNotNull(UserNotification::where('user_id', $this->owner->id)->value('read_at'));
    }

    public function test_the_center_and_settings_pages_render_in_both_languages(): void
    {
        foreach (['en' => 'Notification settings', 'ar' => 'إعدادات الإشعارات'] as $locale => $title) {
            app()->setLocale($locale);
            $this->actingAs($this->owner)->get(route('admin.notifications.settings'))->assertOk()->assertSee($title)
                ->assertSee('publishing.post_failed', false); // every optional event has a control
            $this->get(route('admin.notifications.center'))->assertOk();
        }
    }
}
