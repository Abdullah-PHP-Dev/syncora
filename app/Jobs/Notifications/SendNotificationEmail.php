<?php

namespace App\Jobs\Notifications;

use App\Mail\PlatformNotificationMail;
use App\Models\Notifications\EmailSuppression;
use App\Models\Notifications\NotificationDelivery;
use App\Services\Notifications\EventGuards;
use App\Services\Notifications\Notify;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one notification email (and anything waiting in the same group).
 *
 * The delivery row is claimed ("sending") before the provider is called, so a retry
 * after an accepted send never sends twice (rule R15). A send that throws goes back to
 * "queued" and is retried with backoff; after the last try it is marked failed.
 */
class SendNotificationEmail implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 600, 1800];

    public function __construct(public int $deliveryId, public array $transient = []) {}

    /**
     * Sends right away in this process, after the current transaction commits. Never throws:
     * a provider error puts the delivery back to "queued" (with the error) and
     * `notifications:send-due` retries it; after the last try it is marked failed.
     */
    public static function sendNow(int $deliveryId, array $transient = []): void
    {
        DB::afterCommit(function () use ($deliveryId, $transient) {
            $job = new self($deliveryId, $transient);
            try {
                \App\Services\Notifications\Links::withPublicRoot(fn () => $job->handle(app(RecipientResolver::class)));
            } catch (\Throwable $e) {
                $attempts = (int) NotificationDelivery::whereKey($deliveryId)->value('attempts');
                if ($attempts >= $job->tries) {
                    $job->failed($e);
                }
                Log::warning('Notification email not sent.', ['delivery' => $deliveryId, 'attempts' => $attempts, 'error' => $e->getMessage()]);
            }
        });
    }

    public function handle(RecipientResolver $resolver): void
    {
        Notify::$processing = true;
        try {
            $this->send($resolver);
        } finally {
            Notify::$processing = false;
        }
    }

    private function send(RecipientResolver $resolver): void
    {
        $claimed = DB::transaction(function () use ($resolver) {
            $delivery = NotificationDelivery::whereKey($this->deliveryId)->lockForUpdate()->first();
            if (! $delivery || $delivery->status !== NotificationDelivery::QUEUED) {
                return null;
            }
            $event = $delivery->notificationEvent;
            $config = $event->config();
            $stop = match (true) {
                ! $resolver->stillAllowed($delivery->user, $event, $config) => [NotificationDelivery::SKIPPED, 'no longer allowed'],
                ! \App\Services\PlatformEmails\EmailTemplateSwitch::enabled($event->event) => [NotificationDelivery::SKIPPED, 'disabled by administrator'],
                ! EventGuards::relevant($event, $delivery) => [NotificationDelivery::SKIPPED, 'no longer relevant'],
                EmailSuppression::blocks($delivery->to_address) => [NotificationDelivery::SUPPRESSED, 'suppressed address'],
                default => null,
            };
            if ($stop) {
                $delivery->update(['status' => $stop[0], 'error' => $stop[1]]);

                return null;
            }
            // Everything else due in this person's group goes out in this one email.
            $group = collect([$delivery]);
            if ($delivery->group_key) {
                $group = $group->merge(NotificationDelivery::where('recipient_key', $delivery->recipient_key)->where('group_key', $delivery->group_key)
                    ->where('channel', 'email')->where('status', NotificationDelivery::QUEUED)->whereKeyNot($delivery->id)
                    ->where('scheduled_for', '<=', now()->addMinute())->lockForUpdate()->get()
                    ->filter(fn (NotificationDelivery $d) => EventGuards::relevant($d->notificationEvent, $d)));
            }
            NotificationDelivery::whereIn('id', $group->pluck('id'))->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1')]);

            return $group;
        });
        if (! $claimed) {
            return;
        }

        $lead = $claimed->first();
        try {
            $mailer = config('notifications.mailer') ? Mail::mailer(config('notifications.mailer')) : Mail::mailer();
            $mail = new PlatformNotificationMail($claimed->all(), $this->transient);
            $sent = $mailer->to($lead->to_address)->send($mail);
        } catch (\Throwable $e) {
            NotificationDelivery::whereIn('id', $claimed->pluck('id'))->update(['status' => NotificationDelivery::QUEUED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
        $messageId = $sent?->getMessageId();
        $lead->update(['status' => NotificationDelivery::SENT, 'sent_at' => now(), 'provider_message_id' => $messageId, 'error' => null,
            'subject' => mb_substr((string) $mail->envelope()->subject, 0, 255), 'template_version' => $mail->templateVersion]);
        NotificationDelivery::whereIn('id', $claimed->pluck('id')->slice(1))
            ->update(['status' => NotificationDelivery::GROUPED, 'sent_at' => now(), 'provider_message_id' => $messageId]);
    }

    public function failed(?\Throwable $e): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->whereIn('status', [NotificationDelivery::QUEUED, 'sending'])
            ->update(['status' => NotificationDelivery::FAILED, 'failed_at' => now(), 'error' => mb_substr((string) $e?->getMessage(), 0, 1000)]);
    }
}
