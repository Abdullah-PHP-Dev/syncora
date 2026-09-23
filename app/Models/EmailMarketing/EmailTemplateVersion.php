<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateVersion extends Model
{
    protected $fillable = [
        'email_template_id', 'version', 'html_content', 'schema_json', 'editor_type', 'created_by',
    ];

    protected $casts = [
        'schema_json' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
