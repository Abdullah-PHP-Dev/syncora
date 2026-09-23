<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'user_id', 'name', 'subject', 'body', 'schema_json', 'sendgrid_design_id',
        'current_version', 'thumbnail_url', 'category', 'status',
    ];

    protected $casts = [
        'schema_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class)->orderByDesc('version');
    }

    /**
     * Snapshots the current body (and block schema, when present) as a new
     * version row before it's overwritten - called from the controller
     * right before an update, so every save creates real, browsable
     * history rather than silently clobbering the previous body. A
     * template built with the block editor carries its schema_json
     * forward into the version too, so restoring it re-hydrates the
     * editor's actual block tree, not just the rendered HTML - a template
     * still on the legacy code editor (no schema_json) keeps that
     * distinction via editor_type.
     */
    public function snapshotVersion(?int $createdBy = null): EmailTemplateVersion
    {
        $version = $this->versions()->create([
            'version'      => $this->current_version,
            'html_content' => $this->body,
            'schema_json'  => $this->schema_json,
            'editor_type'  => $this->schema_json ? 'blocks' : 'code',
            'created_by'   => $createdBy,
        ]);

        $this->increment('current_version');

        return $version;
    }
}
