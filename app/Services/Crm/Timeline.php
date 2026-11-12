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
            $this->notify($key, $links);
            if (! empty($links['contact_id']) && ($contact = \App\Models\Crm\Contact::withoutGlobalScopes()->find($links['contact_id']))) {
                app(LeadScorer::class)->rescore($contact);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CRM automations skipped.', ['event' => $key, 'error' => $e->getMessage()]);
        }

        return $activity;
    }

    /** G-02: bell items that depend on what automations did first (an assigned lead is not "unassigned"). */
    private function notify(string $key, array $links): void
    {
        $notifier = app(CrmNotifier::class);
        if ($key === 'lead_created' && ! empty($links['lead_id']) && ($lead = \App\Models\Crm\Lead::withoutGlobalScopes()->find($links['lead_id'])) && ! $lead->owner_id) {
            $notifier->leadUnassigned($lead);
        }
        if (in_array($key, ['deal_won', 'deal_lost'], true) && ! empty($links['deal_id']) && ($deal = \App\Models\Crm\Deal::withoutGlobalScopes()->find($links['deal_id']))) {
            $notifier->dealClosed($deal);
        }
    }
}
