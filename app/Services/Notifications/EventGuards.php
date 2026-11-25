<?php

namespace App\Services\Notifications;

use App\Models\Notifications\NotificationDelivery;
use App\Models\Notifications\NotificationEvent;
use App\Models\Notifications\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checked just before an email leaves: is it still true and still useful?
 * A delayed or grouped email about something already fixed is not sent.
 */
class EventGuards
{
    public static function relevant(NotificationEvent $event, NotificationDelivery $delivery): bool
    {
        $config = $event->config();
        // Delayed events give the person a chance to see it in the app first.
        if ($config['delay'] && $delivery->user_id && UserNotification::where('user_id', $delivery->user_id)
            ->where('notification_event_id', $event->id)->whereNotNull('read_at')->exists()) {
            return false;
        }

        return rescue(fn () => match (true) {
            $event->event === 'billing.payment_failed' => ! DB::table('wallet_transactions')->where('seller_id', $event->workspace_id)
                ->where('category', 'subscription')->where('status', 'completed')->where('updated_at', '>', $event->occurred_at)->exists(),
            // Only an existing subject whose status moved on cancels the email (e.g. the post was fixed).
            str_starts_with($event->event, 'publishing.post_failed') => in_array(self::subjectStatus($event, 'posts'), [null, 'failed'], true),
            $event->event === 'connections.reconnect_required', $event->event === 'connections.expiring'
                => in_array(self::subjectStatus($event, 'social_connections'), [null, 'expiring', 'expired', 'needs_reauth', 'revoked', 'error'], true),
            $event->event === 'inbox.unanswered' => ! DB::table('messages')->where('conversation_id', $event->subject_id)
                ->where('direction', 'outbound')->where('created_at', '>', $event->occurred_at)->exists(),
            default => true,
        }, true, false);
    }

    private static function subjectStatus(NotificationEvent $event, string $table): ?string
    {
        return $event->subject_id && Schema::hasTable($table) ? DB::table($table)->where('id', $event->subject_id)->value('status') : null;
    }
}
