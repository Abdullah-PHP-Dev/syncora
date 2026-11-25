<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

/** Durable outbox row: one domain event occurrence (dedup_key makes emitting idempotent). */
class NotificationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['event', 'workspace_id', 'subject_type', 'subject_id', 'actor_id', 'dedup_key', 'payload', 'priority', 'occurred_at', 'processed_at'];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];

    public function config(): array
    {
        return app(\App\Services\Notifications\NotificationCatalogue::class)->get($this->event);
    }
}
