<?php

namespace App\Services\PlatformEmails;

use App\Mail\PlatformNotificationMail;

/**
 * Layout data for an email rendered from template content (published, a draft in the editor,
 * or the built-in default). Used by the account emails, the admin preview and test sends.
 */
class TemplateMailBuilder
{
    public function __construct(private TemplateRegistry $registry, private TemplateRenderer $renderer) {}

    public function layout(string $key, string $locale, array $content, array $vars, array $details = [], array $extra = []): array
    {
        $meta = $this->registry->get($key);
        $r = $this->renderer->render($content, $vars, $locale, $details);

        return PlatformNotificationMail::layoutData($locale, $meta['tone'], [
            'subject' => $r['subject'], 'preheader' => $r['preheader'], 'headline' => $r['headline'], 'cta' => $r['cta'], 'body' => null, 'note' => null,
        ], $extra + [
            'blocks_html' => $r['blocks_html'], 'cta_url' => $r['cta_url'], 'secondary' => $r['secondary'], 'footer_custom' => $r['footer'],
            'category' => trans('notifications.categories.'.$meta['category'], [], $locale),
        ]);
    }

    /** Sample values for previews and tests: never real customer data. */
    public function sampleVariables(string $key, string $locale): array
    {
        $vars = [];
        foreach ($this->registry->describe($key, $locale) as $v) {
            $vars[$v['name']] = (string) $v['example'];
        }
        $vars['action_url'] = url('/');
        $vars['application_url'] = EmailBranding::get()['website_url'];
        if (array_key_exists('verification_url', $vars)) {
            $vars['verification_url'] = url('/email/verify/link/sample');
            $vars['company_logo'] = (string) EmailBranding::get()['logo_url'];
        }

        return $vars;
    }

    /** Detail rows a "details" block shows for this sample (labels in the email's language). */
    public function sampleDetails(string $key, string $locale): array
    {
        $vars = $this->registry->variables($key);

        return collect(['platform', 'account', 'reason', 'plan', 'amount', 'ticket', 'campaign', 'source', 'channel'])
            ->filter(fn ($k) => in_array($k, $vars, true))
            ->map(fn ($k) => [trans('notifications.details.'.$k, [], $locale), TemplateRegistry::SAMPLE[$k] ?? '…'])->values()->all();
    }
}
