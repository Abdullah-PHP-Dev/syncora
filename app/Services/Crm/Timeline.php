<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;

/**
 * Record history (audit): who did what, written as "event" activities so it
 * shows on the same timeline as notes and tasks.
 */
class Timeline
{
    public function __construct(private CrmContext $context) {}

    public function event(string $key, array $links, array $meta = []): Activity
    {
        $activity = Activity::create(array_merge($links, [
            'type' => 'event',
            'subject' => $key,
            'created_by' => $this->context->actorId(),
            'meta' => $meta ?: null,
        ]));

        // Automations listen to the same history (AutomationEngine never throws).
        try {
            app(AutomationEngine::class)->handle($key, $links);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CRM automations skipped.', ['event' => $key, 'error' => $e->getMessage()]);
        }

        return $activity;
    }
}
