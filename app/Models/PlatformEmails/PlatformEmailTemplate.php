<?php

namespace App\Models\PlatformEmails;

use Illuminate\Database\Eloquent\Model;

/** An administrator's version of one email (template key + locale). content NULL = built-in default. */
class PlatformEmailTemplate extends Model
{
    protected $table = 'platform_email_templates';

    protected $fillable = ['key', 'locale', 'content', 'draft', 'version', 'published_at', 'created_by', 'updated_by'];

    protected $casts = ['content' => 'array', 'draft' => 'array', 'published_at' => 'datetime'];

    public function versions()
    {
        return $this->hasMany(PlatformEmailTemplateVersion::class, 'platform_email_template_id')->orderByDesc('version')->orderByDesc('id');
    }

    public function editor()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
