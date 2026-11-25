<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\SellerTeamMember;
use App\Models\User;
use App\Services\Notifications\Emitters\Support;
use App\Services\Notifications\NotificationCatalogue;
use App\Services\Notifications\Notify;
use App\Services\Notifications\PreferenceResolver;

/**
 * G-02 / S-12: tells the right person about CRM events. Now an adapter of the platform
 * notification system (App\Services\Notifications\Notify): same events and recipients as
 * before, but queued (never inside the lead/deal save or a webhook), rendered in each
 * reader's own language, and following the person's preferences in notification_preferences.
 * Nobody is told about something they did themselves. Never throws.
 */
class CrmNotifier
{
    public const EVENTS = ['lead_assigned', 'lead_unassigned', 'deal_assigned', 'deal_won', 'deal_lost', 'task_reminder'];

    public function __construct(private CrmContext $context) {}

    /** The person's current choice per CRM event in this workspace (shape kept for the CRM settings tab). */
    public static function preferences(int $workspaceId, int $userId): array
    {
        $user = User::find($userId);
        $catalogue = app(NotificationCatalogue::class);

        return collect(self::EVENTS)->mapWithKeys(function ($event) use ($user, $workspaceId, $catalogue) {
            $choice = $user ? app(PreferenceResolver::class)->for($user, $catalogue->get('crm.'.$event), $workspaceId) : ['in_app' => true, 'email' => 'off'];

            return [$event => ['in_app' => $choice['in_app'], 'email' => $choice['email'] !== 'off']];
        })->all();
    }

    /** Saves choices from the CRM settings tab into the platform preferences (this workspace only). */
    public static function savePreferences(int $workspaceId, User $user, array $events): void
    {
        foreach (array_intersect_key($events, array_flip(self::EVENTS)) as $event => $choice) {
            app(PreferenceResolver::class)->save($user, $workspaceId, 'event', 'crm.'.$event,
                (bool) ($choice['in_app'] ?? true), ($choice['email'] ?? false) ? 'immediate' : 'off');
        }
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

    private function emit(string $event, int $workspaceId, $subject, array $users, array $params, string $url, array $extra = []): void
    {
        Notify::event('crm.'.$event, array_merge([
            'workspace' => $workspaceId, 'subject' => $subject, 'actor' => $this->acting(), 'users' => $users,
            'params' => $params, 'url' => $url, 'group_url' => Support::url('admin.crm.overview'), 'dedup' => implode(':', $users).':'.now()->timestamp,
        ], $extra));
    }

    private static function contactName(Lead $lead): string
    {
        return $lead->contact()->withoutGlobalScopes()->first()?->full_name ?: '—';
    }

    public function leadAssigned(Lead $lead): void
    {
        if ($lead->owner_id) {
            $this->emit('lead_assigned', $lead->workspace_id, $lead, [$lead->owner_id],
                ['name' => self::contactName($lead), 'source' => (string) ($lead->source ?: '—'), 'interest' => (string) $lead->interest],
                route('admin.crm.leads.show', $lead->id));
        }
    }

    public function leadUnassigned(Lead $lead): void
    {
        $this->emit('lead_unassigned', $lead->workspace_id, $lead, self::managers($lead->workspace_id),
            ['name' => self::contactName($lead), 'source' => (string) ($lead->source ?: '—')], route('admin.crm.leads.show', $lead->id));
    }

    public function dealAssigned(Deal $deal): void
    {
        if ($deal->owner_id) {
            $this->emit('deal_assigned', $deal->workspace_id, $deal, [$deal->owner_id], ['name' => (string) $deal->name], route('admin.crm.deals.show', $deal->id));
        }
    }

    public function dealClosed(Deal $deal): void
    {
        $event = $deal->status === 'won' ? 'deal_won' : 'deal_lost';
        $this->emit($event, $deal->workspace_id, $deal, array_values(array_filter(array_merge([$deal->owner_id], self::managers($deal->workspace_id)))),
            ['name' => (string) $deal->name, 'value' => $deal->currency.' '.number_format((float) $deal->value), 'reason' => (string) ($deal->lost_reason ?: '')],
            route('admin.crm.deals.show', $deal->id), ['dedup' => $event]);
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
                Notify::event('crm.task_reminder', [
                    'workspace' => $task->workspace_id, 'subject' => $task, 'users' => [$task->owner_id],
                    'params' => ['subject' => (string) $task->subject], 'dates' => array_filter(['due' => $task->due_at]), 'url' => $url,
                    'dedup' => 'reminder:'.optional($task->remind_at)->timestamp,
                ]);
                $count++;
            });

        return $count;
    }

    /** CRM-18: an automation's "notify" action - in the bell and by email, following preferences. */
    public static function automationMessage(int $workspaceId, int $memberId, string $automation, string $message, ?string $url): void
    {
        Notify::event('crm.automation_notify', [
            'workspace' => $workspaceId, 'users' => [$memberId], 'params' => ['automation' => $automation, 'message' => $message],
            'url' => $url ?? Support::url('admin.crm.overview'),
        ]);
    }
}
