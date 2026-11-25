<?php

namespace Tests\Feature\Notifications;

use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\NotificationEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

/** SUP-01/02/03/04/07 through the real ticket routes (one ticket model: Ticket / TicketMessage). */
class SupportNotificationsTest extends TestCase
{
    use CreatesSupportModuleTables;

    public function test_ticket_lifecycle_notifies_the_customer_and_staff_without_leaking_billing_replies(): void
    {
        $this->createSupportModuleTables();
        (require database_path('migrations/2026_11_23_100000_create_platform_notification_tables.php'))->up();
        (require database_path('migrations/2026_11_24_100000_create_platform_email_template_tables.php'))->up();
        foreach (['2026_08_17_233010_create_bundles_table', '2026_10_18_140000_create_seller_team_members'] as $m) {
            \Illuminate\Support\Facades\Schema::hasTable($m === '2026_08_17_233010_create_bundles_table' ? 'bundles' : 'seller_team_members') || (require database_path("migrations/$m.php"))->up();
        }
        $this->withoutMiddleware([\Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class, \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class]);
        Mail::fake();
        $seller = User::factory()->create(['email' => 'seller@example.com']);
        $seller->assignRole(Role::findOrCreate('seller', 'web'));
        $agent = User::factory()->create(['email' => 'agent@example.com']);
        $agent->assignRole(Role::findOrCreate('customer_support', 'web'));
        Role::findOrCreate('admin', 'web');

        $this->actingAs($seller)->postJson(route('admin.tickets.store'), ['subject' => 'Refund please', 'category' => 'billing', 'priority' => 'high', 'body' => 'Card 4242 charged twice'])->assertOk();
        $ticket = Ticket::firstOrFail();
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('seller@example.com') && str_contains($m->envelope()->subject, "We've received your request"));
        Mail::assertSent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('agent@example.com') && str_contains($m->envelope()->subject, 'New ticket'));

        $this->actingAs($agent)->patchJson(route('admin.tickets.status', $ticket), ['status' => 'in_progress', 'assigned_to' => $agent->id])->assertOk();
        $this->postJson(route('admin.tickets.messages.store', $ticket), ['body' => 'Refund issued to card ending 4242'])->assertOk();
        $this->postJson(route('admin.tickets.messages.store', $ticket), ['body' => 'internal: check ledger', 'is_internal_note' => true])->assertOk();
        $this->patchJson(route('admin.tickets.status', $ticket), ['status' => 'resolved'])->assertOk();

        $this->assertSame(['support.ticket_received', 'support.ticket_new', 'support.ticket_assigned', 'support.reply_received', 'support.ticket_resolved'],
            NotificationEvent::orderBy('id')->pluck('event')->all(), 'internal notes raise nothing');
        $this->assertSame(0, \App\Models\Notifications\UserNotification::where('event', 'support.ticket_assigned')->count(), 'an agent assigning themselves is not told about it');
        $this->travel(6)->minutes(); // grouped events: sent by the every-minute task after their window
        $this->artisan('notifications:send-due')->assertSuccessful();
        $reply = Mail::sent(PlatformNotificationMail::class, fn ($m) => $m->hasTo('seller@example.com') && str_contains($m->envelope()->subject, 'New reply'))->first();
        $this->assertStringNotContainsString('4242', $reply->render(), 'billing replies are read in the app, not in the inbox');
    }
}
