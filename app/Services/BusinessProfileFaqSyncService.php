<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\Faq;
use App\Models\FaqCategory;

/**
 * Projects a seller's structured Business Profile into their own
 * Knowledge Base as ordinary Faq rows (source='business_profile',
 * source_key='<field>'), rather than giving AiCopilotService a second,
 * separate "check structured data first" retrieval path. This is a
 * deliberate simplification: a well-phrased structured fact ("what are
 * your hours") already scores very highly on semantic similarity alone,
 * so routing it through the exact same findBestMatch() pipeline achieves
 * the BRD's "prefer structured data over guessing" intent without
 * maintaining two divergent scoring implementations.
 *
 * Idempotent per field: sync() is safe to call on every profile save. A
 * field that's been cleared has its corresponding Faq row removed, not
 * left behind with stale/empty content.
 */
class BusinessProfileFaqSyncService
{
    private const CATEGORY_SLUG = 'business-info';

    public function __construct(private AiCopilotService $copilot)
    {
    }

    public function sync(BusinessProfile $profile): void
    {
        $fields = $this->fields($profile);

        $categoryId = null;

        foreach ($fields as $key => $data) {
            if ($data === null) {
                Faq::where('user_id', $profile->user_id)
                    ->where('source', 'business_profile')
                    ->where('source_key', $key)
                    ->delete();

                continue;
            }

            $categoryId ??= $this->categoryIdFor($profile->user_id);

            $faq = Faq::firstOrNew([
                'user_id'    => $profile->user_id,
                'source'     => 'business_profile',
                'source_key' => $key,
            ]);

            $faq->fill([
                'faq_category_id' => $categoryId,
                'question'        => $data['question'],
                'answer'          => $data['answer'],
                'language'        => 'en',
                'status'          => 'published',
            ]);

            $changed = $faq->isDirty(['question', 'answer']) || !$faq->exists;
            $faq->save();

            // Same "only pay for a Gemini call when the embedded text
            // actually changed" rule KnowledgeBaseController::update()
            // already uses - a profile save that only touched an
            // unrelated field shouldn't re-embed every other field's row.
            if ($changed) {
                $this->copilot->embedFaq($faq);
            }
        }
    }

    /**
     * @return array<string, array{question: string, answer: string}|null>
     */
    private function fields(BusinessProfile $profile): array
    {
        return [
            'business_name' => $profile->business_name ? [
                'question' => 'What is the name of your business?',
                'answer'   => $profile->business_name,
            ] : null,

            'business_hours' => $profile->business_hours ? [
                'question' => 'What are your business hours?',
                'answer'   => $this->formatHours($profile->business_hours),
            ] : null,

            'phone' => $profile->phone ? [
                'question' => 'What is your phone number?',
                'answer'   => $profile->phone,
            ] : null,

            'address' => $profile->address ? [
                'question' => 'Where are you located? What is your address?',
                'answer'   => $profile->address,
            ] : null,

            'delivery_policy' => $profile->delivery_policy ? [
                'question' => 'What is your delivery policy? Do you deliver?',
                'answer'   => $profile->delivery_policy,
            ] : null,

            'return_policy' => $profile->return_policy ? [
                'question' => 'What is your return/refund policy?',
                'answer'   => $profile->return_policy,
            ] : null,
        ];
    }

    private function formatHours(array $hours): string
    {
        $days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
        $closed = array_map('strtolower', $hours['closed'] ?? []);

        $lines = [];

        foreach ($days as $key => $label) {
            if (in_array($key, $closed, true)) {
                $lines[] = "{$label}: Closed";
            } elseif (!empty($hours[$key])) {
                $lines[] = "{$label}: {$hours[$key]}";
            }
        }

        // <br>, not "\n" - every other Faq::answer in this app is real,
        // Purifier-sanitized HTML (FaqController/KnowledgeBaseController
        // both wrap even a one-line answer in <p>...</p>); keeping this
        // one plain text with literal newlines would render as one run-on
        // line wherever answers are shown as HTML (eg. the Help Center),
        // and AiCopilotService::htmlToPlainText() already converts <br>
        // back to a real line break for the customer-facing/agent-reply
        // paths that need plain text instead.
        return $lines ? implode('<br>', $lines) : 'Hours not specified.';
    }

    private function categoryIdFor(int $sellerId): int
    {
        return FaqCategory::firstOrCreate(
            ['user_id' => $sellerId, 'slug' => self::CATEGORY_SLUG],
            ['name' => 'Business Info', 'sort_order' => 0]
        )->id;
    }
}
