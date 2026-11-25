<?php

namespace App\Models\PlatformEmails;

use Illuminate\Database\Eloquent\Model;

class PlatformEmailTemplateVersion extends Model
{
    public $timestamps = false;

    protected $table = 'platform_email_template_versions';

    protected $fillable = ['platform_email_template_id', 'version', 'action', 'content', 'created_by', 'created_at'];

    protected $casts = ['content' => 'array', 'created_at' => 'datetime'];

    public function author()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
