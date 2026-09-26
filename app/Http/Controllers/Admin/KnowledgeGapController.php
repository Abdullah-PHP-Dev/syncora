<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeGapReport;
use App\Services\AiCopilotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Questions the AI Copilot couldn't answer for this seller's own
 * customers (ProcessAiCopilotReply::recordKnowledgeGap()), deduplicated
 * by normalized wording. Turning a gap into a FAQ always creates a DRAFT
 * (never published automatically) - the spec's own "don't auto-publish
 * AI-surfaced knowledge" rule, same reasoning KnowledgeBaseController
 * already follows for every FAQ.
 */
class KnowledgeGapController extends Controller
{
    public function __construct(private AiCopilotService $copilot)
    {
    }

    public function index(Request $request)
    {
        $gaps = KnowledgeGapReport::ownedBy(Auth::id())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('occurrence_count')
            ->orderByDesc('last_occurred_at')
            ->paginate(15)
            ->withQueryString();

        $categories = FaqCategory::ownedBy(Auth::id())->orderBy('sort_order')->get();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'gaps' => $gaps]);
        }

        return view('admin.ai-copilot.knowledge-gaps', compact('gaps', 'categories'));
    }

    public function convertToFaq(Request $request, KnowledgeGapReport $gap)
    {
        abort_unless($gap->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'answer'           => ['required', 'string'],
            'faq_category_id'  => ['nullable', 'exists:faq_categories,id'],
        ]);

        // Same re-check as KnowledgeBaseController::validated() - a
        // category id must actually belong to this seller, not just exist.
        if (!empty($validated['faq_category_id'])) {
            $ownsCategory = FaqCategory::ownedBy(Auth::id())->whereKey($validated['faq_category_id'])->exists();
            abort_unless($ownsCategory, 403);
        }

        $answer = \Mews\Purifier\Facades\Purifier::clean($validated['answer'], 'default');

        $faq = Faq::create([
            'user_id'         => Auth::id(),
            'faq_category_id' => $validated['faq_category_id'] ?? null,
            'question'        => $gap->question,
            'answer'          => $answer,
            'language'        => 'en',
            // Draft, not published - the seller reviews it in the
            // Knowledge Base like any hand-authored FAQ before it's ever
            // used to answer a real customer.
            'status'          => 'draft',
        ]);

        $this->copilot->embedFaq($faq);

        $gap->update(['status' => 'faq_created', 'suggested_faq_id' => $faq->id]);

        return response()->json([
            'success' => true,
            'faq'     => $faq,
            'message' => 'Draft FAQ "' . Str::limit($faq->question, 60) . '" created - review and publish it from your Knowledge Base.',
        ]);
    }

    public function ignore(KnowledgeGapReport $gap)
    {
        abort_unless($gap->user_id === Auth::id(), 403);

        $gap->update(['status' => 'ignored']);

        return response()->json(['success' => true, 'message' => 'Dismissed.']);
    }

    /**
     * "I'm looking into this, don't let it get re-flagged as new while I
     * do" - a manual triage state between 'new' and an eventual
     * 'faq_created'/'ignored'/'resolved'. QA-audit finding: this status
     * (and 'resolved') existed in the migration's enum and the view's
     * badge styling but nothing ever set them - closing that gap.
     */
    public function markUnderReview(KnowledgeGapReport $gap)
    {
        abort_unless($gap->user_id === Auth::id(), 403);

        $gap->update(['status' => 'under_review']);

        return response()->json(['success' => true, 'message' => 'Marked under review.']);
    }

    /**
     * For a gap that was handled some other way than a new FAQ (eg. the
     * seller already covers it in an existing FAQ's wording, or answered
     * it directly and it's unlikely to recur) - distinct from 'ignored'
     * (never going to be addressed) and 'faq_created' (addressed via a
     * new FAQ specifically).
     */
    public function resolve(KnowledgeGapReport $gap)
    {
        abort_unless($gap->user_id === Auth::id(), 403);

        $gap->update(['status' => 'resolved']);

        return response()->json(['success' => true, 'message' => 'Marked resolved.']);
    }
}
