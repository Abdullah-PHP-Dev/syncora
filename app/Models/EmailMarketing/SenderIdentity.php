<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderIdentity extends Model
{
    protected $fillable = [
        'user_id', 'email_subaccount_id', 'sendgrid_sender_id', 'nickname',
        'from_name', 'from_email', 'reply_to', 'address', 'address_2',
        'city', 'state', 'zip', 'country', 'status', 'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subaccount(): BelongsTo
    {
        return $this->belongsTo(EmailSubaccount::class, 'email_subaccount_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
