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
        'failed_count', 'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
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

    public function sends(): HasMany
    {
        return $this->hasMany(EmailCampaignSend::class);
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
}
