<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Services\AiCopilotService;
use Illuminate\Console\Command;

/**
 * Runs hourly (see bootstrap/app.php) as a backstop for
 * AiCopilotService::embedFaq() - a Gemini failure at save time swallows
 * the error and leaves embedding null forever (see that method's own
 * docblock), and a pure draft->published status change never triggers an
 * embed at all (FaqController/KnowledgeBaseController only re-embed
 * "if wasChanged(['question','answer'])"). Every retrieval path
 * (findBestMatch/findBestSystemMatch) silently skips a null-embedding row
 * via whereNotNull('embedding') - there's no error surfaced anywhere else,
 * so without this sweep a failed embed stays invisible forever.
 *
 * Scoped to published() only - a draft isn't reachable by any search path
 * yet, so embedding it here would just be a wasted Gemini call; the
 * moment it's published, this same sweep picks it up on its next run.
 */
class ReembedFaqs extends Command
{
    protected $signature = 'ai-copilot:reembed-faqs {--limit=200 : Maximum number of FAQs to process in this run}';

    protected $description = 'Re-embed published FAQs with a missing or stale-model embedding';

    public function handle(AiCopilotService $copilot): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $total = 0;
        $embedded = 0;

        // chunkById() ignores a pre-applied ->limit() (it sets its own
        // take() per chunk internally), so the total-processed cap has to
        // be enforced by returning false from the closure once reached,
        // not by a query-level limit().
        Faq::published()
            ->where(fn ($q) => $q->whereNull('embedding')->orWhere('embedding_model', '!=', AiCopilotService::EMBEDDING_MODEL))
            ->orderBy('id')
            ->chunkById(50, function ($faqs) use ($copilot, &$total, &$embedded, $limit) {
                foreach ($faqs as $faq) {
                    if ($total >= $limit) {
                        return false;
                    }

                    $total++;
                    $copilot->embedFaq($faq);

                    if ($faq->fresh()->embedding !== null) {
                        $embedded++;
                    }
                }

                return $total < $limit;
            });

        $failed = $total - $embedded;
        $this->info("Embedded {$embedded} of {$total} FAQs ({$failed} failed - will retry next run).");

        return self::SUCCESS;
    }
}
