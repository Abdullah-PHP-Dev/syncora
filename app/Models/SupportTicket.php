<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const CATEGORIES = ['general', 'billing', 'technical', 'account'];

    protected $fillable = ['user_id', 'assigned_to', 'subject', 'category', 'priority', 'status', 'resolved_at'];
    protected $casts = ['resolved_at' => 'datetime'];

    public function customer() { return $this->belongsTo(User::class, 'user_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages() { return $this->hasMany(SupportTicketMessage::class); }
}
