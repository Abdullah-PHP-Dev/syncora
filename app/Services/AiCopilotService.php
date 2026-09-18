<?php

namespace App\Services;

use App\Models\Faq;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 of the AI Copilot + FAQ + Ticket System BRD: retrieval +
 * confidence scoring for a seller's own Knowledge Base (Faq rows where
 * user_id is that seller - see Faq model / KnowledgeBaseController).
 *
 * Scope actually built this pass, stated plainly: this service answers
 * "what does the seller's Knowledge Base say, and how confident should
 * we be", nothing more. It does NOT auto-send anything to a customer -
 * wiring "auto-reply above threshold" into every inbound-message webhook
 * (Meta/Instagram/WhatsApp/Telegram/X each have their own controller -
 * confirmed while grounding this feature, same fan-out problem the
 * notification-center plan hit for inbound comments) and every
 * platform's outbound-send path is a separate, larger integration task.
 * What's built here is the safe, immediately useful slice: a real
 * confidence-scored match an agent can review and send with one click
 * from the existing Unified Inbox - see CopilotController::findAnswer().
 *
 * Composite confidence score (BRD section 5.2), weighted:
 *   - semantic similarity   50% - cosine similarity between Gemini
 *                                 embeddings of the customer message and
 *                                 the candidate FAQ's question+answer
 *   - keyword/entity overlap 20% - Jaccard overlap of non-stopword tokens,
 *                                  catches exact-term matches embeddings
 *                                  can blur past (eg. a specific city name)
 *   - historical accuracy    20% - this FAQ's own helpful/unhelpful ratio
 *   - context consistency    10% - overlap between the FAQ and the
 *                                  conversation's recent messages, not
 *                                  just the single latest one
 *
 * Grounding constraint (BRD 5.1, "AI selects and lightly formats an
 * approved reply, not generates one"): the suggested reply is the
 * matched FAQ's answer verbatim - no rephrasing pass. A "lightly adapt
 * the tone" step is a real, separate enhancement (another Gemini call,
 * another failure mode, another chance to drift from the source text)
 * deliberately left out rather than half-built silently.
 */
class AiCopilotService
{
    private const EMBEDDING_MODEL = 'gemini-embedding-001';

    /**
     * Default routing thresholds - BRD 5.2 calls these out as "a
     * per-tenant configurable setting", which isn't built yet (would need
     * a per-seller settings row; this app's adminSetting() is a single
     * flat, installation-wide table, not seller-scoped - see
     * FaqController's grounding notes on this app's real tenancy shape).
     * Hardcoded to the BRD's own stated defaults until that's built.
     */
    public const AUTO_REPLY_THRESHOLD = 80;
    public const SUGGESTED_THRESHOLD = 50;

    private const WEIGHTS = [
        'semantic'   => 0.5,
        'keyword'    => 0.2,
        'historical' => 0.2,
        'context'    => 0.1,
    ];

    private const STOPWORDS = [
        'the', 'a', 'an', 'is', 'are', 'do', 'does', 'you', 'i', 'to', 'for',
        'of', 'and', 'or', 'in', 'on', 'at', 'my', 'your', 'it', 'this',
        'that', 'how', 'what', 'when', 'where', 'can', 'will', 'with', 'be',
        'have', 'has', 'me', 'we', 'us', 'our',
    ];

    /**
     * Raw Gemini embedding call - shared by embedFaq() (called once, at
     * FAQ save time) and findBestMatch() (called once per incoming
     * customer message, never once per candidate FAQ - see this class's
     * docblock for why that split matters for both cost and the BRD's
     * own <500ms search-latency NFR).
     */
    public function embed(string $text): ?array
    {
        $apiKey = adminSetting('gemini_api_key_free');

        if (empty($apiKey) || trim($text) === '') {
            return null;
        }

        // Same execution-time-budget gotcha as PostController::
        // generateAiContent() - retry()'s sleeps count against PHP's
        // whole-request wall clock, not just Http::timeout().
        set_time_limit(60);

        $response = Http::timeout(15)
            ->retry(3, fn ($attempt, $exception) => RetryPolicy::delayMs($attempt, $exception), fn ($exception) => RetryPolicy::isRetryable($exception), throw: false)
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . self::EMBEDDING_MODEL . ':embedContent?key=' . $apiKey,
                ['content' => ['parts' => [['text' => mb_substr($text, 0, 8000)]]]]
            );

        if (!$response->successful()) {
            Log::warning('Gemini embedding request failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        }

        return $response->json('embedding.values');
    }

    /**
     * Called from FaqController/KnowledgeBaseController on create/update.
     * Embeds question+answer together (not just the question) so a
     * customer message that echoes phrasing from the ANSWER - eg. asking
     * about "2-3 business days" - can still surface the right FAQ even if
     * their wording barely resembles the stored question text.
     *
     * Deliberately swallows a failed embed (logs a warning, leaves the
     * row's embedding null) rather than blocking the save - an admin/
     * seller authoring a FAQ shouldn't lose their work because Gemini is
     * rate-limited at that exact moment. A null-embedding FAQ still
     * contributes its keyword/historical/context score components; it
     * just won't be found via semantic similarity until re-saved (or a
     * future batch re-embed job runs - not built this pass).
     */
    public function embedFaq(Faq $faq): void
    {
        $vector = $this->embed($faq->question . "\n" . $faq->answer);

        if ($vector === null) {
            return;
        }

        $faq->forceFill([
            'embedding'       => $vector,
            'embedding_model' => self::EMBEDDING_MODEL,
        ])->saveQuietly();
    }

    public static function cosineSimilarity(array $a, array $b): float
    {
        if (!$a || !$b || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i];
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff($words, self::STOPWORDS));
    }

    public static function keywordOverlap(string $a, string $b): float
    {
        $tokensA = array_unique(self::tokenize($a));
        $tokensB = array_unique(self::tokenize($b));

        if (!$tokensA || !$tokensB) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * No feedback yet defaults to a genuinely neutral 0.5, not a
     * favorable one.
     *
     * This was originally 0.7 ("brand-new FAQ shouldn't be penalized for
     * having zero votes"), but live testing (a full role-based +
     * multi-message conversation test) surfaced a real bug that default
     * caused: 0.7 * 20% weight + contextConsistency's old no-context
     * default of 1.0 * 10% weight added up to a flat +0.24 floor handed
     * to every single FAQ candidate regardless of actual relevance - it
     * alone was enough to push a content-free "Hi there!" greeting to
     * 51% confidence and an incorrect auto-suggestion. A neutral 0.5 for
     * "no data either way" doesn't inflate an irrelevant match, while a
     * FAQ that actually earns positive feedback still climbs above it.
     */
    public static function historicalAccuracy(Faq $faq): float
    {
        $total = $faq->helpful_count + $faq->unhelpful_count;

        return $total === 0 ? 0.5 : $faq->helpful_count / $total;
    }

    /**
     * Keyword overlap between the FAQ and the conversation's recent
     * messages (not just the single message being answered right now) -
     * a real, lightweight stand-in for the BRD's "consistent with the
     * conversation's established intent" without a separate intent-
     * classification model.
     *
     * No prior context (a conversation's first message) now scores a
     * neutral 0.5, not 1.0 - see historicalAccuracy()'s docblock for why
     * an optimistic "nothing to be inconsistent with yet" default was
     * actively wrong: combined with that other default, it was enough on
     * its own to push irrelevant first messages over the suggestion
     * threshold. A genuine absence of signal should score neutral, not
     * favorable, on both components.
     */
    public static function contextConsistency(string $faqQuestion, array $recentMessages): float
    {
        if (!$recentMessages) {
            return 0.5;
        }

        return self::keywordOverlap($faqQuestion, implode(' ', $recentMessages));
    }

    /**
     * The actual retrieval + scoring pass for one inbound customer
     * message. $recentMessages is prior message bodies in the same
     * conversation, oldest-to-newest excluded of the current one - see
     * CopilotController::findAnswer() for how that's gathered.
     */
    public function findBestMatch(string $customerMessage, int $sellerUserId, array $recentMessages = []): array
    {
        $candidates = Faq::ownedBy($sellerUserId)->published()->whereNotNull('embedding')->get();

        if ($candidates->isEmpty()) {
            return $this->result(null, 0, [
                'semantic_similarity' => 0, 'keyword_overlap' => 0,
                'historical_accuracy' => 0, 'context_consistency' => 0,
            ]);
        }

        // Semantic similarity is scored TWICE per candidate and the
        // better of the two is kept - once against the message alone,
        // once against recent context + the message concatenated - not
        // just the context-blended version alone. Live testing found
        // that blending unconditionally cuts both ways: it correctly
        // rescues a context-dependent follow-up like "how many days do I
        // have to do that?" (meaningless alone, but clearly about
        // returns once the preceding message is included), but it just
        // as easily WORSENS a clean topic switch - "are you open on
        // Friday?" scored worse once a prior, unrelated returns message
        // got blended in, since that stale context pulled the embedding
        // away from the hours FAQ it should have matched on its own
        // merits. Taking the max of both scores gets the benefit of
        // context without that regression: a topic switch still wins on
        // its own (message-alone) score, while a genuine follow-up wins
        // on its (message+context) score.
        //
        // keywordOverlap() below stays scoped to just the current message
        // deliberately - exact-term matching shouldn't get diluted by
        // older messages' vocabulary bleeding into it either.
        $messageOnlyVector = $this->embed($customerMessage);
        $contextualVector = $recentMessages
            ? $this->embed(trim(implode(' ', [...$recentMessages, $customerMessage])))
            : null;

        $scored = $candidates->map(function (Faq $faq) use ($customerMessage, $messageOnlyVector, $contextualVector, $recentMessages) {
            $semanticMessageOnly = $messageOnlyVector ? self::cosineSimilarity($messageOnlyVector, $faq->embedding) : 0.0;
            $semanticContextual = $contextualVector ? self::cosineSimilarity($contextualVector, $faq->embedding) : 0.0;
            $semantic = max($semanticMessageOnly, $semanticContextual);
            $keyword = self::keywordOverlap($customerMessage, $faq->question);
            $historical = self::historicalAccuracy($faq);
            $context = self::contextConsistency($faq->question, $recentMessages);

            $composite = ($semantic * self::WEIGHTS['semantic'])
                + ($keyword * self::WEIGHTS['keyword'])
                + ($historical * self::WEIGHTS['historical'])
                + ($context * self::WEIGHTS['context']);

            return [
                'faq'        => $faq,
                'confidence' => (int) round($composite * 100),
                'breakdown'  => [
                    'semantic_similarity' => (int) round($semantic * 100),
                    'keyword_overlap'     => (int) round($keyword * 100),
                    'historical_accuracy' => (int) round($historical * 100),
                    'context_consistency' => (int) round($context * 100),
                ],
            ];
        })->sortByDesc('confidence')->values();

        $best = $scored->first();

        return $this->result($best['faq'], $best['confidence'], $best['breakdown']);
    }

    private function result(?Faq $faq, int $confidence, array $breakdown): array
    {
        return [
            'status'              => $faq && $confidence >= self::SUGGESTED_THRESHOLD ? 'suggested' : 'no_match',
            'faq'                 => $faq,
            'confidence'          => $confidence,
            'auto_reply_eligible' => $confidence >= self::AUTO_REPLY_THRESHOLD,
            'breakdown'           => $breakdown,
            'suggested_reply'     => $confidence >= self::SUGGESTED_THRESHOLD ? $faq?->answer : null,
        ];
    }
}
