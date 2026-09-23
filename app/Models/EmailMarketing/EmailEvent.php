<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailEvent extends Model
{
    protected $fillable = [
        'user_id', 'email_campaign_id', 'sendgrid_message_id', 'event_type',
        'recipient_email', 'event_at', 'ip', 'user_agent', 'url', 'reason',
        'sg_event_id', 'raw_payload',
    ];

    protected $casts = [
        'event_at'     => 'datetime',
        'raw_payload'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
