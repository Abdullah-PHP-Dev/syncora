<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Stage;
use Illuminate\Support\Facades\DB;

class DealService
{
    public function __construct(private Timeline $timeline) {}

    /** Move a deal to a stage (drag on the board). Won/lost stages close it; the contact follows. */
    /**
     * @param  array{value?: float, won_at?: string, win_reasons?: array, competitor?: string, notes?: string,
     *               mark_customer?: bool, onboarding_task?: string, reengage_days?: int}  $details  won/lost modal (D-07/D-08)
     */
    public function move(Deal $deal, Stage $stage, ?int $position = null, ?string $lostReason = null, array $details = []): Deal
    {
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422);

        return DB::transaction(function () use ($deal, $stage, $position, $lostReason, $details) {
            $from = $deal->stage_id;
            $deal->fill([
                'stage_id' => $stage->id, 'probability' => $stage->probability,
                'status' => $stage->kind, 'position' => $position ?? $deal->position,
            ]);
            if ($from !== $stage->id) {
                $deal->stage_changed_at = now();
            }
            $deal->won_at = $stage->kind === 'won' ? ($deal->won_at ?? now()) : null;
            $deal->lost_at = $stage->kind === 'lost' ? ($deal->lost_at ?? now()) : null;
            $deal->lost_reason = $stage->kind === 'lost' ? ($lostReason ?? $deal->lost_reason) : null;
            if ($stage->kind === 'won') {
                if (isset($details['value'])) {
                    $deal->value = $details['value'];
                }
                if (! empty($details['won_at'])) {
                    $deal->won_at = \Illuminate\Support\Carbon::parse($details['won_at']);
                }
                $deal->win_reasons = $details['win_reasons'] ?? $deal->win_reasons;
            }
            if ($stage->kind === 'lost') {
                $deal->competitor = $details['competitor'] ?? $deal->competitor;
            }
            if (! empty($details['notes'])) {
                $deal->notes = trim(($deal->notes ? $deal->notes."\n\n" : '').$details['notes']);
            }
            $deal->save();

            $owner = $deal->owner_id;
            if ($stage->kind === 'won' && ! empty($details['onboarding_task'])) {
                \App\Models\Crm\Activity::create(['type' => 'task', 'priority' => 'medium', 'subject' => mb_substr($details['onboarding_task'], 0, 200), 'due_at' => now()->addDay(),
                    'owner_id' => $owner, 'contact_id' => $deal->contact_id, 'deal_id' => $deal->id, 'created_by' => app(CrmContext::class)->actorId()]);
            }
            if ($stage->kind === 'lost' && ! empty($details['reengage_days'])) {
                \App\Models\Crm\Activity::create(['type' => 'task', 'priority' => 'low', 'subject' => __('crm.deals.reengage_subject', ['name' => $deal->name]),
                    'due_at' => now()->addDays((int) $details['reengage_days']), 'owner_id' => $owner, 'contact_id' => $deal->contact_id, 'deal_id' => $deal->id,
                    'created_by' => app(CrmContext::class)->actorId()]);
            }

            if ($from !== $stage->id) {
                $this->timeline->event($stage->kind === 'open' ? 'deal_stage_changed' : 'deal_'.$stage->kind,
                    ['deal_id' => $deal->id, 'contact_id' => $deal->contact_id, 'company_id' => $deal->company_id],
                    ['stage' => $stage->name, 'value' => (float) $deal->value, 'reason' => $lostReason]);
            }
            if ($stage->kind === 'won' && $deal->contact_id && ($details['mark_customer'] ?? true)) {
                Contact::whereKey($deal->contact_id)->update(['lifecycle' => 'customer', 'last_activity_at' => now()]);
            }

            return $deal->fresh(['stage', 'contact', 'company', 'owner']);
        });
    }
}
