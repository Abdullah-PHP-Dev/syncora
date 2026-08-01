<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostWhatsappRecipient extends Model
{
    protected $table = 'post_whatsapp_recipients';

    protected $fillable = [
        'post_id', 'phone_number', 'status', 'external_message_id', 'error_message', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
