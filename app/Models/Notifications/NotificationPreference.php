<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

/** A person's choice for one category or event, in one workspace (0 = all workspaces). */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'workspace_id', 'scope', 'key', 'in_app', 'email'];

    protected $casts = ['in_app' => 'boolean'];
}
