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
        'user_id', 'name', 'subject', 'body', 'sendgrid_design_id',
        'current_version', 'thumbnail_url', 'category', 'status',
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
     * Snapshots the current body as a new version row before it's
     * overwritten - called from the controller right before an update, so
     * every save creates real, browsable history rather than silently
     * clobbering the previous body.
     */
    public function snapshotVersion(?int $createdBy = null): EmailTemplateVersion
    {
        $version = $this->versions()->create([
            'version'      => $this->current_version,
            'html_content' => $this->body,
            'editor_type'  => 'code',
            'created_by'   => $createdBy,
        ]);

        $this->increment('current_version');

        return $version;
    }
}
