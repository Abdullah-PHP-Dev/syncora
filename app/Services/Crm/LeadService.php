<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Stage;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        private CrmContext $context,
        private ContactMatcher $matcher,
        private Timeline $timeline,
        private PipelineService $pipelines,
    ) {}

    /**
     * Create a lead. The person is matched on phone/email first, so a lead for
     * someone already in the CRM never creates a second contact.
     */
    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            $actor = $this->context->actorId();
            // Captured leads may arrive unassigned on purpose (automations route them).
            $owner = array_key_exists('owner_id', $data) ? $data['owner_id'] : $actor;
            $match = isset($data['contact_id']) ? null : $this->matcher->duplicates($data['phone'] ?? null, $data['email'] ?? null)->first();
            $contact = isset($data['contact_id']) ? Contact::findOrFail($data['contact_id']) : ($match ? Contact::withoutGlobalScope('visibility')->find($match->id) : null);

            if (! $contact) {
                [$first, $last] = array_pad(explode(' ', trim($data['name']), 2), 2, null);
                $contact = Contact::create([
                    'first_name' => $first, 'last_name' => $last, 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null,
                    'lifecycle' => 'lead', 'source' => $data['source'] ?? null, 'owner_id' => $owner, 'created_by' => $actor,
                    'last_activity_at' => now(),
                ]);
                $this->timeline->event('contact_created', ['contact_id' => $contact->id]);
            }

            $lead = Lead::create([
                'contact_id' => $contact->id, 'owner_id' => $owner, 'status' => 'new',
                'source' => $data['source'] ?? null, 'campaign' => $data['campaign'] ?? null, 'interest' => $data['interest'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null, 'currency' => $data['currency'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null, 'created_by' => $actor,
                'external_lead_id' => $data['external_lead_id'] ?? null, 'meta' => $data['meta'] ?? null,
            ]);
            $this->timeline->event('lead_created', ['contact_id' => $contact->id, 'lead_id' => $lead->id]);

            if (! empty($data['follow_up']) && $lead->owner_id) {
                Activity::create([
                    'type' => 'task', 'subject' => __('crm.leads.follow_up_subject', ['name' => $contact->full_name]),
                    'due_at' => now()->addMinutes(15), 'owner_id' => $lead->owner_id, 'created_by' => $actor,
                    'contact_id' => $contact->id, 'lead_id' => $lead->id,
                ]);
            }

            return $lead;
        });
    }

    /** Qualify & convert: same person (lifecycle → opportunity), optional company, deal and task, in one step. */
    public function convert(Lead $lead, array $data): Lead
    {
        abort_if($lead->status === 'converted', 422, __('crm.leads.already_converted'));

        return DB::transaction(function () use ($lead, $data) {
            $actor = $this->context->actorId();
            $contact = $lead->contact;

            if (! empty($data['company_name'])) {
                $company = Company::firstOrCreate(['name' => trim($data['company_name'])], ['owner_id' => $lead->owner_id, 'created_by' => $actor]);
                $contact->company_id = $company->id;
            }
            $contact->lifecycle = 'opportunity';
            $contact->last_activity_at = now();
            $contact->save();

            $deal = null;
            if (! empty($data['create_deal'])) {
                $pipelineId = $data['pipeline_id'] ?? $this->pipelines->default()->id;
                $stage = Stage::where('pipeline_id', $pipelineId)->findOrFail($data['stage_id']);
                $deal = Deal::create([
                    'pipeline_id' => $pipelineId, 'stage_id' => $stage->id, 'probability' => $stage->probability,
                    'contact_id' => $contact->id, 'company_id' => $contact->company_id, 'lead_id' => $lead->id,
                    'owner_id' => $data['owner_id'] ?? $lead->owner_id, 'name' => $data['deal_name'],
                    'value' => $data['value'] ?? $lead->estimated_value ?? 0, 'currency' => $data['currency'] ?? $lead->currency ?? 'SAR',
                    'expected_close_date' => $data['expected_close_date'] ?? null, 'source' => $lead->source,
                    'stage_changed_at' => now(), 'created_by' => $actor,
                ]);
                $this->timeline->event('deal_created', ['contact_id' => $contact->id, 'deal_id' => $deal->id], ['name' => $deal->name, 'value' => (float) $deal->value]);
            }

            if (! empty($data['task_subject'])) {
                Activity::create([
                    'type' => 'task', 'subject' => $data['task_subject'], 'due_at' => $data['task_due_at'] ?? now()->addDay(),
                    'owner_id' => $deal?->owner_id ?? $lead->owner_id, 'created_by' => $actor,
                    'contact_id' => $contact->id, 'lead_id' => $lead->id, 'deal_id' => $deal?->id,
                ]);
            }

            $lead->update(['status' => 'converted', 'converted_at' => now(), 'deal_id' => $deal?->id]);
            $this->timeline->event('lead_converted', ['contact_id' => $contact->id, 'lead_id' => $lead->id, 'deal_id' => $deal?->id]);

            return $lead->fresh(['contact', 'deal']);
        });
    }

    public function disqualify(Lead $lead, string $reason, ?string $note): Lead
    {
        $lead->update(['status' => 'unqualified', 'disqualify_reason' => $reason, 'disqualify_note' => $note]);
        $this->timeline->event('lead_disqualified', ['contact_id' => $lead->contact_id, 'lead_id' => $lead->id], ['reason' => $reason]);

        return $lead;
    }

    public function assign(array $leadIds, int $ownerId): int
    {
        $count = 0;
        foreach (Lead::whereIn('id', $leadIds)->get() as $lead) {
            if ($lead->owner_id !== $ownerId) {
                $lead->update(['owner_id' => $ownerId]);
                $this->timeline->event('owner_changed', ['contact_id' => $lead->contact_id, 'lead_id' => $lead->id], ['owner_id' => $ownerId]);
                $count++;
            }
        }

        return $count;
    }
}
