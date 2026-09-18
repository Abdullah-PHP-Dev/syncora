<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Services\AiCopilotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A seller's own business Knowledge Base - Tier 2 of the BRD ("Subscriber
 * FAQ + AI Copilot Journey"): business hours, delivery, pricing,
 * policies, etc, authored by the seller and (once Phase 3 - AI Copilot
 * retrieval - is built) matched against their customers' inbound
 * messages. Deliberately the mirror image of FaqController (System FAQ):
 * every query here is scoped to Auth::id() instead of user_id IS NULL,
 * so a seller can only ever see/edit their own entries - the "tenant
 * isolation" rule from the BRD, enforced the way this app actually does
 * scoping (user_id), not a separate tenant_id column.
 *
 * Lives under the normal ->middleware(['subscription']) route group
 * (unlike FaqController/TicketController) because this is a core seller
 * feature on par with Posts/Ads/Chats, not a support-access path that
 * must stay reachable without an active subscription.
 *
 * Same Vue-SPA-in-page shape as FaqController: index() serves the Blade
 * wrapper + first-page props on a plain browser GET, JSON on an axios
 * (X-Requested-With) GET; the mutation endpoints are JSON-only, called
 * only by KnowledgeBaseManager.vue (which is FaqManager.vue reused with
 * this controller's URLs - one Vue component, two backends).
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(private AiCopilotService $copilot)
    {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();

        $categories = FaqCategory::ownedBy($userId)->orderBy('sort_order')->get();

        $faqs = Faq::ownedBy($userId)
            ->with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('faq_category_id', $request->integer('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(fn ($w) => $w->where('question', 'like', $term)->orWhere('answer', 'like', $term));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'success'    => true,
                'faqs'       => $faqs,
                'categories' => $categories,
            ]);
        }

        return view('admin.knowledge-base.index', compact('faqs', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $faq = Faq::create([
            'user_id'         => Auth::id(),
            'faq_category_id' => $validated['faq_category_id'],
            'question'        => $validated['question'],
            'answer'          => $validated['answer'],
            'language'        => $validated['language'],
            'status'          => $validated['status'],
            'tags'            => $validated['tags'],
        ])->load('category');

        // Synchronous, not queued - this is a low-frequency admin/seller
        // authoring action (not a webhook on the AI Copilot's own hot
        // path), so the ~1-2s Gemini embedding call adding to this one
        // save is an acceptable trade against the complexity of a queue
        // worker process this app doesn't otherwise require. A failed
        // embed doesn't fail the save - see embedFaq()'s docblock.
        $this->copilot->embedFaq($faq);

        return response()->json(['success' => true, 'faq' => $faq, 'message' => 'FAQ "' . Str::limit($faq->question, 60) . '" created.']);
    }

    public function update(Request $request, Faq $faq)
    {
        $this->authorizeOwner($faq);

        $validated = $this->validated($request);

        $faq->update([
            'faq_category_id' => $validated['faq_category_id'],
            'question'        => $validated['question'],
            'answer'          => $validated['answer'],
            'language'        => $validated['language'],
            'status'          => $validated['status'],
            'tags'            => $validated['tags'],
        ]);

        // Only re-embed if the text that was actually embedded changed -
        // a status/category/tags-only edit doesn't need a fresh Gemini
        // call, the existing vector is still accurate.
        if ($faq->wasChanged(['question', 'answer'])) {
            $this->copilot->embedFaq($faq);
        }

        return response()->json(['success' => true, 'faq' => $faq->fresh('category'), 'message' => 'FAQ updated.']);
    }

    public function destroy(Faq $faq)
    {
        $this->authorizeOwner($faq);

        $faq->delete();

        return response()->json(['success' => true, 'message' => 'FAQ deleted.']);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $category = FaqCategory::create([
            'user_id' => Auth::id(),
            'name'    => $validated['name'],
            'slug'    => Str::slug($validated['name']) . '-' . Str::random(4),
        ]);

        return response()->json(['success' => true, 'category' => $category, 'message' => 'Category "' . $category->name . '" created.']);
    }

    /**
     * Route-model-bound $faq could be any seller's row (or a System FAQ
     * with user_id null) - this is the actual tenant-isolation
     * enforcement point, checked server-side on every write, never left
     * to the client-supplied form to imply ownership.
     */
    private function authorizeOwner(Faq $faq): void
    {
        abort_unless($faq->user_id === Auth::id(), 403);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'faq_category_id' => ['nullable', 'exists:faq_categories,id'],
            'question'        => ['required', 'string', 'max:500'],
            'answer'          => ['required', 'string'],
            'language'        => ['required', 'string', 'max:10'],
            'status'          => ['required', 'in:draft,published'],
            'tags'            => ['nullable', 'string', 'max:500'],
        ]);

        // A seller must not be able to attach their FAQ to another
        // seller's private category (or a category doesn't exist at
        // all) by guessing/tampering with the id - re-check ownership
        // here rather than trusting the 'exists' rule alone.
        if (!empty($validated['faq_category_id'])) {
            $ownsCategory = FaqCategory::ownedBy(Auth::id())->whereKey($validated['faq_category_id'])->exists();
            abort_unless($ownsCategory, 403);
        }

        $validated['tags'] = ($validated['tags'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $validated['tags']))))
            : [];

        $validated['faq_category_id'] = $validated['faq_category_id'] ?? null;

        return $validated;
    }
}
