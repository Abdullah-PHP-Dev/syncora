<?php

namespace App\Models\EmailMarketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignSend extends Model
{
    protected $table = 'email_campaign_sends';

    protected $fillable = [
        'email_campaign_id', 'email_subscriber_id', 'status', 'mailgun_message_id',
        'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'bounced_at',
        'complained_at', 'unsubscribed_at', 'error_message',
    ];

    protected $casts = [
        'sent_at'          => 'datetime',
        'delivered_at'     => 'datetime',
        'opened_at'        => 'datetime',
        'clicked_at'       => 'datetime',
        'bounced_at'       => 'datetime',
        'complained_at'    => 'datetime',
        'unsubscribed_at'  => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(EmailSubscriber::class, 'email_subscriber_id');
    }
}
