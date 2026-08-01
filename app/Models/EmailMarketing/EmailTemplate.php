<?php

namespace App\Models\EmailMarketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = ['user_id', 'name', 'subject', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
