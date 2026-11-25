<?php

namespace App\Services\PlatformEmails;

use App\Models\PlatformEmails\PlatformEmailTemplate;
use App\Models\PlatformEmails\PlatformEmailTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Storage of administrator templates: draft → publish (a new version), reset to the built-in
 * default, restore an earlier version. Every change is versioned and written to the audit log.
 * Sending only ever reads the published content (cached), never a draft.
 */
class EmailTemplates
{
    public function __construct(private TemplateRegistry $registry, private TemplateValidator $validator) {}

    /** Published administrator content for this email in this language, or null (use the built-in default). */
    public static function published(string $key, string $locale): ?array
    {
        $all = rescue(fn () => Cache::remember('platform_email_templates_published', 300, fn () => PlatformEmailTemplate::whereNotNull('content')
            ->get(['key', 'locale', 'content', 'version'])->mapWithKeys(fn ($t) => [$t->key.'|'.$t->locale => $t->content + ['_version' => $t->version]])->all()), [], false);

        return $all[$key.'|'.$locale] ?? null;
    }

    public function find(string $key, string $locale): ?PlatformEmailTemplate
    {
        return PlatformEmailTemplate::where('key', $key)->where('locale', $locale)->first();
    }

    /** What the editor opens: the draft, else the published version, else the default. */
    public function editable(string $key, string $locale): array
    {
        $row = $this->find($key, $locale);

        return $row?->draft ?? $row?->content ?? $this->registry->defaults($key, $locale);
    }

    public function saveDraft(string $key, string $locale, array $input, User $by): PlatformEmailTemplate
    {
        $content = $this->validator->validate($key, $input);
        $row = PlatformEmailTemplate::firstOrNew(['key' => $key, 'locale' => $locale]);
        $row->created_by ??= $by->id;
        $row->fill(['draft' => $content, 'updated_by' => $by->id])->save();
        $this->audit($row, 'draft_saved', $by);

        return $row;
    }

    public function publish(string $key, string $locale, array $input, User $by): PlatformEmailTemplate
    {
        $content = $this->validator->validate($key, $input);

        return DB::transaction(function () use ($key, $locale, $content, $by) {
            $row = PlatformEmailTemplate::where('key', $key)->where('locale', $locale)->lockForUpdate()->first()
                ?? new PlatformEmailTemplate(['key' => $key, 'locale' => $locale, 'created_by' => $by->id]);
            $row->fill(['content' => $content, 'draft' => null, 'version' => $row->version + 1, 'published_at' => now(), 'updated_by' => $by->id])->save();
            $this->record($row, 'published', $content, $by);

            return $row;
        });
    }

    /** Back to the built-in wording. History is kept; nothing else is touched. */
    public function reset(string $key, string $locale, User $by): ?PlatformEmailTemplate
    {
        $row = $this->find($key, $locale);
        if (! $row) {
            return null;
        }

        return DB::transaction(function () use ($row, $by) {
            $row->fill(['content' => null, 'draft' => null, 'version' => $row->version + 1, 'published_at' => now(), 'updated_by' => $by->id])->save();
            $this->record($row, 'reset', null, $by);

            return $row;
        });
    }

    public function restore(PlatformEmailTemplate $row, PlatformEmailTemplateVersion $version, User $by): PlatformEmailTemplate
    {
        abort_unless((int) $version->platform_email_template_id === (int) $row->id, 404);

        return $version->content ? $this->publish($row->key, $row->locale, $version->content, $by) : $this->reset($row->key, $row->locale, $by);
    }

    /** Copies this language's current content into another language as a draft (e.g. EN → AR to translate). */
    public function duplicate(string $key, string $from, string $to, User $by): PlatformEmailTemplate
    {
        return $this->saveDraft($key, $to, $this->editable($key, $from), $by);
    }

    private function record(PlatformEmailTemplate $row, string $action, ?array $content, User $by): void
    {
        PlatformEmailTemplateVersion::create(['platform_email_template_id' => $row->id, 'version' => $row->version, 'action' => $action, 'content' => $content, 'created_by' => $by->id, 'created_at' => now()]);
        Cache::forget('platform_email_templates_published');
        $this->audit($row, $action, $by);
    }

    private function audit(PlatformEmailTemplate $row, string $action, User $by): void
    {
        rescue(fn () => activity('platform_emails')->causedBy($by)->performedOn($row)
            ->withProperties(['key' => $row->key, 'locale' => $row->locale, 'version' => $row->version])->log($action), null, false);
    }
}
