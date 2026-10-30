<?php

namespace App\Services\AdServices;

use App\Models\Admin\AdCampaign;
use App\Support\Gemini\GeminiClient;
use Illuminate\Support\Facades\Cache;

/**
 * AI insights for the Facebook campaigns page: Gemini reads a fact sheet
 * built on the server from the account's Meta insights (never numbers sent
 * by the browser) and returns at most three observations with a suggested
 * next step. Suggestions are text only - nothing is changed on Meta.
 */
class FacebookAiInsightsService
{
    public const ACTIONS = ['compare_campaigns', 'review_placements', 'review_audience', 'refresh_creative', 'review_budget', 'none'];

    private const TTL_HOURS = 24;

    public function cached(int $accountId, int $days, string $locale, string $platform = 'facebook'): ?array
    {
        return Cache::get($this->key($accountId, $days, $locale, $platform));
    }

    /** @return array{success: bool, data?: array, message?: string} */
    public function generate(FacebookAdService $facebook, int $accountId, int $userId, int $days, string $locale, string $platform = 'facebook'): array
    {
        $insights = $facebook->insights($days);
        if (!($insights['success'] ?? false)) {
            return ['success' => false, 'message' => $insights['error'] ?? 'Insights from Meta aren’t available yet.'];
        }
        $current = $insights['totals']['current'];
        if (($current['spend'] ?? 0) <= 0 && ($current['impressions'] ?? 0) <= 0) {
            return ['success' => false, 'message' => 'There’s no delivery in this period for the AI to analyse.'];
        }

        $facts = $this->facts($insights, $userId);
        $language = $locale === 'ar' ? 'Arabic (Modern Standard, as used in Saudi business writing)' : 'English';
        $prompt = "You are a senior Meta Ads analyst reviewing one advertiser's account for a business owner in Saudi Arabia/GCC.\n"
            . "Write at most 3 insights in {$language}, most useful first, from the JSON facts below.\n"
            . ($platform === 'instagram' ? "All figures cover Instagram placements only (Feed, Stories, Reels, Explore) of a Meta ad account.\n" : '')
            . "Rules:\n"
            . "- Use ONLY numbers that appear in the facts (you may compute simple differences or percentages from them). Never invent figures, benchmarks or results.\n"
            . "- Describe what the data shows; do not claim what caused it.\n"
            . "- Each insight: a short title (under 70 characters) and a text of 1-2 sentences (under 230 characters) that cites the exact numbers, plus one suggested next step the owner can review.\n"
            . "- 'action' must be one of: " . implode(', ', self::ACTIONS) . ".\n"
            . "- Currency is {$facts['currency']}. Results are counted as '{$facts['result_label']}'.\n"
            . "- If the data is too thin for a point, skip it rather than guess.\n\n"
            . 'Facts: ' . json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = GeminiClient::json($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'insights' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'title' => ['type' => 'STRING'],
                            'text' => ['type' => 'STRING'],
                            'action' => ['type' => 'STRING', 'enum' => self::ACTIONS],
                        ],
                        'required' => ['title', 'text', 'action'],
                    ],
                ],
            ],
            'required' => ['insights'],
        ]);

        if (!$result['success']) {
            return $result;
        }

        $items = collect($result['data']['insights'] ?? [])
            ->filter(fn ($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== '' && trim((string) ($i['text'] ?? '')) !== '')
            ->take(3)
            ->map(fn ($i) => [
                'title' => mb_substr(trim((string) $i['title']), 0, 90),
                'text' => mb_substr(trim((string) $i['text']), 0, 320),
                'action' => in_array($i['action'] ?? null, self::ACTIONS, true) ? $i['action'] : 'none',
            ])->values()->all();

        if (!$items) {
            return ['success' => false, 'message' => 'The AI found nothing it could state from this data - try a longer period.'];
        }

        $data = ['insights' => $items, 'days' => $days, 'locale' => $locale, 'since' => $insights['since'], 'until' => $insights['until'], 'generated_at' => now()->toIso8601String()];
        Cache::put($this->key($accountId, $days, $locale, $platform), $data, now()->addHours(self::TTL_HOURS));

        return ['success' => true, 'data' => $data];
    }

    /** The compact, real-data fact sheet the AI is allowed to use. */
    private function facts(array $insights, int $userId): array
    {
        $round = fn ($v, $d = 2) => $v === null ? null : round((float) $v, $d);
        $totals = fn (?array $t) => $t ? [
            'spend' => $round($t['spend']), 'impressions' => $t['impressions'], 'reach' => $t['reach'] ?? null, 'clicks' => $t['clicks'],
            'ctr_percent' => $t['ctr'] !== null ? $round($t['ctr'] * 100) : null, 'cpc' => $round($t['cpc']),
            'frequency' => !empty($t['reach']) ? $round($t['impressions'] / $t['reach']) : null,
            'results' => $t['results'], 'cost_per_result' => $round($t['cost_per_result']),
        ] : null;

        $names = AdCampaign::where('user_id', $userId)->whereIn('id', array_keys($insights['campaigns']))->pluck('name', 'id');
        $campaigns = collect($insights['campaigns'])->map(fn ($c, $id) => [
            'campaign' => $names[$id] ?? ('ID ' . $id),
            'spend' => $round($c['spend']), 'impressions' => $c['impressions'],
            'ctr_percent' => $c['ctr'] !== null ? $round($c['ctr'] * 100) : null,
            'results' => $c['results'], 'result_type' => $c['result_type'], 'cost_per_result' => $round($c['cost_per_result']),
        ])->sortByDesc('spend')->take(15)->values()->all();

        $details = $insights['details'] ?? [];
        $breakdown = fn ($rows) => $rows === null ? null : collect($rows)->take(8)->map(fn ($r) => [
            'segment' => str_replace('|', ' ', $r['key']), 'spend' => $round($r['spend']), 'results' => $r['results'],
            'cost_per_result' => $r['results'] ? $round($r['spend'] / $r['results']) : null,
        ])->values()->all();

        $type = $insights['totals']['current']['result_type'] ?? null;

        return [
            'period' => ['since' => $insights['since'], 'until' => $insights['until'], 'days' => $insights['days']],
            'currency' => $insights['currency'] ?: 'the account currency',
            'result_label' => $type === 'mixed' ? 'different results per campaign (not comparable)' : str_replace('_', ' ', (string) $type),
            'this_period' => $totals($insights['totals']['current']),
            'previous_period' => $totals($insights['totals']['previous'] ?? null),
            'campaigns' => $campaigns,
            'conversion_path' => $details['funnel'] ?? null,
            'placements' => $breakdown($details['placements'] ?? null),
            'regions' => $breakdown($details['regions'] ?? null),
            'ages' => $breakdown($details['ages'] ?? null),
        ];
    }

    private function key(int $accountId, int $days, string $locale, string $platform = 'facebook'): string
    {
        return 'fb_ai_insights:' . $platform . ':' . $accountId . ':' . $days . ':' . ($locale === 'ar' ? 'ar' : 'en');
    }
}
