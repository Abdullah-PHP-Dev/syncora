<?php

namespace App\Services\PlatformEmails;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

/**
 * Turns editor input into safe, normalised template content. Only known fields and block
 * types survive; every {{ variable }} must be one this email really receives; links may only
 * be http(s), mailto or a variable; custom HTML goes through HTMLPurifier's "email" profile.
 * Templates are data: they are never compiled as Blade or executed.
 */
class TemplateValidator
{
    public const BLOCKS = ['text', 'heading', 'button', 'card', 'details', 'image', 'divider', 'html', 'code'];

    public const URL_PATTERN = '/^(https?:\/\/[^\s<>"]+|mailto:[^\s<>"]+|\{\{\s*[a-z][a-z0-9_]*\s*\}\}[^\s<>"]*)$/i';

    public function __construct(private TemplateRegistry $registry) {}

    public function validate(string $key, array $input): array
    {
        $url = ['nullable', 'string', 'max:500', 'regex:'.self::URL_PATTERN];
        Validator::make($input, [
            'subject' => ['required', 'string', 'max:200'],
            'preheader' => ['nullable', 'string', 'max:200'],
            'heading' => ['required', 'string', 'max:200'],
            'cta_text' => ['nullable', 'string', 'max:80'],
            'cta_url' => $url,
            'secondary_text' => ['nullable', 'string', 'max:80'],
            'secondary_url' => $url,
            'footer' => ['nullable', 'string', 'max:1000'],
            'blocks' => ['nullable', 'array', 'max:40'],
            'blocks.*.type' => ['required', 'in:'.implode(',', self::BLOCKS)],
            'blocks.*.text' => ['nullable', 'string', 'max:5000'],
            'blocks.*.label' => ['nullable', 'string', 'max:80'],
            'blocks.*.url' => $url,
            'blocks.*.alt' => ['nullable', 'string', 'max:200'],
            'blocks.*.width' => ['nullable', 'integer', 'between:40,600'],
            'blocks.*.rows' => ['nullable', 'array', 'max:20'],
            'blocks.*.rows.*.label' => ['nullable', 'string', 'max:120'],
            'blocks.*.rows.*.value' => ['nullable', 'string', 'max:500'],
            'blocks.*.html' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        $plain = fn ($v) => trim(strip_tags((string) $v));
        // Nested block input is read from the validated array and rebuilt field by field (never passed through as-is).
        $blocks = array_values(array_map(fn (array $b) => match ($b['type']) {
            'text', 'heading' => ['type' => $b['type'], 'text' => trim((string) ($b['text'] ?? ''))],
            'code' => ['type' => 'code', 'text' => $plain($b['text'] ?? '')],
            'button' => ['type' => 'button', 'label' => $plain($b['label'] ?? ''), 'url' => trim((string) ($b['url'] ?? ''))],
            'card' => ['type' => 'card', 'rows' => array_values(array_map(fn ($r) => ['label' => $plain($r['label'] ?? ''), 'value' => $plain($r['value'] ?? '')], (array) ($b['rows'] ?? [])))],
            'image' => ['type' => 'image', 'url' => trim((string) ($b['url'] ?? '')), 'alt' => $plain($b['alt'] ?? ''), 'width' => (int) ($b['width'] ?? 600) ?: 600],
            'html' => ['type' => 'html', 'html' => (string) Purifier::clean((string) ($b['html'] ?? ''), 'email')],
            default => ['type' => $b['type']],
        }, (array) ($input['blocks'] ?? [])));

        $content = [
            'subject' => $plain($input['subject']), 'preheader' => $plain($input['preheader'] ?? ''), 'heading' => $plain($input['heading']),
            'blocks' => $blocks,
            'cta_text' => $plain($input['cta_text'] ?? ''), 'cta_url' => trim((string) ($input['cta_url'] ?? '')),
            'secondary_text' => $plain($input['secondary_text'] ?? ''), 'secondary_url' => trim((string) ($input['secondary_url'] ?? '')),
            'footer' => $plain($input['footer'] ?? ''),
        ];

        $allowed = $this->registry->variables($key);
        preg_match_all('/\{\{\s*([^}]*?)\s*\}\}/', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $m);
        $unknown = array_values(array_diff(array_unique($m[1]), $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['variables' => __('platform_emails.unknown_variables', ['names' => implode(', ', $unknown)])]);
        }

        if ($missing = self::missingRequired($key, $content)) {
            throw ValidationException::withMessages(['variables' => __('platform_emails.required_variables', ['names' => implode(', ', $missing)])]);
        }

        return $content;
    }

    /** Required variables (TemplateRegistry::REQUIRED) this content doesn't use. */
    public static function missingRequired(string $key, array $content): array
    {
        $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return array_values(array_filter(TemplateRegistry::REQUIRED[$key] ?? [], fn ($name) => ! preg_match('/\{\{\s*'.preg_quote($name, '/').'\s*\}\}/', $json)));
    }
}
