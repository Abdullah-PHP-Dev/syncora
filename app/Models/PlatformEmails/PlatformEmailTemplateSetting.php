<?php

namespace App\Models\PlatformEmails;

use Illuminate\Database\Eloquent\Model;

/** Per-event email switch set by an administrator. No row = enabled. */
class PlatformEmailTemplateSetting extends Model
{
    protected $table = 'platform_email_template_settings';

    protected $fillable = ['key', 'enabled', 'updated_by'];

    protected $casts = ['enabled' => 'boolean'];
}
