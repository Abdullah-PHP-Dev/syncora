<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSegment extends Model
{
    protected $fillable = [
        'user_id', 'sendgrid_segment_id', 'name', 'query_json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
