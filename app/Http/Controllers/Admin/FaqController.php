<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * System FAQ / Knowledge Base management - Socialeaz's own platform-level
 * FAQs (billing, account setup, cross-platform posting, troubleshooting),
 * authored here and surfaced read-only to every seller via
 * HelpCenterController.
 *
 * Gated to the 'admin' role rather than living under the existing
 * ->prefix('admin')->middleware(['subscription']) route group used by the
 * rest of this app's seller-facing pages: EnsureActiveSubscription
 * hard-aborts(403) any user without the 'seller' role, which would make
 * this permanently unreachable for an admin-only account. These routes
 * sit in the same 'auth'-only, no-subscription-required tier as the
 * dashboard/subscription-select routes instead - see routes/web.php.
 *
 * The 'admin' role itself was previously seeded but never actually
 * checked anywhere in this codebase (confirmed via a full grep before
 * building this) - this is the first real use of it.
 *
 * The UI (FaqManager.vue) is a Vue SPA-within-the-page rather than
 * Blade+jQuery: index() does double duty - a plain browser GET (no
 * X-Requested-With) renders the Blade wrapper with the first page of
 * data as Vue props for a no-flash initial paint, while every filter/
 * pagination change after that is an axios GET (window.axios sets
 * X-Requested-With globally in bootstrap.js) that gets pure JSON back
 * instead of a full page reload. store/update/destroy/storeCategory are
 * JSON-only now - only the Vue component ever calls them.
 */
class FaqController extends Controller
{
    /**
     * Inline guard, not a constructor $this->middleware() callback - this
     * app's base Controller (app/Http/Controllers/Controller.php) is bare
     * (no ControllerMiddlewareOptions/HasMiddleware wiring, a Laravel 11+
     * change), and no other controller here relies on that removed
     * helper either. Called at the top of every action instead.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403, 'System FAQ management is restricted to Socialeaz admins.');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $categories = FaqCategory::system()->orderBy('sort_order')->get();

        $faqs = Faq::system()
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

        return view('admin.faqs.index', compact('faqs', 'categories'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $this->validated($request);

        $faq = Faq::create([
            'user_id'         => null,
            'faq_category_id' => $validated['faq_category_id'],
            'question'        => $validated['question'],
            'answer'          => $validated['answer'],
            'language'        => $validated['language'],
            'status'          => $validated['status'],
            'tags'            => $validated['tags'],
        ])->load('category');

        return response()->json(['success' => true, 'faq' => $faq, 'message' => 'FAQ "' . Str::limit($faq->question, 60) . '" created.']);
    }

    public function update(Request $request, Faq $faq)
    {
        $this->authorizeAdmin();
        abort_unless(is_null($faq->user_id), 404);

        $validated = $this->validated($request);

        $faq->update([
            'faq_category_id' => $validated['faq_category_id'],
            'question'        => $validated['question'],
            'answer'          => $validated['answer'],
            'language'        => $validated['language'],
            'status'          => $validated['status'],
            'tags'            => $validated['tags'],
        ]);

        return response()->json(['success' => true, 'faq' => $faq->fresh('category'), 'message' => 'FAQ updated.']);
    }

    public function destroy(Faq $faq)
    {
        $this->authorizeAdmin();
        abort_unless(is_null($faq->user_id), 404);

        $faq->delete();

        return response()->json(['success' => true, 'message' => 'FAQ deleted.']);
    }

    /**
     * Category management is intentionally minimal (name only, no
     * dedicated CRUD screen) - the BRD lists categories as a first-class
     * concept but this Phase 1 pass keeps them to what's needed to
     * organize the Help Center without building a second full CRUD UI
     * for what is, so far, just a label.
     */
    public function storeCategory(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $category = FaqCategory::create([
            'user_id' => null,
            'name'    => $validated['name'],
            'slug'    => Str::slug($validated['name']) . '-' . Str::random(4),
        ]);

        return response()->json(['success' => true, 'category' => $category, 'message' => 'Category "' . $category->name . '" created.']);
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

        // 'nullable' rules aren't guaranteed a key in validate()'s return
        // when the field is absent from the request entirely (confirmed
        // live: omitting tags outright, not just sending it empty, threw
        // "Undefined array key" here) - ?? covers both cases, for every
        // nullable field this normalizes, not just tags.
        $validated['tags'] = ($validated['tags'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $validated['tags']))))
            : [];

        $validated['faq_category_id'] = $validated['faq_category_id'] ?? null;

        return $validated;
    }
}
