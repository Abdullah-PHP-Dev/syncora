<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Automation;
use App\Models\Crm\AutomationRun;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Crm\Lead;
use App\Models\Crm\Stage;
use App\Models\EmailMarketing\EmailList;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CRM automations (AU-01/02): trigger → conditions (all must match) → actions.
 *
 * Event triggers fire from Timeline::event() (the record history every CRM
 * write already produces), synchronously and inside the same workspace scope.
 * Time-based triggers (lead/deal idle) run from `crm:run-automations` hourly.
 * A failing automation is logged on its run and never breaks the user action.
 */
class AutomationEngine
{
    public const TRIGGERS = ['lead_created', 'contact_created', 'deal_created', 'deal_stage_changed', 'deal_won', 'deal_lost', 'conversation_linked', 'lead_idle', 'deal_idle'];

    public const TIME_TRIGGERS = ['lead_idle', 'deal_idle'];

    public const ACTIONS = ['create_task', 'assign_owner', 'add_tag', 'set_lifecycle', 'set_lead_status', 'move_deal_stage', 'add_to_email_list', 'notify'];

    /** Condition fields: "record.attribute". */
    public const FIELDS = [
        'contact.lifecycle', 'contact.source', 'contact.score', 'contact.tags', 'contact.language', 'contact.owner_id', 'contact.email', 'contact.phone',
        'lead.source', 'lead.status', 'lead.estimated_value', 'lead.campaign',
        'deal.value', 'deal.pipeline_id', 'deal.stage_id', 'deal.source', 'deal.owner_id',
    ];

    public const OPERATORS = ['equals', 'not_equals', 'contains', 'gte', 'lte', 'is_empty', 'not_empty'];

    private static int $depth = 0;

    public function __construct(private CrmContext $context) {}

    /** An event happened in the current workspace (called by Timeline). */
    public function handle(string $event, array $links): void
    {
        if (self::$depth >= 2 || ! in_array($event, self::TRIGGERS, true) || ! Auth::hasUser()) {
            return; // Loop guard: an action's own events may trigger one more level, no further.
        }
        $automations = Automation::where('trigger', $event)->where('is_active', true)->get();
        if ($automations->isEmpty()) {
            return;
        }
        $subject = $this->subjectFrom($links);
        foreach ($automations as $automation) {
            $this->run($automation, $subject, null);
        }
    }

    /** Time-based triggers for every workspace (scheduler). Returns runs started. */
    public function runIdle(): int
    {
        $count = 0;
        $automations = Automation::withoutGlobalScopes()->whereIn('trigger', self::TIME_TRIGGERS)->where('is_active', true)->get();
        foreach ($automations->groupBy('workspace_id') as $workspaceId => $list) {
            $account = User::find($workspaceId);
            if (! $account) {
                continue;
            }
            $previous = Auth::user();
            Auth::setUser($account); // run as the workspace, exactly like a request does
            try {
                foreach ($list as $automation) {
                    foreach ($this->idleSubjects($automation) as [$subject, $runKey]) {
                        $count += $this->run($automation, $subject, $runKey) ? 1 : 0;
                    }
                }
            } finally {
                $previous ? Auth::setUser($previous) : Auth::forgetUser();
            }
        }

        return $count;
    }

    /** "Test run": how many times this would have fired in the last 30 days (or now, for idle triggers). */
    public function preview(array $definition): int
    {
        $automation = new Automation($definition);
        if (in_array($automation->trigger, self::TIME_TRIGGERS, true)) {
            return collect($this->idleSubjects($automation))->filter(fn ($s) => $this->matches($automation, $s[0]))->count();
        }

        return Activity::where('type', 'event')->where('subject', $automation->trigger)->where('created_at', '>=', now()->subDays(30))
            ->limit(2000)->get()
            ->map(fn ($event) => $this->subjectFrom($event->only(['contact_id', 'lead_id', 'deal_id'])))
            ->filter(fn ($subject) => $this->triggerMatches($automation, $subject) && $this->matches($automation, $subject))
            ->count();
    }

    private function run(Automation $automation, array $subject, ?string $runKey): bool
    {
        if (! $this->triggerMatches($automation, $subject) || ! $this->matches($automation, $subject)) {
            return false;
        }
        $primary = $subject['deal'] ?? $subject['lead'] ?? $subject['contact'];
        if (! $primary) {
            return false;
        }
        if ($runKey && AutomationRun::where('automation_id', $automation->id)->where('run_key', $runKey)->exists()) {
            return false;
        }

        self::$depth++;
        $results = [];
        $status = 'done';
        try {
            DB::transaction(function () use ($automation, $subject, &$results) {
                foreach ($automation->actions as $action) {
                    $results[] = [$action['type'] ?? '?' => $this->act($action, $subject)];
                }
            });
        } catch (\Throwable $e) {
            $status = 'failed';
            $results[] = ['error' => mb_substr($e->getMessage(), 0, 300)];
            Log::warning('CRM automation failed.', ['automation' => $automation->id, 'error' => $e->getMessage()]);
        } finally {
            self::$depth--;
        }

        AutomationRun::create([
            'automation_id' => $automation->id, 'subject_type' => class_basename($primary), 'subject_id' => $primary->id,
            'run_key' => $runKey, 'status' => $status, 'result' => $results,
        ]);
        $automation->forceFill(['runs_count' => $automation->runs_count + 1, 'last_run_at' => now()])->save();

        return true;
    }

    private function triggerMatches(Automation $automation, array $subject): bool
    {
        $stageId = $automation->trigger_config['stage_id'] ?? null;

        return ! ($automation->trigger === 'deal_stage_changed' && $stageId && (int) ($subject['deal']?->stage_id) !== (int) $stageId);
    }

    public function matches(Automation $automation, array $subject): bool
    {
        foreach ($automation->conditions ?? [] as $condition) {
            [$record, $attribute] = array_pad(explode('.', (string) ($condition['field'] ?? ''), 2), 2, null);
            if (! in_array($condition['field'] ?? '', self::FIELDS, true) && ! in_array($condition['field'] ?? '', LeadScorer::SIGNALS, true)) {
                return false;
            }
            $actual = isset($subject[$record]) ? ($subject[$record]->{$attribute} ?? null) : null;
            if (! $this->compare($actual, $condition['operator'] ?? 'equals', $condition['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        if (is_array($actual)) { // tags
            return match ($operator) {
                'contains', 'equals' => in_array(mb_strtolower((string) $expected), array_map('mb_strtolower', $actual), true),
                'not_equals' => ! in_array(mb_strtolower((string) $expected), array_map('mb_strtolower', $actual), true),
                'is_empty' => $actual === [], 'not_empty' => $actual !== [], default => false,
            };
        }
        $a = is_string($actual) ? mb_strtolower($actual) : $actual;
        $e = is_string($expected) ? mb_strtolower($expected) : $expected;

        return match ($operator) {
            'equals' => (string) $a === (string) $e,
            'not_equals' => (string) $a !== (string) $e,
            'contains' => $e !== '' && str_contains((string) $a, (string) $e),
            'gte' => is_numeric($a) && is_numeric($e) && $a >= $e,
            'lte' => is_numeric($a) && is_numeric($e) && $a <= $e,
            'is_empty' => $a === null || $a === '',
            'not_empty' => $a !== null && $a !== '',
            default => false,
        };
    }

    private function act(array $action, array $subject): string
    {
        $contact = $subject['contact'];
        $lead = $subject['lead'];
        $deal = $subject['deal'];
        $owned = $deal ?? $lead ?? $contact;

        switch ($action['type'] ?? null) {
            case 'create_task':
                $assignee = ($action['assignee'] ?? 'owner') === 'owner' ? ($owned->owner_id ?? null) : (int) $action['assignee'];
                Activity::create([
                    'type' => 'task', 'subject' => $this->fill($action['subject'] ?? 'Follow up', $subject),
                    'due_at' => now()->addMinutes(max(0, (int) ($action['due_minutes'] ?? 60))), 'owner_id' => $assignee,
                    'contact_id' => $contact?->id, 'lead_id' => $lead?->id, 'deal_id' => $deal?->id, 'meta' => ['automation' => true],
                ]);

                return 'task';
            case 'assign_owner':
                $members = array_values(array_intersect(array_map('intval', $action['member_ids'] ?? []), $this->context->memberIds()));
                if (! $members) {
                    return 'no eligible member';
                }
                $pick = ($action['mode'] ?? 'member') === 'round_robin'
                    ? collect($members)->sortBy(fn ($id) => Lead::withoutGlobalScope('visibility')->where('owner_id', $id)->whereIn('status', ['new', 'contacted', 'qualified'])->count())->first()
                    : $members[0];
                foreach (array_filter([$owned, $contact && ! $contact->owner_id ? $contact : null]) as $record) {
                    $record->update(['owner_id' => $pick]);
                }

                return 'owner '.$pick;
            case 'add_tag':
                if ($contact && ($tag = trim((string) ($action['tag'] ?? ''))) !== '') {
                    $contact->update(['tags' => array_values(array_unique(array_merge($contact->tags ?? [], [$tag])))]);
                }

                return 'tag';
            case 'set_lifecycle':
                if ($contact && in_array($action['lifecycle'] ?? '', Contact::LIFECYCLES, true)) {
                    $contact->update(['lifecycle' => $action['lifecycle']]);
                }

                return 'lifecycle';
            case 'set_lead_status':
                if ($lead && in_array($action['status'] ?? '', ['new', 'contacted', 'qualified'], true)) {
                    $lead->update(['status' => $action['status']]);
                }

                return 'lead status';
            case 'move_deal_stage':
                $stage = $deal ? Stage::where('pipeline_id', $deal->pipeline_id)->find($action['stage_id'] ?? 0) : null;
                if ($stage && $stage->id !== $deal->stage_id) {
                    app(DealService::class)->move($deal, $stage);
                }

                return 'stage';
            case 'add_to_email_list':
                $list = EmailList::where('user_id', Auth::id())->find($action['email_list_id'] ?? 0);

                return $list && $contact ? 'email list +'.app(EmailListBridge::class)->add($list, collect([$contact]))['added'] : 'no list';
            case 'notify':
                $member = User::whereIn('id', $this->context->memberIds())->find($action['member_id'] ?? 0);
                if ($member?->email) {
                    $text = $this->fill($action['message'] ?? '{name}', $subject);
                    Mail::raw($text."\n\n".($contact ? route('admin.crm.contacts.show', $contact) : ''), fn ($m) => $m->to($member->email)->subject('Socialeaz CRM: '.mb_substr($text, 0, 80)));
                }

                return 'notified';
        }

        return 'unknown action';
    }

    /** {name}, {source}, {deal}, {value} placeholders in task subjects and messages. */
    private function fill(string $text, array $subject): string
    {
        return strtr($text, [
            '{name}' => $subject['contact']?->full_name ?? '', '{source}' => $subject['lead']?->source ?? $subject['contact']?->source ?? '',
            '{deal}' => $subject['deal']?->name ?? '', '{value}' => $subject['deal'] ? number_format((float) $subject['deal']->value) : '',
        ]);
    }

    private function subjectFrom(array $links): array
    {
        $deal = ! empty($links['deal_id']) ? Deal::withoutGlobalScope('visibility')->find($links['deal_id']) : null;
        $lead = ! empty($links['lead_id']) ? Lead::withoutGlobalScope('visibility')->find($links['lead_id']) : null;
        $contactId = $links['contact_id'] ?? $deal?->contact_id ?? $lead?->contact_id;
        $contact = $contactId ? Contact::withoutGlobalScope('visibility')->find($contactId) : null;

        return ['contact' => $contact, 'lead' => $lead, 'deal' => $deal];
    }

    /** @return array<int, array{0: array, 1: string}> */
    private function idleSubjects(Automation $automation): array
    {
        $days = max(1, (int) ($automation->trigger_config['days'] ?? CrmSettings::for((int) Auth::id())['idle_days']));
        $cutoff = now()->subDays($days);

        if ($automation->trigger === 'lead_idle') {
            return Lead::withoutGlobalScope('visibility')->with('contact')->whereIn('status', ['new', 'contacted'])
                ->where('created_at', '<', $cutoff)
                ->whereHas('contact', fn ($c) => $c->withoutGlobalScope('visibility')->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $cutoff)))
                ->limit(500)->get()
                ->map(fn ($lead) => [['contact' => $lead->contact, 'lead' => $lead, 'deal' => null], 'lead:'.$lead->id.':'.($lead->contact->last_activity_at?->timestamp ?? 0)])->all();
        }

        return Deal::withoutGlobalScope('visibility')->with('contact')->where('status', 'open')->where('stage_changed_at', '<', $cutoff)
            ->limit(500)->get()
            ->map(fn ($deal) => [['contact' => $deal->contact, 'lead' => null, 'deal' => $deal], 'deal:'.$deal->id.':'.$deal->stage_changed_at?->timestamp])->all();
    }
}
