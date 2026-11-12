<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;

/** Shared JSON shapes so every CRM page renders records the same way. */
trait Presents
{
    protected function contactRow(Contact $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->full_name, 'first_name' => $c->first_name, 'last_name' => $c->last_name,
            'arabic_name' => $c->arabic_name, 'email' => $c->email, 'phone' => $c->phone, 'job_title' => $c->job_title,
            'lifecycle' => $c->lifecycle, 'source' => $c->source, 'score' => $c->score, 'tags' => $c->tags ?? [],
            'owner' => $c->owner ? ['id' => $c->owner->id, 'name' => $c->owner->name] : null,
            'company' => $c->company ? ['id' => $c->company->id, 'name' => $c->company->name] : null,
            'last_activity_at' => $c->last_activity_at?->toIso8601String(), 'created_at' => $c->created_at?->toIso8601String(),
            'url' => route('admin.crm.contacts.show', $c),
        ];
    }

    protected function leadRow(Lead $l): array
    {
        return [
            'id' => $l->id, 'status' => $l->status, 'source' => $l->source, 'campaign' => $l->campaign, 'interest' => $l->interest,
            'estimated_value' => $l->estimated_value !== null ? (float) $l->estimated_value : null, 'currency' => $l->currency,
            'score' => $l->score, 'disqualify_reason' => $l->disqualify_reason, 'deal_id' => $l->deal_id, 'url' => route('admin.crm.leads.show', $l),
            'first_response_minutes' => $l->meta['first_response_minutes'] ?? null,
            'owner' => $l->owner ? ['id' => $l->owner->id, 'name' => $l->owner->name] : null,
            'contact' => $l->contact ? $this->contactRow($l->contact) : null,
            'created_at' => $l->created_at?->toIso8601String(),
        ];
    }

    protected function dealRow(Deal $d): array
    {
        return [
            'id' => $d->id, 'name' => $d->name, 'value' => (float) $d->value, 'currency' => $d->currency, 'probability' => $d->probability,
            'pipeline_id' => $d->pipeline_id, 'stage_id' => $d->stage_id, 'stage' => $d->stage?->name, 'status' => $d->status,
            'expected_close_date' => $d->expected_close_date?->toDateString(), 'source' => $d->source, 'lost_reason' => $d->lost_reason,
            'position' => $d->position, 'won_at' => $d->won_at?->toIso8601String(), 'url' => route('admin.crm.deals.show', $d),
            'idle_days' => $d->status === 'open' ? (int) ($d->stage_changed_at ?? $d->created_at)?->diffInDays(now()) : 0,
            'owner' => $d->owner ? ['id' => $d->owner->id, 'name' => $d->owner->name] : null,
            'contact' => $d->contact ? ['id' => $d->contact->id, 'name' => $d->contact->full_name, 'url' => route('admin.crm.contacts.show', $d->contact)] : null,
            'company' => $d->company ? ['id' => $d->company->id, 'name' => $d->company->name] : null,
        ];
    }

    protected function activityRow(Activity $a): array
    {
        return [
            'id' => $a->id, 'type' => $a->type, 'subject' => $a->subject, 'body' => $a->body, 'meta' => $a->meta,
            'priority' => $a->priority, 'outcome' => $a->outcome, 'duration_minutes' => $a->duration_minutes,
            'due_at' => $a->due_at?->toIso8601String(), 'completed_at' => $a->completed_at?->toIso8601String(),
            'overdue' => $a->type === 'task' && ! $a->completed_at && $a->due_at?->isPast(),
            'owner' => $a->owner ? ['id' => $a->owner->id, 'name' => $a->owner->name] : null,
            'author' => $a->author ? ['id' => $a->author->id, 'name' => $a->author->name] : null,
            'contact' => $a->contact ? ['id' => $a->contact->id, 'name' => $a->contact->full_name, 'url' => route('admin.crm.contacts.show', $a->contact)] : null,
            'deal' => $a->deal ? ['id' => $a->deal->id, 'name' => $a->deal->name] : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
