<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

/** One channel attempt for one recipient (email or digest). Unique per event x recipient x channel. */
class NotificationDelivery extends Model
{
    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SUPPRESSED = 'suppressed';
    public const SKIPPED = 'skipped';
    public const GROUPED = 'grouped';
    public const DIGESTED = 'digested';

    protected $fillable = ['notification_event_id', 'user_id', 'recipient_key', 'channel', 'status', 'group_key', 'locale', 'to_address',
        'provider_message_id', 'error', 'attempts', 'scheduled_for', 'sent_at', 'failed_at', 'subject', 'template_version'];

    protected $casts = ['scheduled_for' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];

    public function notificationEvent()
    {
        return $this->belongsTo(NotificationEvent::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
