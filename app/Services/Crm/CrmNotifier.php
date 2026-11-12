<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Notification;
use App\Models\SellerTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * G-02 / S-12: tells the right person about CRM events, in the navbar bell
 * and optionally by email, following each person's own preferences.
 * Nobody is told about something they did themselves. Never throws.
 */
class CrmNotifier
{
    public const EVENTS = ['lead_assigned', 'lead_unassigned', 'deal_assigned', 'deal_won', 'deal_lost', 'task_reminder'];

    /** Defaults when a person has not chosen: everything in the bell, email only for work handed to them. */
    public const DEFAULTS = [
        'lead_assigned' => ['in_app' => true, 'email' => true], 'lead_unassigned' => ['in_app' => true, 'email' => false],
        'deal_assigned' => ['in_app' => true, 'email' => false], 'deal_won' => ['in_app' => true, 'email' => false],
        'deal_lost' => ['in_app' => true, 'email' => false], 'task_reminder' => ['in_app' => true, 'email' => true],
    ];

    public function __construct(private CrmContext $context) {}

    public static function preferences(int $workspaceId, int $userId): array
    {
        $saved = CrmSettings::for($workspaceId)['notifications'][$userId] ?? [];

        return collect(self::DEFAULTS)->map(fn ($d, $event) => [
            'in_app' => (bool) ($saved[$event]['in_app'] ?? $d['in_app']), 'email' => (bool) ($saved[$event]['email'] ?? $d['email']),
        ])->all();
    }

    /** @param  int[]  $userIds */
    public function send(int $workspaceId, array $userIds, string $event, string $title, ?string $body, ?string $url, ?int $exceptActor = null): int
    {
        $sent = 0;
        foreach (array_unique(array_filter($userIds)) as $userId) {
            if ($userId === $exceptActor) {
                continue;
            }
            try {
                $prefs = self::preferences($workspaceId, $userId)[$event] ?? ['in_app' => true, 'email' => false];
                if ($prefs['in_app']) {
                    Notification::create(['workspace_id' => $workspaceId, 'user_id' => $userId, 'event' => $event, 'title' => mb_substr($title, 0, 200),
                        'body' => $body ? mb_substr($body, 0, 300) : null, 'url' => $url]);
                    $sent++;
                }
                if ($prefs['email'] && ($person = User::find($userId)) && $person->email) {
                    Mail::raw(trim($title."\n\n".($body ?? '')."\n\n".($url ?? '')), fn ($m) => $m->to($person->email)->subject('Socialeaz CRM: '.mb_substr($title, 0, 90)));
                }
            } catch (\Throwable $e) {
                Log::warning('CRM notification skipped.', ['event' => $event, 'user' => $userId, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /** The person clicking, or null for webhooks and scheduled jobs (then the owner must hear about it too). */
    private function acting(): ?int
    {
        return request()->hasSession() ? $this->context->actorId() : null;
    }

    /** The organization owner and CRM admins: who hears about unowned work. */
    public static function managers(int $workspaceId): array
    {
        $ownerId = User::whereKey($workspaceId)->value('workspace_owner_id') ?: $workspaceId;

        return array_merge([$ownerId], SellerTeamMember::where('owner_id', $workspaceId)->where('role', 'admin')
            ->whereNotNull('accepted_at')->whereNotNull('user_id')->pluck('user_id')->all());
    }

    public function leadAssigned(Lead $lead): void
    {
        if ($lead->owner_id) {
            $name = $lead->contact()->withoutGlobalScopes()->first()?->full_name ?? '';
            $this->send($lead->workspace_id, [$lead->owner_id], 'lead_assigned', __('crm.notify.lead_assigned', ['name' => $name]),
                $lead->interest, route('admin.crm.leads.show', $lead->id), $this->acting());
        }
    }

    public function leadUnassigned(Lead $lead): void
    {
        $name = $lead->contact()->withoutGlobalScopes()->first()?->full_name ?? '';
        $this->send($lead->workspace_id, self::managers($lead->workspace_id), 'lead_unassigned', __('crm.notify.lead_unassigned', ['name' => $name, 'source' => $lead->source ?? '—']),
            $lead->interest, route('admin.crm.leads.show', $lead->id), $this->acting());
    }

    public function dealAssigned(Deal $deal): void
    {
        if ($deal->owner_id) {
            $this->send($deal->workspace_id, [$deal->owner_id], 'deal_assigned', __('crm.notify.deal_assigned', ['name' => $deal->name]),
                null, route('admin.crm.deals.show', $deal->id), $this->acting());
        }
    }

    public function dealClosed(Deal $deal): void
    {
        $event = $deal->status === 'won' ? 'deal_won' : 'deal_lost';
        $this->send($deal->workspace_id, array_merge([$deal->owner_id], self::managers($deal->workspace_id)), $event,
            __('crm.notify.'.$event, ['name' => $deal->name, 'value' => $deal->currency.' '.number_format((float) $deal->value)]),
            $deal->status === 'lost' ? $deal->lost_reason : null, route('admin.crm.deals.show', $deal->id), $this->acting());
    }

    /** Due reminders (remind_at reached), each sent once. Returns how many. */
    public function sendReminders(): int
    {
        $count = 0;
        Activity::withoutGlobalScopes()->where('type', 'task')->whereNull('completed_at')->whereNotNull('owner_id')
            ->whereNotNull('remind_at')->where('remind_at', '<=', now())->where('remind_at', '>=', now()->subDay())
            ->where(fn ($q) => $q->whereNull('meta->reminded')->orWhere('meta->reminded', false))
            ->limit(500)->get()->each(function (Activity $task) use (&$count) {
                $task->forceFill(['meta' => array_merge($task->meta ?? [], ['reminded' => true])])->saveQuietly();
                $url = $task->deal_id ? route('admin.crm.deals.show', $task->deal_id) : ($task->contact_id ? route('admin.crm.contacts.show', $task->contact_id) : route('admin.crm.activities.index'));
                $count += $this->send($task->workspace_id, [$task->owner_id], 'task_reminder', __('crm.notify.task_reminder', ['subject' => $task->subject]),
                    $task->due_at ? __('crm.notify.due_at', ['time' => $task->due_at->format('d M H:i')]) : null, $url);
            });

        return $count;
    }
}
