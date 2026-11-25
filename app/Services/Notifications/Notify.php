<?php

namespace App\Services\Notifications;

use App\Jobs\Notifications\ProcessNotificationEvent;
use App\Models\Notifications\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The one way modules tell people about something.
 *
 *   Notify::event('publishing.post_failed', [
 *       'workspace' => $post->user_id,        // tenant account id (null for personal/security/staff events)
 *       'subject'   => $post,                 // optional model the event is about
 *       'actor'     => $actorId,              // who caused it (excluded unless the event includes the actor)
 *       'users'     => [$assigneeId],         // explicit recipients for the 'users' strategy
 *       'emails'    => ['invitee@x.com'],     // recipients without an account
 *       'params'    => ['platform' => 'Instagram'],   // text placeholders (no secrets, minimal PII)
 *       'dates'     => ['when' => $post->schedule_at], // shown in each reader's own timezone
 *       'url'       => route('admin.posts.edit', $post),
 *       'dedup'     => "{$post->id}:{$post->updated_at->timestamp}",
 *       'transient' => ['url' => $secretInviteUrl],    // used for the email only, never stored in the database
 *   ]);
 *
 * Cheap and safe inside requests, webhooks and model hooks: it writes one outbox row
 * (idempotent on the dedup key) and sends the rest right after the transaction commits.
 * It never throws, so a notification problem can't break the business action.
 */
class Notify
{
    /** Set while a notification job runs: notifications never trigger more notifications (rule R17). */
    public static bool $processing = false;

    /** Events raised inside a transaction that is about to roll back (e.g. a limit check that throws). */
    private static array $deferred = [];

    /**
     * Emit after the current request / command / queue job finishes, outside any transaction.
     * Used where the triggering code throws and rolls its transaction back.
     */
    public static function later(string $event, array $options = []): void
    {
        self::$deferred[($options['dedup'] ?? '').$event] = [$event, $options];
    }

    public static function flush(): void
    {
        $pending = self::$deferred;
        self::$deferred = [];
        foreach ($pending as [$event, $options]) {
            self::event($event, $options);
        }
    }

    private const DENIED_PARAM_KEYS = ['token', 'password', 'secret', 'access_token', 'refresh_token', 'api_key', 'card_number', 'cvv'];

    public static function event(string $event, array $options = []): ?NotificationEvent
    {
        if (self::$processing) {
            return null;
        }
        try {
            $catalogue = app(NotificationCatalogue::class);
            $config = $catalogue->get($event);
            $subject = $options['subject'] ?? null;
            $payload = array_filter([
                'params' => self::cleanParams($options['params'] ?? []),
                'dates' => collect($options['dates'] ?? [])->filter()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toIso8601String())->all(),
                'url' => isset($options['url']) ? Links::path($options['url']) : null,
                'group_url' => isset($options['group_url']) ? Links::path($options['group_url']) : null,
                'users' => array_values(array_unique(array_filter(array_map('intval', $options['users'] ?? [])))),
                'emails' => array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $options['emails'] ?? [])))),
                'count' => $options['count'] ?? null,
                'items' => $options['items'] ?? null,
            ], fn ($v) => $v !== null && $v !== []);

            $discriminator = $options['dedup'] ?? (string) Str::uuid();
            $subjectKey = $subject instanceof Model ? class_basename($subject).'#'.$subject->getKey() : '';
            $dedupKey = mb_substr($event.':'.$subjectKey.':'.$discriminator, 0, 191);

            $inserted = DB::table('notification_events')->insertOrIgnore([
                'event' => $event,
                'workspace_id' => $options['workspace'] ?? null,
                'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
                'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
                'actor_id' => $options['actor'] ?? null,
                'dedup_key' => $dedupKey,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'priority' => $config['priority'],
                'occurred_at' => now(),
            ]);
            if (! $inserted) {
                return null; // Already emitted (webhook redelivery, overlapping scheduler runs, retries).
            }
            $row = NotificationEvent::where('dedup_key', $dedupKey)->first();
            // Processed and emailed straight away (no queue worker needed), once the surrounding
            // transaction commits. A failure is logged and never reaches the business action.
            $transient = $options['transient'] ?? [];
            DB::afterCommit(function () use ($row, $transient, $event) {
                try {
                    ProcessNotificationEvent::dispatchSync($row->id, $transient);
                } catch (\Throwable $e) {
                    Log::warning('Notification not processed.', ['event' => $event, 'id' => $row->id, 'error' => $e->getMessage()]);
                }
            });

            return $row;
        } catch (\Throwable $e) {
            if (app()->runningUnitTests() && $e instanceof \InvalidArgumentException) {
                throw $e;
            }
            Log::warning('Notification not emitted.', ['event' => $event, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Text placeholders only: scalars, short, and never anything that looks like a credential (rule R11). */
    private static function cleanParams(array $params): array
    {
        return collect($params)
            ->reject(fn ($v, $k) => Str::contains(Str::lower((string) $k), self::DENIED_PARAM_KEYS))
            ->filter(fn ($v) => is_scalar($v) || $v === null)
            ->map(fn ($v) => is_string($v) ? Str::limit(trim(strip_tags($v)), 300) : $v)
            ->all();
    }
}
