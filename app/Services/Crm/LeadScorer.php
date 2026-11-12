<?php

namespace App\Services\Crm;

use App\Models\Crm\Automation;
use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\Messaging\Message;
use Illuminate\Support\Facades\DB;

/**
 * S-06 lead scoring: transparent rules (fit, engagement, intent, negative),
 * capped 0–100, with the reasons stored next to the score. Rules use the
 * automation condition fields plus `signal.*` values computed here:
 *  - signal.messages       inbound Inbox messages from this person
 *  - signal.channels       distinct channels they used (Inbox + identities)
 *  - signal.days_inactive  days since the last CRM activity
 *  - signal.replied_fast   1 when the team answered within an hour (minutes to first reply ≤ 60)
 *  - signal.intent         1 when their messages, comments or lead interest ask about
 *                          price, availability, delivery or ordering (BuyingIntent; no AI credits)
 */
class LeadScorer
{
    public const GROUPS = ['fit', 'engagement', 'intent', 'negative'];

    public const SIGNALS = ['signal.messages', 'signal.channels', 'signal.days_inactive', 'signal.replied_fast', 'signal.intent'];

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'intent_signal' => true,
            'bands' => ['hot' => 75, 'warm' => 50],
            'rules' => [
                ['group' => 'fit', 'field' => 'lead.estimated_value', 'operator' => 'gte', 'value' => 10000, 'points' => 20, 'on' => true],
                ['group' => 'fit', 'field' => 'contact.email', 'operator' => 'not_empty', 'value' => null, 'points' => 5, 'on' => true],
                ['group' => 'engagement', 'field' => 'signal.messages', 'operator' => 'gte', 'value' => 3, 'points' => 15, 'on' => true],
                ['group' => 'engagement', 'field' => 'signal.channels', 'operator' => 'gte', 'value' => 2, 'points' => 10, 'on' => true],
                ['group' => 'engagement', 'field' => 'signal.replied_fast', 'operator' => 'equals', 'value' => 1, 'points' => 15, 'on' => true],
                ['group' => 'intent', 'field' => 'signal.intent', 'operator' => 'equals', 'value' => 1, 'points' => 25, 'on' => true],
                ['group' => 'intent', 'field' => 'lead.source', 'operator' => 'equals', 'value' => 'whatsapp', 'points' => 10, 'on' => true],
                ['group' => 'negative', 'field' => 'signal.days_inactive', 'operator' => 'gte', 'value' => 14, 'points' => -15, 'on' => true],
                ['group' => 'negative', 'field' => 'contact.tags', 'operator' => 'contains', 'value' => 'spam', 'points' => -40, 'on' => true],
            ],
        ];
    }

    public static function config(int $workspaceId): array
    {
        return array_replace(self::defaults(), CrmSettings::for($workspaceId)['scoring'] ?? []);
    }

    public static function band(int $score, array $config): string
    {
        return $score >= $config['bands']['hot'] ? 'hot' : ($score >= $config['bands']['warm'] ? 'warm' : 'cold');
    }

    /** Score a contact (and its open leads) and store score + reasons. */
    public function rescore(Contact $contact): int
    {
        $config = self::config($contact->workspace_id);
        if (! $config['enabled']) {
            return $contact->score;
        }
        $leads = Lead::withoutGlobalScopes()->where('contact_id', $contact->id)->whereNotIn('status', ['converted', 'unqualified'])->whereNull('deleted_at')->get();
        $signals = $this->signals($contact, $leads->first(), $config);
        $best = 0;
        foreach ($leads->isEmpty() ? [null] : $leads as $lead) {
            [$score, $reasons] = $this->evaluate($config, ['contact' => $contact, 'lead' => $lead, 'deal' => null, 'signal' => $signals]);
            if ($lead) {
                $lead->forceFill(['score' => $score, 'score_breakdown' => $reasons])->saveQuietly();
            }
            $best = max($best, $score);
        }
        $contact->forceFill(['score' => $best])->saveQuietly();

        return $best;
    }

    /** @return array{0: int, 1: array<int, array{label: string, points: int, group: string}>} */
    public function evaluate(array $config, array $subject): array
    {
        $engine = app(AutomationEngine::class);
        $total = 0;
        $reasons = [];
        foreach ($config['rules'] as $rule) {
            if (empty($rule['on'])) {
                continue;
            }
            $probe = new Automation(['conditions' => [['field' => $rule['field'], 'operator' => $rule['operator'], 'value' => $rule['value'] ?? null]]]);
            if ($engine->matches($probe, $subject)) {
                $total += (int) $rule['points'];
                $reasons[] = ['group' => $rule['group'], 'field' => $rule['field'], 'operator' => $rule['operator'], 'value' => $rule['value'] ?? null, 'points' => (int) $rule['points']];
            }
        }

        return [max(0, min(100, $total)), $reasons];
    }

    private function signals(Contact $contact, ?Lead $lead, array $config): object
    {
        $conversationIds = DB::table('conversations')->where('crm_contact_id', $contact->id)->pluck('id');
        $inbound = $conversationIds->isEmpty() ? collect() : Message::whereIn('conversation_id', $conversationIds)->where('direction', 'inbound')->latest('id')->limit(50)->get(['conversation_id', 'body', 'created_at']);
        $firstIn = $inbound->min('created_at');
        $firstOut = $firstIn ? Message::whereIn('conversation_id', $conversationIds)->where('direction', 'outbound')->where('created_at', '>=', $firstIn)->min('created_at') : null;
        $channels = DB::table('conversations')->where('crm_contact_id', $contact->id)->distinct()->count('platform')
            + DB::table('crm_contact_identities')->where('contact_id', $contact->id)->whereNotIn('platform', DB::table('conversations')->where('crm_contact_id', $contact->id)->pluck('platform'))->distinct()->count('platform');

        $intent = false;
        if ($config['intent_signal'] && (CrmSettings::for($contact->workspace_id)['ai']['intent_signal'] ?? true)) {
            $texts = $inbound->pluck('body')->push($lead?->interest)->merge(
                DB::table('crm_activities')->where('contact_id', $contact->id)->where('subject', 'comment_to_lead')->pluck('meta')->map(fn ($m) => json_decode((string) $m, true)['comment'] ?? null)
            );
            $intent = $texts->filter()->contains(fn ($t) => BuyingIntent::detect($t));
        }

        return (object) [
            'messages' => $inbound->count(),
            'channels' => $channels,
            'days_inactive' => (int) ($contact->last_activity_at ?? $contact->created_at)?->diffInDays(now()),
            'replied_fast' => $firstIn && $firstOut && \Illuminate\Support\Carbon::parse($firstIn)->diffInMinutes(\Illuminate\Support\Carbon::parse($firstOut)) <= 60 ? 1 : 0,
            'intent' => $intent ? 1 : 0,
        ];
    }

    /** Back-test: of leads created in the last 90 days, how many in each band converted. */
    public function backtest(int $workspaceId): array
    {
        $config = self::config($workspaceId);
        $leads = Lead::forWorkspace($workspaceId)->with('deal:id,status')->where('created_at', '>=', now()->subDays(90))->get(['id', 'score', 'status', 'deal_id']);
        $out = [];
        foreach (['hot', 'warm', 'cold'] as $band) {
            $group = $leads->filter(fn ($l) => self::band((int) $l->score, $config) === $band);
            $won = $group->filter(fn ($l) => $l->status === 'converted' || $l->deal?->status === 'won')->count();
            $out[$band] = ['leads' => $group->count(), 'converted' => $won, 'rate' => $group->count() ? round($won / $group->count() * 100) : null];
        }

        return $out;
    }
}
