<?php

namespace App\Jobs\Notifications;

use App\Models\Notifications\NotificationEvent;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Resolves recipients and channels for one stored event. Safe to retry. */
class ProcessNotificationEvent implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    /** @param array $transient email-only data (e.g. a one-time invitation link); encrypted in the queue, never stored */
    public function __construct(public int $eventId, public array $transient = []) {}

    public function handle(NotificationService $service): void
    {
        $event = NotificationEvent::find($this->eventId);
        if (! $event) {
            return;
        }
        Notify::$processing = true;
        try {
            $service->process($event, $this->transient);
        } finally {
            Notify::$processing = false;
        }
    }
}
