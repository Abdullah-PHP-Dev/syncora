<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\Messaging\Message;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI on a contact: summary, next step and a follow-up draft. Reads only this
 * workspace's record (the caller resolved it through the workspace scope),
 * never sends anything, and runs on the existing AI credits (the route is in
 * package_access ai_content, so EnsurePackageFeature reserves and refunds).
 */
class CrmAiService
{
    public function brief(Contact $contact, string $locale): array
    {
        self::ensureEnabled($contact->workspace_id, 'brief');

        return $this->generate($this->prompt($contact, $locale), [
            'summary' => ['type' => 'STRING'],
            'next_steps' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            'follow_up' => ['type' => 'STRING'],
            'sentiment' => ['type' => 'STRING', 'enum' => ['positive', 'neutral', 'negative']],
        ], ['contact' => $contact->id]);
    }

    /** AI-03: a free question about one contact, answered from their record only. */
    public function askContact(Contact $contact, string $question, string $locale): array
    {
        self::ensureEnabled($contact->workspace_id, 'brief');

        return $this->generate($this->context($contact, $locale)
            ."\nAnswer this question from the salesperson using only the information above. If the answer is not there, say so plainly.\nQuestion: ".mb_substr($question, 0, 500),
            ['answer' => ['type' => 'STRING']], ['contact' => $contact->id]);
    }

    /** AI-04: fill the qualification checklist from the conversation and notes. */
    public function qualify(\App\Models\Crm\Lead $lead, string $locale): array
    {
        self::ensureEnabled($lead->workspace_id, 'qualification');
        $contact = $lead->contact()->withoutGlobalScopes()->firstOrFail();
        $criteria = \App\Http\Controllers\Admin\Crm\LeadController::CRITERIA;
        $item = ['type' => 'OBJECT', 'properties' => ['met' => ['type' => 'BOOLEAN'], 'note' => ['type' => 'STRING']], 'required' => ['met', 'note']];

        return $this->generate($this->context($contact, $locale)."\nLead interest: ".($lead->interest ?: '-')."\n"
            .'For each criterion decide if the conversation shows it is met, with a short quote or reason as the note (empty note if unknown). '
            .'Criteria: need (a clear need), budget (budget confirmed), timing (timing or urgency), decision_maker (talking to the decision maker), delivery_area (inside the service or delivery area).',
            array_fill_keys($criteria, $item), ['lead' => $lead->id]);
    }

    /** AI-01: a question about the whole pipeline, answered from aggregated workspace numbers. */
    public function askCrm(int $workspaceId, array $facts, string $question, string $locale): array
    {
        self::ensureEnabled($workspaceId, 'ask');

        return $this->generate('You are a sales analyst inside a CRM. Answer in '.($locale === 'ar' ? 'Arabic' : 'English').'. '
            .'Use only these facts about the business (JSON):'."\n".json_encode($facts, JSON_UNESCAPED_UNICODE)."\n"
            .'Answer the question in at most 6 short sentences or bullet points, with concrete numbers from the facts. If the facts do not cover it, say what is missing.'
            ."\nQuestion: ".mb_substr($question, 0, 500), ['answer' => ['type' => 'STRING'], 'suggestions' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]], ['workspace' => $workspaceId]);
    }

    /** S-11: a workspace can switch each AI feature off. */
    public static function ensureEnabled(int $workspaceId, string $feature): void
    {
        abort_unless((CrmSettings::for($workspaceId)['ai'][$feature] ?? true) !== false, 403, __('crm.ai.disabled'));
    }

    private function generate(string $prompt, array $properties, array $logContext): array
    {
        $apiKey = adminSetting('gemini_api_key');
        abort_unless($apiKey, 422, __('crm.ai.unavailable'));

        $response = Http::timeout(25)
            ->retry(2, fn ($attempt, $e) => RetryPolicy::delayMs($attempt, $e), fn ($e) => RetryPolicy::isRetryable($e), throw: false)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key='.$apiKey, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'response_schema' => ['type' => 'OBJECT', 'properties' => $properties, 'required' => array_keys($properties)],
                ],
            ]);

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = $text ? json_decode($text, true) : null;
        if (! $response->successful() || ! is_array($data)) {
            Log::warning('CRM AI call failed.', ['status' => $response->status()] + $logContext);

            // A failed response refunds the credit (EnsurePackageFeature releases on >= 400).
            abort(502, __('crm.ai.failed'));
        }

        return $data;
    }

    private function prompt(Contact $contact, string $locale): string
    {
        return $this->context($contact, $locale)
            ."Give: a 2-3 sentence summary of what the customer wants and where things stand; up to 3 concrete next steps; "
            ."a short, friendly follow-up message the salesperson could send (do not invent prices or dates that are not above); and the customer's sentiment.";
    }

    private function context(Contact $contact, string $locale): string
    {
        $contact->loadMissing(['company', 'deals.stage', 'leads']);
        $messages = Message::whereIn('conversation_id', $contact->conversations()->pluck('id'))
            ->latest('created_at')->limit(20)->get(['direction', 'body', 'created_at'])->reverse()
            ->map(fn ($m) => ($m->direction === 'inbound' ? 'Customer' : 'Business').': '.mb_substr((string) $m->body, 0, 400))->implode("\n");
        $notes = $contact->activities()->whereIn('type', ['note', 'call', 'meeting'])->latest()->limit(10)->pluck('body')->filter()->implode("\n- ");
        $deals = $contact->deals->map(fn ($d) => "{$d->name} ({$d->stage?->name}, {$d->currency} {$d->value})")->implode('; ');

        return "You are a sales assistant inside a CRM. Answer in ".($locale === 'ar' ? 'Arabic' : 'English').".\n"
            ."Contact: {$contact->full_name}; lifecycle {$contact->lifecycle}; source {$contact->source}; company ".($contact->company?->name ?? '-').".\n"
            ."Deals: ".($deals ?: '-')."\nNotes:\n- ".($notes ?: '-')."\nRecent conversation:\n".($messages ?: '-')."\n";
    }
}
