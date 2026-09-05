<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;

/**
 * Read-only Help Center - every seller's window onto Socialeaz's own
 * System FAQs (see FaqController's docblock for who authors these).
 * Deliberately queries Faq::system() only, never a seller's own business
 * FAQ - those two knowledge layers stay separate per the BRD's core
 * ownership rule (Socialeaz supports the seller; the seller supports
 * their own customers, not the other way around).
 */
class HelpCenterController extends Controller
{
    public function index(Request $request)
    {
        $categories = FaqCategory::system()->orderBy('sort_order')->get();

        $faqs = Faq::system()
            ->published()
            ->with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('faq_category_id', $request->integer('category')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(fn ($w) => $w->where('question', 'like', $term)->orWhere('answer', 'like', $term));
            })
            ->orderBy('faq_category_id')
            ->get();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'faqs' => $faqs, 'categories' => $categories]);
        }

        // Flat list, same shape as the ajax branch above - HelpCenterBrowser.vue
        // groups by category client-side itself (it needs to re-group after
        // every search/filter refetch anyway, so grouping once more here for
        // just the first paint would be a second, divergent implementation
        // of the same logic).
        return view('admin.help-center.index', ['faqs' => $faqs, 'categories' => $categories]);
    }
}
