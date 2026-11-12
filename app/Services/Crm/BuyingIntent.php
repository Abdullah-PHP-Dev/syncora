<?php

namespace App\Services\Crm;

/**
 * Flags comments and messages that ask about price, availability, delivery
 * or ordering (M-03), in English and Arabic (Gulf phrasing included). A
 * keyword signal for triage, not a decision: people still create the lead.
 */
class BuyingIntent
{
    private const PATTERNS = [
        '/\b(price|prices|cost|how much|hm|quote|discount|offer)\b/i',
        '/\b(available|in stock|sizes?|colou?rs?|deliver(y|ies)?|ship(ping)?|order|buy|purchase|book(ing)?)\b/i',
        '/\b(dm me|send me|whats ?app|location|branch)\b/i',
        '/(سعر|السعر|بكم|كم سعر|كم السعر|تكلفة|خصم|عرض|متوفر|متوفرة|توصيل|يوصل|توصلون|اطلب|أطلب|طلب|شراء|اشتري|أشتري|احجز|حجز|المقاس|المقاسات|الألوان|الوان|واتس|وين الفرع|الموقع)/u',
    ];

    public static function detect(?string $text): bool
    {
        $text = trim((string) $text);
        if ($text === '' || mb_strlen($text) < 3) {
            return false;
        }
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
