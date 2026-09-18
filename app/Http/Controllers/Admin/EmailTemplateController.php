<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\EmailTemplateVersion;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::where('user_id', Auth::id())->latest()->paginate(50);

        return view('admin.email.templates.index', compact('templates'));
    }

    public function create()
    {
        // Real categories this seller has already used, not a fabricated
        // fixed taxonomy - offered as a <datalist> so the field still
        // accepts free text on a brand new account with no templates yet.
        $categories = EmailTemplate::where('user_id', Auth::id())
            ->whereNotNull('category')->distinct()->pluck('category');

        return view('admin.email.templates.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $template = EmailTemplate::create(['user_id' => Auth::id(), ...$validated]);

        // The first save is version 1's content too - snapshotted so
        // "Version History" always has at least one entry, even for a
        // template that's never been edited since creation.
        $template->versions()->create([
            'version'      => 1,
            'html_content' => $template->body,
            'editor_type'  => 'code',
            'created_by'   => Auth::id(),
        ]);
        $template->update(['current_version' => 2]);

        return redirect()->route('admin.email.templates.index')->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $versions = $template->versions;
        $categories = EmailTemplate::where('user_id', Auth::id())
            ->whereNotNull('category')->distinct()->pluck('category');

        return view('admin.email.templates.edit', compact('template', 'versions', 'categories'));
    }

    public function update(Request $request, EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $validated = $this->validated($request);

        // Snapshot the body as it stands right now, before overwriting it
        // - every save creates real, browsable history instead of
        // silently clobbering the previous version.
        $template->snapshotVersion(Auth::id());
        $template->update($validated);

        return redirect()->route('admin.email.templates.index')->with('success', 'Template updated.');
    }

    /**
     * Restores an older version's HTML as the template's current body -
     * itself snapshotted first, so rolling back is also just a normal,
     * reversible version (never destructive - the version being rolled
     * back FROM is never lost).
     */
    public function restoreVersion(EmailTemplate $template, EmailTemplateVersion $version)
    {
        abort_unless($template->user_id === Auth::id(), 403);
        abort_unless($version->email_template_id === $template->id, 403);

        $template->snapshotVersion(Auth::id());
        $template->update(['body' => $version->html_content]);

        return redirect()->route('admin.email.templates.edit', $template)->with('success', "Restored version {$version->version}.");
    }

    public function destroy(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    /**
     * Reuses the exact Gemini integration pattern already proven in
     * PostController::generateAiContent() (social post captions) - same
     * admin-configured key, same retry policy, same execution-time-budget
     * handling - just a different prompt and response_schema shaped for
     * an email template (subject + HTML body) instead of a social caption.
     * The UI calls this "AI", not "ChatGPT", since the real backend is
     * Google Gemini.
     */
    public function generateAiContent(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $apiKey = adminSetting('gemini_api_key_free');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'AI content generation is not configured yet - add a Gemini API key in Admin > APIs.',
            ], 422);
        }

        set_time_limit(120);

        $response = Http::timeout(15)
            ->retry(3, fn ($attempt, $exception) => RetryPolicy::delayMs($attempt, $exception), fn ($exception) => RetryPolicy::isRetryable($exception), throw: false)
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $apiKey,
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => 'Write a marketing email template based on this goal: "' . $validated['prompt'] . '". '
                                    . 'Write a short, compelling subject line, and an HTML email body using simple inline-styled '
                                    . 'tags only (p, h1-h3, a, div, strong, em, ul/li) - no <html>/<head>/<body> wrapper tags. '
                                    . 'Address the reader with the personalization tag {{first_name}} at least once. '
                                    . 'Include one clear call-to-action link styled as a button using inline CSS '
                                    . '(e.g. <a href="#" style="display:inline-block;background:#7c5cff;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">Button Text</a>).'],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'response_schema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'subject' => ['type' => 'STRING', 'description' => 'A short, engaging email subject line, under 100 characters.'],
                                'body'    => ['type' => 'STRING', 'description' => 'The email body as inline-styled HTML, no html/head/body wrapper tags.'],
                            ],
                            'required' => ['subject', 'body'],
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {
            Log::warning('Gemini AI email template generation failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $response->json('error.message') ?? 'Failed to generate content - please try again.',
            ], 422);
        }

        $rawText = $response->json('candidates.0.content.parts.0.text');
        $data = $rawText ? json_decode($rawText, true) : null;

        if (!is_array($data) || !isset($data['subject'], $data['body'])) {
            Log::warning('Gemini AI email template generation returned an unexpected shape.', ['raw' => $rawText]);

            return response()->json([
                'success' => false,
                'message' => 'AI returned an unexpected response - please try again.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => ['subject' => $data['subject'], 'body' => $data['body']],
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'status'   => ['required', Rule::in(['draft', 'published'])],
        ]);
    }
}
