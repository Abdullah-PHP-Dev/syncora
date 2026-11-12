<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

/** A CRM bell item for one person in one workspace (see App\Services\Crm\CrmNotifier). */
class Notification extends Model
{
    protected $table = 'crm_notifications';

    protected $fillable = ['workspace_id', 'user_id', 'event', 'title', 'body', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
