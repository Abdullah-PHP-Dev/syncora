<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BrandedPlatformMail;
use App\Models\PlatformEmails\PlatformEmailTemplate;
use App\Models\PlatformEmails\PlatformEmailTemplateSetting;
use App\Models\PlatformEmails\PlatformEmailTemplateVersion;
use App\Models\Notifications\NotificationDelivery;
use App\Services\PlatformEmails\EmailBranding;
use App\Services\PlatformEmails\EmailTemplates;
use App\Services\PlatformEmails\EmailTemplateSwitch;
use App\Services\PlatformEmails\TemplateMailBuilder;
use App\Services\PlatformEmails\TemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Communication management for administrators (role:admin only - routes/web.php):
 * every platform email per event and language, its on/off switch, global branding and the
 * email log. Previews and test sends use sample data only and never touch business records.
 */
class PlatformEmailTemplateController extends Controller
{
    public function __construct(private TemplateRegistry $registry, private EmailTemplates $templates, private TemplateMailBuilder $builder) {}

    private function guard(string $key, string $locale): void
    {
        abort_unless($this->registry->exists($key) && in_array($locale, TemplateRegistry::LOCALES, true), 404);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'module' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in(['active', 'disabled', 'custom', 'default', 'draft'])], 'locale' => ['nullable', Rule::in(TemplateRegistry::LOCALES)],
        ]);
        $rows = PlatformEmailTemplate::with('editor:id,name')->get()->keyBy(fn ($t) => $t->key.'|'.$t->locale);
        $disabled = PlatformEmailTemplateSetting::where('enabled', false)->pluck('key')->all();
        $list = collect();
        foreach ($this->registry->all() as $key => $meta) {
            foreach (TemplateRegistry::LOCALES as $locale) {
                $row = $rows[$key.'|'.$locale] ?? null;
                $content = $row?->content ?? $this->registry->defaults($key, $locale);
                $list->push([
                    'key' => $key, 'locale' => $locale, 'module' => $meta['category'], 'name' => $this->registry->label($key),
                    'subject' => $content['subject'], 'active' => ! in_array($key, $disabled, true), 'locked' => $meta['locked'],
                    'custom' => (bool) $row?->content, 'draft' => (bool) $row?->draft, 'version' => $row?->version,
                    'updated_at' => $row?->updated_at, 'updated_by' => $row?->editor?->name,
                ]);
            }
        }
        $q = mb_strtolower((string) ($filters['q'] ?? ''));
        $list = $list->filter(fn ($r) => (! $q || str_contains(mb_strtolower($r['name'].' '.$r['key'].' '.$r['subject']), $q))
            && (empty($filters['module']) || $r['module'] === $filters['module'])
            && (empty($filters['locale']) || $r['locale'] === $filters['locale'])
            && match ($filters['status'] ?? null) {
                'active' => $r['active'], 'disabled' => ! $r['active'], 'custom' => $r['custom'], 'default' => ! $r['custom'], 'draft' => $r['draft'], default => true,
            })->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($list->forPage($page, 25)->values(), $list->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.platform-emails.index', [
            'templates' => $paginator, 'filters' => $filters,
            'modules' => collect($this->registry->all())->pluck('category')->unique()->values(),
            'stats' => ['total' => count($this->registry->all()), 'custom' => $rows->filter(fn ($t) => $t->content)->count(), 'disabled' => count($disabled)],
        ]);
    }

    public function edit(string $key, string $locale)
    {
        $this->guard($key, $locale);
        $row = $this->templates->find($key, $locale);
        $meta = $this->registry->get($key);
        $route = fn ($name, $extra = []) => route('admin.platform-emails.'.$name, ['key' => $key, 'locale' => $locale] + $extra);

        return view('admin.platform-emails.edit', [
            'key' => $key, 'locale' => $locale, 'meta' => $meta, 'name' => $this->registry->label($key),
            'editor' => [
                'key' => $key, 'locale' => $locale, 'locales' => TemplateRegistry::LOCALES, 'name' => $this->registry->label($key),
                'module' => trans('notifications.categories.'.$meta['category']), 'locked' => $meta['locked'], 'mandatory' => $meta['mandatory'],
                'active' => EmailTemplateSwitch::enabled($key), 'custom' => (bool) $row?->content, 'has_draft' => (bool) $row?->draft, 'version' => $row?->version ?? 0,
                'content' => $this->templates->editable($key, $locale),
                'defaults' => $this->registry->defaults($key, $locale),
                'variables' => $this->registry->describe($key),
                'blocks' => \App\Services\PlatformEmails\TemplateValidator::BLOCKS,
                'urls' => [
                    'draft' => $route('draft'), 'publish' => $route('publish'), 'reset' => $route('reset'), 'preview' => $route('preview'),
                    'test' => $route('test'), 'toggle' => $route('toggle'), 'versions' => $route('versions'), 'duplicate' => $route('duplicate'),
                    'restore' => route('admin.platform-emails.restore', ['key' => $key, 'locale' => $locale, 'version' => '__VERSION__']),
                    'edit' => route('admin.platform-emails.edit', ['key' => $key, 'locale' => '__LOCALE__']),
                    'index' => route('admin.platform-emails.index'),
                ],
            ],
        ]);
    }

    public function saveDraft(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $this->templates->saveDraft($key, $locale, $request->all(), $request->user());

        return response()->json(['message' => __('platform_emails.saved_draft')]);
    }

    public function publish(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $row = $this->templates->publish($key, $locale, $request->all(), $request->user());

        return response()->json(['message' => __('platform_emails.published', ['version' => $row->version]), 'version' => $row->version]);
    }

    public function reset(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $row = $this->templates->reset($key, $locale, $request->user());

        return response()->json(['message' => __('platform_emails.reset_done'), 'content' => $this->registry->defaults($key, $locale), 'version' => $row?->version ?? 0]);
    }

    public function duplicate(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $to = $request->validate(['to' => ['required', Rule::in(array_diff(TemplateRegistry::LOCALES, [$locale]))]])['to'];
        $this->templates->duplicate($key, $locale, $to, $request->user());

        return response()->json(['message' => __('platform_emails.duplicated'), 'redirect' => route('admin.platform-emails.edit', ['key' => $key, 'locale' => $to])]);
    }

    public function toggle(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        EmailTemplateSwitch::set($key, (bool) $enabled, $request->user()->id);
        rescue(fn () => activity('platform_emails')->causedBy($request->user())->withProperties(['key' => $key])->log($enabled ? 'enabled' : 'disabled'), null, false);

        return response()->json(['message' => __($enabled ? 'platform_emails.enabled' : 'platform_emails.disabled'), 'active' => (bool) $enabled]);
    }

    /** Renders the editor's current (unsaved) content with sample data. Nothing is stored or sent. */
    public function preview(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $layout = $this->sampleLayout($request, $key, $locale);
        $brand = EmailBranding::get();

        return response()->json([
            'subject' => $layout['subject'], 'preheader' => $layout['preheader'],
            'from_name' => $brand['sender_name'], 'from_email' => $brand['sender_email'],
            'html' => view('emails.notifications.layout', $layout)->render(),
        ]);
    }

    /** Sends the editor's current content with sample data to one address, marked as a test. */
    public function test(Request $request, string $key, string $locale)
    {
        $this->guard($key, $locale);
        $to = $request->validate(['email' => ['required', 'email:rfc', 'max:191']])['email'];
        try {
            \App\Services\Notifications\Links::withPublicRoot(function () use ($request, $key, $locale, $to) {
                $layout = $this->sampleLayout($request, $key, $locale);
                $layout['test'] = true;
                $layout['subject'] = '['.__('platform_emails.test_prefix').'] '.$layout['subject'];
                $mailer = config('notifications.mailer') ? Mail::mailer(config('notifications.mailer')) : Mail::mailer();
                $mailer->to($to)->send(new BrandedPlatformMail($layout, $locale));
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // invalid content: field errors, as before
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => __('platform_emails.test_failed')], 422);
        }
        rescue(fn () => activity('platform_emails')->causedBy($request->user())->withProperties(['key' => $key, 'locale' => $locale, 'to' => $to])->log('test_sent'), null, false);

        return response()->json(['message' => __('platform_emails.test_sent', ['email' => $to])]);
    }

    public function versions(string $key, string $locale)
    {
        $this->guard($key, $locale);
        $row = $this->templates->find($key, $locale);

        return response()->json(['versions' => $row ? $row->versions()->with('author:id,name')->limit(50)->get()->map(fn (PlatformEmailTemplateVersion $v) => [
            'id' => $v->id, 'version' => $v->version, 'action' => $v->action, 'author' => $v->author?->name, 'at' => $v->created_at?->toIso8601String(),
            'subject' => $v->content['subject'] ?? null,
        ]) : []]);
    }

    public function restore(Request $request, string $key, string $locale, int $version)
    {
        $this->guard($key, $locale);
        $row = $this->templates->find($key, $locale) ?? abort(404);
        $restored = $this->templates->restore($row, PlatformEmailTemplateVersion::findOrFail($version), $request->user());

        return response()->json(['message' => __('platform_emails.restored'), 'content' => $this->templates->editable($key, $locale),
            'version' => $restored->version, 'custom' => (bool) $restored->content]);
    }

    /**
     * "Create template": every platform email belongs to an event the app really sends, so creating
     * one means choosing module → event → language and customising it (a template with no event
     * behind it would never be sent). Already-customised choices are marked.
     */
    public function create()
    {
        $custom = PlatformEmailTemplate::whereNotNull('content')->get(['key', 'locale'])->map(fn ($t) => $t->key.'|'.$t->locale)->all();
        $events = collect($this->registry->all())->map(fn ($meta, $key) => [
            'key' => $key, 'module' => $meta['category'], 'name' => $this->registry->label($key),
            'custom' => array_values(array_filter(TemplateRegistry::LOCALES, fn ($l) => in_array($key.'|'.$l, $custom, true))),
        ])->values();

        return view('admin.platform-emails.create', [
            'events' => $events, 'modules' => $events->pluck('module')->unique()->values(),
        ]);
    }

    public function startCreate(Request $request)
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:80'], 'locale' => ['required', Rule::in(TemplateRegistry::LOCALES)]]);
        $this->guard($data['key'], $data['locale']);

        return redirect()->route('admin.platform-emails.edit', $data);
    }

    public function branding()
    {
        return view('admin.platform-emails.branding', ['brand' => EmailBranding::get(), 'social' => EmailBranding::SOCIAL]);
    }

    public function saveBranding(Request $request)
    {
        $url = ['nullable', 'url:http,https', 'max:500'];
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:80'], 'logo_url' => $url, 'website_url' => $url,
            'sender_name' => ['required', 'string', 'max:80'], 'sender_email' => ['required', 'email', 'max:191'],
            'reply_to' => ['required', 'email', 'max:191'], 'support_email' => ['required', 'email', 'max:191'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer_text' => ['nullable', 'string', 'max:300'], 'copyright' => ['nullable', 'string', 'max:160'],
        ]);
        // Free-form nested input: read from the request and whitelisted to the known networks.
        $data['social'] = collect((array) $request->input('social', []))->only(EmailBranding::SOCIAL)
            ->map(fn ($v) => is_string($v) && preg_match('#^https?://\S+$#', trim($v)) ? trim($v) : '')->all();
        $data = array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $data);
        EmailBranding::save($data);
        rescue(fn () => activity('platform_emails')->causedBy($request->user())->log('branding_updated'), null, false);

        return redirect()->route('admin.platform-emails.branding')->with('success', __('platform_emails.branding_saved'));
    }

    /** The email log: what the platform actually tried to send, and what happened. */
    public function logs(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'sending', 'sent', 'grouped', 'failed', 'suppressed', 'skipped', 'digested'])],
            'event' => ['nullable', 'string', 'max:80'], 'q' => ['nullable', 'string', 'max:191'],
        ]);
        $deliveries = NotificationDelivery::with('notificationEvent:id,event,occurred_at,subject_type,subject_id')
            ->where('channel', 'email')
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['event'] ?? null, fn ($q, $e) => $q->whereHas('notificationEvent', fn ($q) => $q->where('event', $e)))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('to_address', 'like', '%'.$s.'%')->orWhere('subject', 'like', '%'.$s.'%')))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.platform-emails.logs', ['deliveries' => $deliveries, 'filters' => $f, 'events' => array_keys($this->registry->all())]);
    }

    private function sampleLayout(Request $request, string $key, string $locale): array
    {
        // Validated and sanitised exactly as a save would be; preview never stores anything.
        $content = $request->has('subject') ? app(\App\Services\PlatformEmails\TemplateValidator::class)->validate($key, $request->all()) : $this->templates->editable($key, $locale);

        return $this->builder->layout($key, $locale, $content, $this->builder->sampleVariables($key, $locale), $this->builder->sampleDetails($key, $locale), [
            'workspace' => TemplateRegistry::SAMPLE['workspace_name'],
        ]);
    }
}
