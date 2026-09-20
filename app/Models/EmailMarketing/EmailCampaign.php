<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $table = 'email_campaigns';

    protected $fillable = [
        'user_id', 'email_list_id', 'email_template_id', 'name', 'subject',
        'from_name', 'from_email', 'body', 'status', 'scheduled_at', 'sent_at',
        'total_recipients', 'sent_count', 'delivered_count', 'opened_count',
        'clicked_count', 'bounced_count', 'complained_count', 'unsubscribed_count',
        'failed_count', 'error_message', 'sendgrid_single_send_id',
        'sender_identity_id', 'preheader', 'audience_type', 'audience_id',
        'campaign_type', 'suppression_group_id',
    ];

    protected $casts = [
        'scheduled_at'          => 'datetime',
        'sent_at'               => 'datetime',
        // Without this, a value just set via mass-assignment from an
        // HTML <select> (always a string over HTTP) stays a string
        // in-memory for the rest of that same request - a fresh
        // ::find() happens to come back as a real int from the DB
        // driver, which is what made this bug so easy to miss testing
        // via tinker. SendGrid's Go backend rejects a JSON string here
        // with an opaque "json could not be unmarshalled" error, since
        // it strictly expects a JSON number for this field.
        'suppression_group_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(EmailList::class, 'email_list_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function senderIdentity(): BelongsTo
    {
        return $this->belongsTo(SenderIdentity::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(EmailCampaignSend::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }

    /**
     * Resolves the actual audience row (EmailList or EmailSegment) from
     * audience_type/audience_id - kept as one lookup here rather than two
     * separate nullable belongsTo relations, since a campaign only ever
     * has one or the other (see the audience_type enum).
     */
    public function audience(): EmailList|EmailSegment|null
    {
        if (!$this->audience_id) {
            // Pre-migration rows (or ones created before audience_id was
            // set) fall back to the original email_list_id column.
            return $this->email_list_id ? $this->list : null;
        }

        return $this->audience_type === 'segment'
            ? EmailSegment::find($this->audience_id)
            : EmailList::find($this->audience_id);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    public function openRate(): float
    {
        return $this->delivered_count > 0
            ? round(($this->opened_count / $this->delivered_count) * 100, 1)
            : 0.0;
    }

    public function clickRate(): float
    {
        return $this->delivered_count > 0
            ? round(($this->clicked_count / $this->delivered_count) * 100, 1)
            : 0.0;
    }

    public function deliveryRate(): float
    {
        return $this->sent_count > 0
            ? round(($this->delivered_count / $this->sent_count) * 100, 1)
            : 0.0;
    }

    public function bounceRate(): float
    {
        return $this->sent_count > 0
            ? round(($this->bounced_count / $this->sent_count) * 100, 1)
            : 0.0;
    }

    public function unsubscribeRate(): float
    {
        return $this->delivered_count > 0
            ? round(($this->unsubscribed_count / $this->delivered_count) * 100, 1)
            : 0.0;
    }

    /**
     * SendGrid's "blocked" event has no dedicated counter column (unlike
     * delivered/opened/clicked/bounced/complained/unsubscribed, which are
     * incremented on arrival by SendGridWebhookService) - counted live
     * from the real EmailEvent rows instead of adding a column purely to
     * display it, since every blocked event is already stored regardless.
     */
    public function blockedCount(): int
    {
        return $this->events()->where('event_type', 'blocked')->count();
    }
}
