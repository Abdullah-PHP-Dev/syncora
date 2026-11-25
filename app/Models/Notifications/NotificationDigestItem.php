<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

class NotificationDigestItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'workspace_id', 'cadence', 'notification_event_id', 'created_at', 'sent_at'];

    protected $casts = ['created_at' => 'datetime', 'sent_at' => 'datetime'];

    public function notificationEvent()
    {
        return $this->belongsTo(NotificationEvent::class);
    }
}
