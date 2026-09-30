<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeGapReport;
use App\Services\AiCopilotService;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
 * only by KnowledgeBaseManager.vue (the seller-facing redesign - System
 * FAQ management still uses the simpler shared FaqManager.vue).
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(private AiCopilotService $copilot)
    {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();

        $categories = FaqCategory::ownedBy($userId)->orderBy('sort_order')->orderBy('name')->get();

        $faqs = $this->filteredQuery($request, $userId)
            ->paginate(10)
            ->withQueryString();

        $payload = [
            'faqs'       => $faqs,
            'categories' => $categories,
            'stats'      => $this->stats($userId),
        ];

        if ($request->ajax()) {
            return response()->json(['success' => true] + $payload);
        }

        return view('admin.knowledge-base.index', $payload);
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
            'copilot_enabled' => $validated['copilot_enabled'],
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
            'copilot_enabled' => $validated['copilot_enabled'],
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

    /**
     * Status change or delete for several of the seller's own rows at
     * once (the listing's checkbox selection) - also used for the single-
     * row Publish/Unpublish/Archive menu entries with a one-id array.
     * Scoped by ownedBy() in the query itself, so ids belonging to another
     * seller are silently ignored rather than touched.
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids'    => ['required', 'array', 'max:500'],
            'ids.*'  => ['integer'],
            'action' => ['required', 'in:publish,draft,archive,delete'],
        ]);

        $query = Faq::ownedBy(Auth::id())->whereIn('id', $validated['ids']);

        if ($validated['action'] === 'delete') {
            $count = $query->get()->each->delete()->count();

            return response()->json(['success' => true, 'message' => "{$count} " . Str::plural('entry', $count) . ' deleted.']);
        }

        $status = ['publish' => 'published', 'draft' => 'draft', 'archive' => 'archived'][$validated['action']];

        // Per-model update (not a mass query update) so LogsActivity
        // records each status change the same way a single edit does.
        $count = $query->get()->each(fn (Faq $faq) => $faq->update(['status' => $status]))->count();

        return response()->json(['success' => true, 'message' => "{$count} " . Str::plural('entry', $count) . " marked as {$status}."]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^bx[s]?-[a-z0-9-]+$/'],
        ]);

        $category = FaqCategory::create([
            'user_id' => Auth::id(),
            'name'    => $validated['name'],
            'icon'    => $validated['icon'] ?? null,
            'slug'    => Str::slug($validated['name']) . '-' . Str::random(4),
        ]);

        return response()->json(['success' => true, 'category' => $category, 'message' => 'Category "' . $category->name . '" created.']);
    }

    /**
     * CSV download of the seller's whole Knowledge Base, in the same
     * column layout import() accepts - so an export can be edited in a
     * spreadsheet and re-imported. Exports only the ticked rows when
     * ?ids=1,2,3 is passed, otherwise everything matching the listing's
     * current filters (same query as index()).
     *
     * Built in memory and returned as a regular Illuminate Response, not
     * streamDownload(): LaravelLocalization's LocaleCookieRedirect
     * middleware calls withCookie() on every response, which Symfony's
     * StreamedResponse doesn't have (500 "Call to undefined method").
     */
    public function export(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->unique();

        $faqs = $ids->isNotEmpty()
            ? Faq::ownedBy(Auth::id())->with('category')->whereIn('id', $ids)->latest('updated_at')->get()
            : $this->filteredQuery($request, Auth::id())->get();

        $out = fopen('php://temp', 'r+');
        // UTF-8 BOM so Excel opens Arabic text correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CSV_COLUMNS);

        foreach ($faqs as $faq) {
            fputcsv($out, [
                $faq->question,
                strip_tags($faq->answer),
                $faq->category->name ?? '',
                $faq->language,
                $faq->status,
                implode(', ', $faq->tags ?? []),
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $filename = 'knowledge-base-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * CSV upload (columns: question, answer, category, language, status,
     * tags - header row required, same layout as export()). Unknown
     * category names are created on the fly. Rows are not embedded here -
     * a 500-row file would mean 500 sequential Gemini calls inside one
     * request - the hourly ai-copilot:reembed-faqs job picks up every row
     * with a null embedding instead.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $userId = Auth::id();
        $handle = fopen($request->file('file')->getRealPath(), 'r');

        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => Str::lower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);

        if (!in_array('question', $header, true) || !in_array('answer', $header, true)) {
            fclose($handle);

            return response()->json(['success' => false, 'message' => 'The CSV must have a header row with at least "question" and "answer" columns.'], 422);
        }

        $categories = FaqCategory::ownedBy($userId)->get()->keyBy(fn ($c) => Str::lower($c->name));
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $header, $userId, &$categories, &$imported, &$skipped) {
            while (($row = fgetcsv($handle)) !== false && $imported < 500) {
                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

                $question = trim((string) ($data['question'] ?? ''));
                $answer = trim((string) ($data['answer'] ?? ''));

                if ($question === '' || $answer === '' || mb_strlen($question) > 500) {
                    $skipped++;
                    continue;
                }

                $categoryId = null;
                $categoryName = trim((string) ($data['category'] ?? ''));

                if ($categoryName !== '') {
                    $key = Str::lower($categoryName);

                    if (!$categories->has($key)) {
                        $categories->put($key, FaqCategory::create([
                            'user_id' => $userId,
                            'name'    => Str::limit($categoryName, 100, ''),
                            'slug'    => Str::slug($categoryName) . '-' . Str::random(4),
                        ]));
                    }

                    $categoryId = $categories->get($key)->id;
                }

                $language = Str::lower(trim((string) ($data['language'] ?? '')));
                $status = Str::lower(trim((string) ($data['status'] ?? '')));
                $tags = trim((string) ($data['tags'] ?? ''));

                Faq::create([
                    'user_id'         => $userId,
                    'faq_category_id' => $categoryId,
                    'question'        => $question,
                    'answer'          => \Mews\Purifier\Facades\Purifier::clean(Str::limit($answer, 2000, ''), 'default'),
                    'language'        => in_array($language, ['en', 'ar'], true) ? $language : 'en',
                    'status'          => in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft',
                    'tags'            => $tags !== '' ? array_values(array_filter(array_map('trim', explode(',', $tags)))) : [],
                    'source'          => 'import',
                ]);

                $imported++;
            }
        });

        fclose($handle);

        $message = "{$imported} " . Str::plural('entry', $imported) . ' imported'
            . ($skipped ? ", {$skipped} skipped (missing question/answer)" : '')
            . '. Published entries become available to the AI Copilot within the hour.';

        return response()->json(['success' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => $message]);
    }

    /**
     * "Improve with AI" on the Add/Edit FAQ modal: rewrites the draft
     * answer for clarity and a friendly, professional tone, and suggests
     * a few tags. Nothing is saved - the seller reviews the suggestion in
     * the form and saves it themselves (the BRD's "AI proposes, it never
     * auto-publishes" principle).
     */
    public function improve(Request $request)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer'   => ['nullable', 'string', 'max:2000'],
            'language' => ['nullable', 'in:en,ar'],
        ]);

        $apiKey = adminSetting('gemini_api_key_free');

        if (empty($apiKey)) {
            return response()->json(['success' => false, 'message' => 'AI is not configured yet - add a Gemini API key in Admin > APIs.'], 422);
        }

        $language = ($validated['language'] ?? 'en') === 'ar' ? 'Arabic' : 'English';
        $answer = trim(strip_tags($validated['answer'] ?? ''));

        $prompt = "You help an online business write answers to customer FAQs that an AI assistant will reuse when replying to customers.\n"
            . "Question: \"{$validated['question']}\"\n"
            . ($answer !== ''
                ? "Draft answer: \"{$answer}\"\nRewrite the draft answer so it is clear, accurate, friendly and professional. Keep every fact from the draft and do not invent new facts, prices, dates or policies."
                : 'Write a short template answer, using [square-bracket placeholders] for any business-specific facts you do not know (prices, days, cities, links).')
            . "\nWrite the answer in {$language}, as plain text (no markdown), under 600 characters."
            . "\nAlso suggest 3 to 5 short lowercase tags (one or two words each) describing the topic.";

        $response = Http::timeout(20)
            ->retry(3, fn ($attempt, $exception) => RetryPolicy::delayMs($attempt, $exception), fn ($exception) => RetryPolicy::isRetryable($exception), throw: false)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $apiKey, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'response_schema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'answer' => ['type' => 'STRING'],
                            'tags'   => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                        ],
                        'required' => ['answer', 'tags'],
                    ],
                ],
            ]);

        $data = $response->successful() ? json_decode((string) $response->json('candidates.0.content.parts.0.text'), true) : null;

        if (!is_array($data) || empty($data['answer'])) {
            Log::warning('Gemini Knowledge Base answer improvement failed.', ['status' => $response->status(), 'body' => Str::limit($response->body(), 1000)]);

            return response()->json(['success' => false, 'message' => $response->json('error.message') ?? 'AI could not improve this answer - please try again.'], 422);
        }

        return response()->json([
            'success' => true,
            'answer'  => Str::limit(trim($data['answer']), 2000, ''),
            'tags'    => collect($data['tags'] ?? [])->map(fn ($t) => Str::lower(trim((string) $t)))->filter()->unique()->take(5)->values(),
        ]);
    }

    private const CSV_COLUMNS = ['question', 'answer', 'category', 'language', 'status', 'tags'];

    /**
     * The listing's filters (search, category, status, language) - shared
     * by index() and export() so an export matches what's on screen.
     */
    private function filteredQuery(Request $request, int $userId)
    {
        return Faq::ownedBy($userId)
            ->with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('faq_category_id', $request->integer('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->string('language')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(fn ($w) => $w->where('question', 'like', $term)
                    ->orWhere('answer', 'like', $term)
                    ->orWhere('tags', 'like', $term));
            })
            ->latest('updated_at');
    }

    private function stats(int $userId): array
    {
        $counts = Faq::ownedBy($userId)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total = (int) $counts->sum();

        return [
            'total'          => $total,
            'published'      => (int) $counts->get('published', 0),
            'drafts'         => (int) $counts->get('draft', 0),
            'archived'       => (int) $counts->get('archived', 0),
            'this_month'     => Faq::ownedBy($userId)->where('created_at', '>=', now()->startOfMonth())->count(),
            'knowledge_gaps' => KnowledgeGapReport::ownedBy($userId)->whereIn('status', ['new', 'under_review'])->count(),
        ];
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
            'answer'          => ['required', 'string', 'max:2000'],
            'language'        => ['required', 'in:en,ar'],
            'status'          => ['required', 'in:draft,published,archived'],
            'copilot_enabled' => ['sometimes', 'boolean'],
            'tags'            => ['nullable', 'string', 'max:500'],
        ]);

        $validated['copilot_enabled'] = $request->boolean('copilot_enabled', true);

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

        // Same save-time sanitization as FaqController::validated() - see
        // that method's comment for why (zero server-side HTML/script
        // handling otherwise, reusing the project's existing general-
        // purpose Purifier profile rather than a new one).
        $validated['answer'] = \Mews\Purifier\Facades\Purifier::clean($validated['answer'], 'default');

        return $validated;
    }
}
