<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Query this to answer "is my webhook actually being hit" and "is it
 * succeeding" without grepping laravel.log. Examples (tinker or
 * elsewhere):
 *
 *   WebhookLog::where('platform', 'tiktok')->latest()->take(10)->get();
 *   WebhookLog::where('platform', 'tiktok')->where('created_at', '>=', now()->subHour())->count();
 *   WebhookLog::where('platform', 'tiktok')->where('signature_valid', false)->latest()->first();
 *   WebhookLog::where('platform', 'tiktok')->where('processed', true)->latest()->first();
 */
class WebhookLog extends Model
{
    protected $fillable = [
        'platform', 'event_type', 'signature_valid', 'processed', 'note', 'payload', 'ip',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed'       => 'boolean',
        'payload'         => 'array',
    ];
}
