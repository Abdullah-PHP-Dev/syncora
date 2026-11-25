<?php

namespace App\Services\PlatformEmails;

/**
 * Renders administrator template content with variable values into the parts of the shared
 * email layout. {{ name }} is plain substitution - no Blade, no code: values are escaped for
 * HTML, URLs must still be http(s)/mailto after substitution, unknown or missing variables
 * become empty text. Text blocks support **bold**, *italic*, [label](url) and line breaks.
 */
class TemplateRenderer
{
    private const TOKEN = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i';

    /**
     * @param  array  $vars     variable name => string value
     * @param  array  $details  [[label, value]] rows for the "details" block (event facts)
     */
    public function render(array $content, array $vars, string $locale, array $details = []): array
    {
        $rtl = $locale === 'ar';
        $brand = EmailBranding::get();
        $html = '';
        foreach ($content['blocks'] ?? [] as $block) {
            $html .= $this->block($block, $vars, $rtl, $brand, $details);
        }
        $secondaryUrl = $this->url($content['secondary_url'] ?? '', $vars);

        return [
            'subject' => $this->plain($content['subject'] ?? '', $vars),
            'preheader' => $this->plain($content['preheader'] ?? '', $vars),
            'headline' => $this->plain($content['heading'] ?? '', $vars),
            'cta' => $this->plain($content['cta_text'] ?? '', $vars) ?: null,
            'cta_url' => $this->url($content['cta_url'] ?? '', $vars),
            'secondary' => ($content['secondary_text'] ?? '') !== '' && $secondaryUrl ? ['label' => $this->plain($content['secondary_text'], $vars), 'url' => $secondaryUrl] : null,
            'footer' => $this->plain($content['footer'] ?? '', $vars) ?: null,
            'blocks_html' => $html,
            'body' => null, 'note' => null,
        ];
    }

    /** Text for subjects, headings and labels (the layout escapes it). */
    public function plain(string $template, array $vars): string
    {
        return trim(strip_tags(preg_replace_callback(self::TOKEN, fn ($m) => (string) ($vars[$m[1]] ?? ''), $template)));
    }

    /** A link after substitution, or null when it isn't http(s)/mailto (javascript:, data: etc. never pass). */
    public function url(string $template, array $vars): ?string
    {
        $url = trim(preg_replace_callback(self::TOKEN, fn ($m) => (string) ($vars[$m[1]] ?? ''), $template));

        return $url !== '' && preg_match('#^(https?://|mailto:)#i', $url) && ! preg_match('/[\s<>"]/', $url) ? $url : null;
    }

    /** Escaped HTML for a text block, with the small formatting set. Values can never inject markup. */
    public function text(string $template, array $vars): string
    {
        $values = [];
        $marked = preg_replace_callback(self::TOKEN, function ($m) use (&$values, $vars) {
            $values[] = (string) ($vars[$m[1]] ?? '');

            return "\u{E000}".(count($values) - 1)."\u{E001}";
        }, $template);
        $html = e($marked);
        $restore = fn (string $s, bool $raw = false) => preg_replace_callback("/\u{E000}(\d+)\u{E001}/u", fn ($m) => $raw ? $values[(int) $m[1]] : e($values[(int) $m[1]]), $s);
        $html = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use ($restore) {
            $url = $this->url($restore(html_entity_decode($m[2]), true), []);

            return $url ? '<a href="'.e($url).'" style="color:inherit;text-decoration:underline;">'.$m[1].'</a>' : $m[1];
        }, $html);
        $html = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/su', '<em>$1</em>', $html);

        return nl2br($restore($html), false);
    }

    private function block(array $block, array $vars, bool $rtl, array $brand, array $details): string
    {
        $align = $rtl ? 'right' : 'left';
        $row = fn (string $inner, string $pad = '0 28px 14px') => '<tr><td align="'.$align.'" style="padding:'.$pad.';text-align:'.$align.';">'.$inner.'</td></tr>';

        return match ($block['type'] ?? '') {
            'text' => $row('<p style="margin:0;font-size:15px;line-height:23px;color:#3A4152;">'.$this->text((string) ($block['text'] ?? ''), $vars).'</p>'),
            'heading' => $row('<h2 style="margin:6px 0 0;font-size:17px;line-height:24px;font-weight:700;color:#161B2B;">'.e($this->plain((string) ($block['text'] ?? ''), $vars)).'</h2>'),
            'button' => ($url = $this->url((string) ($block['url'] ?? ''), $vars))
                ? $row('<a href="'.e($url).'" style="display:inline-block;background:'.e($brand['primary_color']).';color:#FFFFFF;font-size:14px;font-weight:700;line-height:40px;padding:0 22px;border-radius:9px;text-decoration:none;">'.e($this->plain((string) ($block['label'] ?? ''), $vars)).'</a>') : '',
            'card' => $this->card(array_map(fn ($r) => [$this->plain((string) ($r['label'] ?? ''), $vars), $this->plain((string) ($r['value'] ?? ''), $vars)], (array) ($block['rows'] ?? [])), $rtl, $row),
            'details' => $this->card($details, $rtl, $row),
            'image' => ($url = $this->url((string) ($block['url'] ?? ''), $vars))
                ? $row('<img src="'.e($url).'" alt="'.e($this->plain((string) ($block['alt'] ?? ''), $vars)).'" width="'.min(544, (int) ($block['width'] ?? 544)).'" style="display:block;max-width:100%;height:auto;border:0;border-radius:8px;">') : '',
            // The verification code: large, spaced, easy to read and copy (one tap selects it).
            'code' => ($code = $this->plain((string) ($block['text'] ?? ''), $vars)) !== ''
                ? $row('<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:4px auto;"><tr><td dir="ltr" align="center" style="background:#F4F1FF;border:1px dashed '.e($brand['primary_color']).';border-radius:12px;padding:14px 16px 14px 26px;font-family:\'SFMono-Regular\',Menlo,Consolas,monospace;font-size:32px;line-height:38px;font-weight:700;letter-spacing:10px;color:#161B2B;">'.e($code).'</td></tr></table>', '6px 28px 18px') : '',
            'divider' => $row('<div style="border-top:1px solid #EEF0F5;font-size:0;line-height:0;">&nbsp;</div>', '6px 28px 18px'),
            // Purified when saved; values are escaped here, so a variable can't add markup either.
            'html' => $row(preg_replace_callback(self::TOKEN, fn ($m) => e((string) ($vars[$m[1]] ?? '')), (string) ($block['html'] ?? ''))),
            default => '',
        };
    }

    private function card(array $rows, bool $rtl, \Closure $row): string
    {
        $rows = array_values(array_filter($rows, fn ($r) => ($r[1] ?? '') !== ''));
        if ($rows === []) {
            return '';
        }
        $align = $rtl ? 'right' : 'left';
        $cells = implode('', array_map(fn ($r) => '<tr><td align="'.$align.'" style="padding:9px 14px;font-size:13px;color:#6B7385;width:34%;vertical-align:top;">'.e($r[0]).'</td>'
            .'<td align="'.$align.'" style="padding:9px 14px;font-size:13px;color:#161B2B;font-weight:600;"><bdi>'.e($r[1]).'</bdi></td></tr>', $rows));

        return $row('<table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="'.($rtl ? 'rtl' : 'ltr').'" style="background:#F8F9FC;border:1px solid #EEF0F5;border-radius:10px;">'.$cells.'</table>');
    }
}
