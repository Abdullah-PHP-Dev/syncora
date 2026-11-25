<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * One item in a person's notification center. Text is not stored: it is rendered
 * from lang/{locale}/notifications.php at display time, in the reader's language.
 */
class UserNotification extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'notification_event_id', 'event', 'category', 'priority', 'params', 'url', 'group_key', 'group_count', 'read_at', 'archived_at'];

    protected $casts = ['params' => 'array', 'read_at' => 'datetime', 'archived_at' => 'datetime'];

    public function scopeVisible($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->whereNull('archived_at');
    }
}
