<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Emitters\BillingNotifications;
use App\Services\Notifications\Emitters\ConnectionNotifications;
use App\Services\Notifications\Emitters\ModuleNotifications;
use App\Services\Notifications\Emitters\PublishingNotifications;
use App\Services\Notifications\Emitters\SecurityNotifications;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Where modules meet the notification system. Each hook only notices a state change and
 * emits an event (one outbox row + a queued job); nothing here sends mail or blocks the
 * save, webhook or request that caused it. Every hook is isolated: a notification problem
 * is logged and never breaks the business action.
 */
class NotificationHooks
{
    public static function register(): void
    {
        $safe = fn (callable $fn) => function (...$args) use ($fn) {
            try {
                $fn(...$args);
            } catch (\Throwable $e) {
                Log::warning('Notification hook skipped.', ['error' => $e->getMessage()]);
            }
        };

        // Security & account
        \App\Models\User::updated($safe(fn ($user) => SecurityNotifications::userUpdated($user)));
        Event::listen(Login::class, $safe(fn (Login $e) => $e->user instanceof \App\Models\User ? SecurityNotifications::login($e->user) : null));
        \Laravel\Sanctum\PersonalAccessToken::created($safe(fn ($token) => SecurityNotifications::tokenCreated($token)));
        \Laravel\Sanctum\PersonalAccessToken::deleted($safe(fn ($token) => SecurityNotifications::tokenDeleted($token)));

        // Connections and publishing (any writer of the status)
        \App\Models\SocialConnection::updated($safe(fn ($c) => $c->wasChanged('status') ? ConnectionNotifications::statusChanged($c, $c->getOriginal('status')) : null));
        \App\Models\Post::updated($safe(fn ($post) => $post->wasChanged('status') ? PublishingNotifications::statusChanged($post) : null));

        // Billing (server-verified transaction state only)
        \App\Models\WalletTransaction::updated($safe(fn ($tx) => BillingNotifications::transactionUpdated($tx)));

        // Inbox & comments
        \App\Models\Messaging\Conversation::updated($safe(fn ($c) => ModuleNotifications::conversationUpdated($c)));
        \App\Models\Messaging\Message::created($safe(fn ($m) => ModuleNotifications::messageCreated($m)));
        \App\Models\Messaging\Message::updated($safe(fn ($m) => ModuleNotifications::messageUpdated($m)));
        \App\Models\PostComment::created($safe(fn ($c) => ModuleNotifications::commentCreated($c)));

        // Support
        \App\Models\Ticket::created($safe(fn ($t) => ModuleNotifications::ticketCreated($t)));
        \App\Models\Ticket::updated($safe(fn ($t) => ModuleNotifications::ticketUpdated($t)));
        \App\Models\TicketMessage::created($safe(fn ($m) => ModuleNotifications::ticketMessageCreated($m)));

        // Ads, email marketing, automations
        \App\Models\Admin\AdBuilderRun::updated($safe(fn ($r) => ModuleNotifications::adBuildUpdated($r)));
        \App\Models\AdReportSchedule::updated($safe(fn ($s) => ModuleNotifications::adReportUpdated($s)));
        \App\Models\EmailMarketing\EmailCampaign::updated($safe(fn ($c) => ModuleNotifications::campaignUpdated($c)));
        \App\Models\Crm\AutomationRun::saved($safe(fn ($r) => ModuleNotifications::automationRunSaved($r)));

        // Events raised inside a rolled-back transaction go out once the unit of work ends.
        app()->terminating(fn () => Notify::flush());
        Event::listen(JobProcessed::class, fn () => Notify::flush());
        Event::listen(JobFailed::class, fn () => Notify::flush());
        Event::listen(CommandFinished::class, fn () => Notify::flush());
    }
}
