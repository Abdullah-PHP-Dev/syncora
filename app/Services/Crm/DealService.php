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
    public function move(Deal $deal, Stage $stage, ?int $position = null, ?string $lostReason = null): Deal
    {
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422);

        return DB::transaction(function () use ($deal, $stage, $position, $lostReason) {
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
            $deal->save();

            if ($from !== $stage->id) {
                $this->timeline->event($stage->kind === 'open' ? 'deal_stage_changed' : 'deal_'.$stage->kind,
                    ['deal_id' => $deal->id, 'contact_id' => $deal->contact_id, 'company_id' => $deal->company_id],
                    ['stage' => $stage->name, 'value' => (float) $deal->value, 'reason' => $lostReason]);
            }
            if ($stage->kind === 'won' && $deal->contact_id) {
                Contact::whereKey($deal->contact_id)->update(['lifecycle' => 'customer', 'last_activity_at' => now()]);
            }

            return $deal->fresh(['stage', 'contact', 'company', 'owner']);
        });
    }
}
