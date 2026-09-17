<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $fillable = ['user_id', 'body', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
