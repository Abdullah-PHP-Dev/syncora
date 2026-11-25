<?php

namespace App\Services\Notifications;

use App\Jobs\Notifications\SendNotificationEmail;
use App\Services\PlatformEmails\EmailTemplateSwitch;
use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fans one stored event out to its recipients (runs on the notifications queue):
 *
 *   recipients → preferences → bell item (grouped) → email: immediate | delayed | digest | skipped
 *
 * Every step is keyed by (event, recipient, channel), so a retried job never repeats
 * a bell item or an email (rules R1, R15). Quiet hours hold P1 email until morning and
 * move P2/P3 to the daily digest; P0 always goes out. More than a few P2 emails an hour
 * spill into the digest too.
 */
class NotificationService
{
    public function __construct(
        private NotificationCatalogue $catalogue,
        private RecipientResolver $recipients,
        private PreferenceResolver $preferences,
    ) {}

    public function process(NotificationEvent $event, array $transient = []): void
    {
        if ($event->processed_at) {
            return;
        }
        $config = $this->catalogue->get($event->event);
        $workspace = $event->workspace_id ? (int) $event->workspace_id : null;

        foreach ($this->recipients->resolve($event, $config) as $recipient) {
            $user = $recipient['user'];
            $choice = $user ? $this->preferences->for($user, $config, $workspace)
                : ['in_app' => false, 'email' => $config['email']];
            if ($user && $choice['in_app']) {
                $this->bell($event, $user, $config);
            }
            if ($recipient['email']) {
                $this->email($event, $recipient, $choice['email'], $config, $transient);
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
    }

    private function bell(NotificationEvent $event, User $user, array $config): void
    {
        if (! $this->claim($event, 'u:'.$user->id, 'in_app', ['user_id' => $user->id, 'status' => NotificationDelivery::SENT, 'sent_at' => now()])) {
            return;
        }
        $payload = $event->payload ?? [];
        // Stored as data (not text): rendered in the reader's language at display time.
        $data = array_filter(['params' => $payload['params'] ?? null, 'dates' => $payload['dates'] ?? null]);
        $groupKey = $config['group'] ? $event->event.':'.$event->workspace_id : null;
        if ($groupKey) {
            $open = UserNotification::where('user_id', $user->id)->where('group_key', $groupKey)->unread()
                ->where('updated_at', '>=', now()->subHours(6))->latest('id')->first();
            if ($open) {
                $open->forceFill(['group_count' => $open->group_count + 1, 'notification_event_id' => $event->id,
                    'params' => $data, 'url' => $payload['group_url'] ?? $open->url, 'updated_at' => now()])->save();

                return;
            }
        }
        UserNotification::create([
            'user_id' => $user->id, 'workspace_id' => $event->workspace_id, 'notification_event_id' => $event->id,
            'event' => $event->event, 'category' => $config['category'], 'priority' => $config['priority'],
            'params' => $data,
            'url' => $payload['url'] ?? null, 'group_key' => $groupKey,
        ]);
    }

    private function email(NotificationEvent $event, array $recipient, string $cadence, array $config, array $transient): void
    {
        $user = $recipient['user'];
        $workspace = $event->workspace_id ? (int) $event->workspace_id : null;
        $base = ['user_id' => $user?->id, 'to_address' => $recipient['email'], 'locale' => RecipientContext::locale($user, $workspace)];

        if ($cadence === 'off') {
            $this->claim($event, $recipient['key'], 'email', $base + ['status' => NotificationDelivery::SKIPPED, 'error' => 'preference']);

            return;
        }
        // Switched off by an administrator (Email Templates): no email, the bell item still stands.
        if (! EmailTemplateSwitch::enabled($event->event)) {
            $this->claim($event, $recipient['key'], 'email', $base + ['status' => NotificationDelivery::SKIPPED, 'error' => 'disabled by administrator']);

            return;
        }
        $when = now()->addMinutes((int) $config['delay']);
        if ($user && $cadence === 'immediate' && $config['priority'] >= 2 && $this->throttled($user)) {
            $cadence = 'daily';
        }
        if ($user && $cadence === 'immediate' && $config['priority'] > 0 && ($quietEnds = RecipientContext::quietUntil($user, $workspace))) {
            if ($config['priority'] >= 2) {
                $cadence = 'daily';
            } else {
                $when = $when->max($quietEnds->toMutable());
            }
        }
        if ($user && in_array($cadence, ['daily', 'weekly'], true)) {
            if ($this->claim($event, $recipient['key'], 'email', $base + ['status' => NotificationDelivery::DIGESTED, 'error' => $cadence])) {
                DB::table('notification_digest_items')->insertOrIgnore([
                    'user_id' => $user->id, 'workspace_id' => $workspace, 'cadence' => $cadence, 'notification_event_id' => $event->id, 'created_at' => now(),
                ]);
            }

            return;
        }
        $groupKey = $config['group'] ? $event->event.':'.$workspace : null;
        if ($groupKey) {
            $when = $when->max(now()->addMinutes((int) $config['group']));
        }
        // Stored in the app timezone (quiet hours are computed in the reader's).
        $when = $when->copy()->setTimezone(config('app.timezone'));
        $delivery = $this->claim($event, $recipient['key'], 'email', $base + ['status' => NotificationDelivery::QUEUED, 'group_key' => $groupKey, 'scheduled_for' => $when]);
        // Due now: sent immediately. Held back (quiet hours, grouping window): sent by the
        // every-minute `notifications:send-due` task once its time comes.
        if ($delivery && ! $when->isFuture()) {
            SendNotificationEmail::sendNow($delivery->id, $transient);
        }
    }

    /** Insert the (event, recipient, channel) row once; returns it only to the caller that created it. */
    private function claim(NotificationEvent $event, string $key, string $channel, array $values): ?NotificationDelivery
    {
        $inserted = DB::table('notification_deliveries')->insertOrIgnore([array_merge([
            'notification_event_id' => $event->id, 'recipient_key' => $key, 'channel' => $channel, 'created_at' => now(), 'updated_at' => now(),
        ], $values)]);

        return $inserted ? NotificationDelivery::where('notification_event_id', $event->id)->where('recipient_key', $key)->where('channel', $channel)->first() : null;
    }

    private function throttled(User $user): bool
    {
        return NotificationDelivery::query()
            ->join('notification_events', 'notification_events.id', '=', 'notification_deliveries.notification_event_id')
            ->where('notification_deliveries.user_id', $user->id)->where('notification_deliveries.channel', 'email')
            ->whereIn('notification_deliveries.status', [NotificationDelivery::QUEUED, NotificationDelivery::SENT, 'sending'])
            ->where('notification_events.priority', '>=', 2)
            ->where('notification_deliveries.created_at', '>=', now()->subHour())
            ->count() >= (int) config('notifications.defaults.p2_emails_per_hour', 6);
    }
}
