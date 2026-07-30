<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $table = 'message_attachments';

    protected $fillable = ['message_id', 'type', 'url', 'mime_type', 'file_name', 'file_size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
