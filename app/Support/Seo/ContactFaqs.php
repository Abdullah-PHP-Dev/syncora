<?php

namespace App\Support\Seo;

use App\Models\Faq;
use Illuminate\Support\Facades\Schema;
use Mews\Purifier\Facades\Purifier;

/**
 * FAQs on the public contact page (#support-faq): the published system FAQs
 * from Admin > FAQs (the same ones the in-app Help Center shows), in the
 * visitor's language, then English. Falls back to config/marketing-faqs.php
 * when none are published. The page and the FAQPage JSON-LD both read this,
 * so search engines see exactly what visitors see.
 *
 * @return array<int, array{question: string, html: string, text: string, link: ?array}>
 */
class ContactFaqs
{
    public const LIMIT = 30;

    public static function for(string $locale): array
    {
        $faqs = self::published($locale) ?: ($locale !== 'en' ? self::published('en') : []);
        if ($faqs) {
            return $faqs;
        }

        return array_map(fn ($faq) => [
            'question' => __($faq['question'], [], $locale),
            'html' => e(__($faq['answer'], [], $locale)),
            'text' => __($faq['answer'], [], $locale),
            'link' => $faq['link'] ?? null,
        ], config('marketing-faqs.contact', []));
    }

    private static function published(string $language): array
    {
        try {
            if (! Schema::hasTable('faqs')) {
                return [];
            }

            // Category order via a subquery: a join would make the scopes' user_id/status ambiguous.
            $categoryOrder = \App\Models\FaqCategory::select('sort_order')->whereColumn('faq_categories.id', 'faqs.faq_category_id')->limit(1);

            return Faq::system()->published()->where('language', $language)
                ->orderByRaw('faq_category_id is null')->orderBy($categoryOrder)->orderBy('id')
                ->limit(self::LIMIT)->get(['question', 'answer'])
                ->map(function (Faq $faq) {
                    // Answers are rich text written in the admin editor: clean before a public page renders them.
                    $html = Purifier::clean((string) $faq->answer, 'default');
                    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                    return ['question' => trim((string) $faq->question), 'html' => $html, 'text' => $text, 'link' => null];
                })
                ->filter(fn ($faq) => $faq['question'] !== '' && $faq['text'] !== '')
                ->values()->all();
        } catch (\Throwable $e) {
            report($e);

            return []; // a database problem must never break the contact page
        }
    }
}
