<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailTemplate;
use App\Models\EmailMarketing\EmailTemplateVersion;
use App\Models\EmailMarketing\SenderIdentity;
use App\Models\SocialAccount;
use App\Rules\ValidEmailTemplateSchema;
use App\Services\EmailMarketingServices\SendGridClient;
use App\Support\Email\EmailHtmlSanitizer;
use App\Support\Gemini\RetryPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailTemplateController extends Controller
{
    public function __construct(protected SendGridClient $sendGridClient)
    {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();

        $categories = EmailTemplate::where('user_id', $userId)->whereNotNull('category')->distinct()->pluck('category');

        $sort = $request->query('sort', 'newest');
        $sortColumn = $sort === 'name' ? 'name' : 'updated_at';
        $sortDirection = $sort === 'oldest' ? 'asc' : ($sort === 'name' ? 'asc' : 'desc');

        $templates = EmailTemplate::where('user_id', $userId)
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query('search') . '%')
                  ->orWhere('subject', 'like', '%' . $request->query('search') . '%');
            }))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(24)
            ->withQueryString();

        // Real counts only - no fabricated usage/rating/success-rate
        // numbers, since nothing in this app tracks any of that.
        $totalTemplates = EmailTemplate::where('user_id', $userId)->count();
        $draftCount = EmailTemplate::where('user_id', $userId)->where('status', 'draft')->count();
        $publishedCount = EmailTemplate::where('user_id', $userId)->where('status', 'published')->count();

        return view('admin.email.templates.index', compact(
            'templates', 'categories', 'totalTemplates', 'draftCount', 'publishedCount'
        ));
    }

    public function create()
    {
        // Real categories this seller has already used, not a fabricated
        // fixed taxonomy - offered as a <datalist> so the field still
        // accepts free text on a brand new account with no templates yet.
        $categories = EmailTemplate::where('user_id', Auth::id())
            ->whereNotNull('category')->distinct()->pluck('category');

        return view('admin.email.templates.create', compact('categories'))
            ->with('socialAccountsByPlatform', $this->connectedSocialAccounts());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        // current_version is explicit here (matching the migration's own
        // default of 1) rather than left to the DB default - Eloquent's
        // create() doesn't read a column's DB-level default back into the
        // in-memory model afterward, so snapshotVersion() below would
        // otherwise see a null current_version and fail its NOT NULL
        // version column.
        $template = EmailTemplate::create(['user_id' => Auth::id(), 'current_version' => 1, ...$validated]);

        // The first save is version 1's content too - snapshotted so
        // "Version History" always has at least one entry, even for a
        // template that's never been edited since creation. Reuses
        // snapshotVersion() (rather than duplicating its shape here)
        // so schema_json/editor_type stay correct without maintaining
        // two copies of this logic.
        $template->snapshotVersion(Auth::id());

        return redirect()->route('admin.email.templates.index')->with('success', 'Template created.');
    }

    public function edit(EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $versions = $template->versions;
        $categories = EmailTemplate::where('user_id', Auth::id())
            ->whereNotNull('category')->distinct()->pluck('category');

        return view('admin.email.templates.edit', compact('template', 'versions', 'categories'))
            ->with('socialAccountsByPlatform', $this->connectedSocialAccounts());
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
        $template->update(['body' => $version->html_content, 'schema_json' => $version->schema_json]);

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
    /**
     * Real image upload for the Design tab's toolbar/Blocks panel (Image,
     * Header logo, Video thumbnail) - same Storage::disk('r2') (Cloudflare
     * R2, S3-compatible) pattern PostController already uses for social
     * post media, so this is a genuine working upload, not a URL prompt().
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = 'uploads/email-templates/' . Auth::id() . '/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

        return response()->json([
            'success' => true,
            'url'     => Storage::disk('r2')->url($path),
        ]);
    }

    /**
     * Real video FILE upload (as an alternative to pasting an existing
     * YouTube/Vimeo link) - the Video block's thumbnail then links here
     * instead of to a third-party site. The video still can't be embedded
     * INSIDE the email itself (every mainstream mail client strips
     * <video> entirely, no matter where the file is hosted), so clicking
     * the thumbnail always opens this real hosted file in a new tab,
     * where the browser plays it natively - same real constraint as
     * before, just no longer requiring the seller to already have an
     * external link.
     */
    public function uploadVideo(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:mp4,mov,webm,avi,m4v', 'max:51200'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = 'uploads/email-templates/' . Auth::id() . '/videos/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'public']);

        return response()->json([
            'success' => true,
            'url'     => Storage::disk('r2')->url($path),
        ]);
    }

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

    /**
     * The seller's own connected, currently-postable pages/profiles
     * (same active()+withPostingPermission() filter PostController's
     * composer uses), grouped by platform with a real public URL
     * computed per account - powers the Social Icons block's account
     * picker so a seller can choose a real connected page instead of
     * typing its link by hand. Accounts with no derivable public URL
     * (eg. a Facebook row that's actually an ad account) are left out
     * entirely rather than offered with a dead link.
     */
    private function connectedSocialAccounts(): array
    {
        return SocialAccount::where('user_id', Auth::id())
            ->active()
            ->withPostingPermission()
            ->get()
            ->map(fn (SocialAccount $account) => [
                'id'         => $account->id,
                'platform'   => $account->platform,
                'name'       => $account->name,
                'avatar_url' => $account->avatar_url,
                'url'        => $account->publicProfileUrl(),
            ])
            ->filter(fn ($account) => $account['url'] !== null)
            ->groupBy('platform')
            ->map(fn ($group) => $group->values())
            ->toArray();
    }

    /**
     * autosave() intentionally does NOT go through this method - it only
     * ever touches schema_json/body (never name/subject/category/status,
     * which live outside the Vue root and are only ever submitted by the
     * real Save button), but it reuses validateAndSanitizeSchemaAndBody()
     * below so the security-critical sanitize step can't drift between
     * store()/update()/autosave()/sendTestEmail().
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'status'   => ['required', Rule::in(['draft', 'published'])],
        ]);

        return [...$validated, ...$this->validateAndSanitizeSchemaAndBody($request)];
    }

    /**
     * Shared by store()/update()/autosave()/sendTestEmail() - the single
     * place schema_json gets shape-checked (ValidEmailTemplateSchema) and
     * body gets run through EmailHtmlSanitizer before either is trusted.
     * Both HTML is generated client-side and the hidden form field
     * submitting it is an ordinary request field - nothing stops a
     * tampered/hand-crafted request from bypassing the generator, so this
     * runs unconditionally regardless of how "body" was really produced.
     */
    private function validateAndSanitizeSchemaAndBody(Request $request): array
    {
        $data = $request->validate([
            'body'        => ['required', 'string', 'max:512000'],
            'schema_json' => ['nullable', 'string', 'max:307200'],
        ]);

        $schema = null;

        if (filled($data['schema_json'] ?? null)) {
            $decoded = json_decode($data['schema_json'], true);

            Validator::make(
                ['schema_json' => $decoded],
                ['schema_json' => [new ValidEmailTemplateSchema]]
            )->validate();

            $schema = $decoded;
        }

        return [
            'body'        => EmailHtmlSanitizer::sanitize($data['body']),
            'schema_json' => $schema,
        ];
    }

    /**
     * Lightweight save-in-place for the block editor's autosave timer -
     * deliberately NOT update() (which calls snapshotVersion()): a version
     * row per autosave tick every couple of seconds would explode
     * email_template_versions and defeat version history as meaningful
     * checkpoints, not a running log. Only schema_json/body move here -
     * name/subject/category/status are only ever submitted by the real
     * Save/Save as Draft button.
     */
    public function autosave(Request $request, EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->update($this->validateAndSanitizeSchemaAndBody($request));

        return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Sends a real transactional email via the seller's own SendGrid
     * subaccount (POST /v3/mail/send - not the Marketing Single Send API
     * SendGridCampaignService uses, since a template being edited has no
     * SendGrid Single Send object of its own yet). Uses the CURRENT
     * in-editor subject/body from the request, not what's saved in the
     * DB, so a seller can test before ever clicking Save and the confirm
     * step's preview is guaranteed to match what's actually sent.
     */
    public function sendTestEmail(Request $request, EmailTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'subject'         => ['required', 'string', 'max:255'],
        ]);
        $validated = [...$validated, ...$this->validateAndSanitizeSchemaAndBody($request)];

        [$subaccount, $sender, $error] = $this->resolveTestSendIdentity();

        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        // No real recipient Contact record exists to resolve these
        // against (SendGrid only substitutes personalization tags at real
        // send time against synced Contacts) - readable sample values are
        // substituted here so the test email is actually legible, never
        // persisted anywhere.
        $sampleValues = [
            '{{first_name}}' => 'John',
            '{{last_name}}'  => 'Doe',
            '{{email}}'      => $validated['recipient_email'],
        ];
        $htmlContent = strtr($validated['body'], $sampleValues);
        $subject = strtr($validated['subject'], $sampleValues);

        $result = $this->sendGridClient->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)->post('mail/send', [
            'personalizations' => [['to' => [['email' => $validated['recipient_email']]]]],
            'subject'          => $subject,
            'from'             => ['email' => $sender->from_email, 'name' => $sender->from_name],
            'content'          => [['type' => 'text/html', 'value' => $htmlContent]],
        ]);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * [$subaccount, $sender, $error] - mirrors
     * EmailCampaignController::fetchSuppressionGroups()'s return shape so
     * the caller can tell "not set up yet" apart from a genuine SendGrid
     * error, same as SendGridCampaignService::preflight()'s identical two
     * checks for actually sending a campaign.
     */
    private function resolveTestSendIdentity(): array
    {
        $subaccount = EmailSubaccount::where('user_id', Auth::id())->first();

        if (!$subaccount?->isActive()) {
            return [null, null, 'Complete Email Marketing setup (SendGrid subaccount) before sending a test email.'];
        }

        $sender = SenderIdentity::where('user_id', Auth::id())->where('status', 'verified')->first();

        if (!$sender) {
            return [null, null, 'Verify a sender identity before sending a test email.'];
        }

        return [$subaccount, $sender, null];
    }
}
