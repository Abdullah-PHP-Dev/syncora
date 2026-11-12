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
        $apiKey = adminSetting('gemini_api_key');
        abort_unless($apiKey, 422, __('crm.ai.unavailable'));

        $response = Http::timeout(20)
            ->retry(2, fn ($attempt, $e) => RetryPolicy::delayMs($attempt, $e), fn ($e) => RetryPolicy::isRetryable($e), throw: false)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key='.$apiKey, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt($contact, $locale)]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'summary' => ['type' => 'STRING'],
                            'next_steps' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                            'follow_up' => ['type' => 'STRING'],
                            'sentiment' => ['type' => 'STRING', 'enum' => ['positive', 'neutral', 'negative']],
                        ],
                        'required' => ['summary', 'next_steps', 'follow_up', 'sentiment'],
                    ],
                ],
            ]);

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = $text ? json_decode($text, true) : null;
        if (! $response->successful() || ! is_array($data)) {
            Log::warning('CRM AI brief failed.', ['status' => $response->status(), 'contact' => $contact->id]);

            // A failed response refunds the credit (EnsurePackageFeature releases on >= 400).
            abort(502, __('crm.ai.failed'));
        }

        return $data;
    }

    private function prompt(Contact $contact, string $locale): string
    {
        $contact->loadMissing(['company', 'deals.stage', 'leads']);
        $messages = Message::whereIn('conversation_id', $contact->conversations()->pluck('id'))
            ->latest('created_at')->limit(20)->get(['direction', 'body', 'created_at'])->reverse()
            ->map(fn ($m) => ($m->direction === 'inbound' ? 'Customer' : 'Business').': '.mb_substr((string) $m->body, 0, 400))->implode("\n");
        $notes = $contact->activities()->whereIn('type', ['note', 'call', 'meeting'])->latest()->limit(10)->pluck('body')->filter()->implode("\n- ");
        $deals = $contact->deals->map(fn ($d) => "{$d->name} ({$d->stage?->name}, {$d->currency} {$d->value})")->implode('; ');

        return "You are a sales assistant inside a CRM. Answer in ".($locale === 'ar' ? 'Arabic' : 'English').".\n"
            ."Contact: {$contact->full_name}; lifecycle {$contact->lifecycle}; source {$contact->source}; company ".($contact->company?->name ?? '-').".\n"
            ."Deals: ".($deals ?: '-')."\nNotes:\n- ".($notes ?: '-')."\nRecent conversation:\n".($messages ?: '-')."\n"
            ."Give: a 2-3 sentence summary of what the customer wants and where things stand; up to 3 concrete next steps; "
            ."a short, friendly follow-up message the salesperson could send (do not invent prices or dates that are not above); and the customer's sentiment.";
    }
}
