<?php

namespace App\Services\PlatformEmails;

use App\Services\Notifications\NotificationCatalogue;
use App\Services\Notifications\NotificationText;
use Illuminate\Support\Facades\Lang;

/**
 * Every email an administrator can edit, and the variables each one really receives.
 *
 * Templates come from the platform notification catalogue (config/notifications.php) plus the
 * two account emails Laravel sends itself (verification, password reset). Nothing is listed
 * twice and new catalogue events appear here automatically.
 *
 * Event variables are read from the built-in wording (lang/en/notifications.php): each
 * :placeholder there is a value the emitting module provides, so the list is exactly what the
 * event carries - never a variable the event can't fill.
 */
class TemplateRegistry
{
    public const LOCALES = ['en', 'ar'];

    /** Emails that keep account access working: they can be edited but never switched off. */
    public const LOCKED = ['auth.verify_email', 'auth.password_reset'];

    public const AUTH = [
        'auth.verify_email' => ['category' => 'security', 'tone' => 'action', 'vars' => ['verification_url', 'verification_otp', 'otp_expiry', 'expire_minutes', 'company_name', 'company_logo']],
        'auth.password_reset' => ['category' => 'security', 'tone' => 'critical', 'vars' => ['expire_minutes']],
    ];

    /** Variables a published version must contain, or customers couldn't complete the action (the default is sent instead). */
    public const REQUIRED = ['auth.verify_email' => ['verification_otp', 'verification_url']];

    /** Events whose built-in email is a designed template (sent through the renderer even before an admin edits it). */
    public const DESIGNED = ['support.new_signup'];

    /** Values an event carries beyond the placeholders its built-in wording happens to use. */
    public const EVENT_VARS = [
        'support.new_signup' => ['name', 'email', 'mobile', 'workspace', 'plan', 'status', 'end', 'when', 'language', 'country', 'device', 'network', 'account_id'],
    ];

    public const GENERAL = ['customer_name', 'customer_email', 'workspace_name', 'application_name', 'application_url', 'support_email', 'current_date', 'current_year', 'action_url'];

    /** Realistic sample values for previews and test emails (never real customer data). */
    public const SAMPLE = [
        'customer_name' => 'Sara Ahmed', 'customer_email' => 'sara@example.com', 'workspace_name' => 'Acme Trading',
        'application_name' => 'Socialeaz', 'support_email' => 'support@socialeaz.com', 'expire_minutes' => '60',
        'name' => 'Sara Ahmed', 'platform' => 'Instagram', 'account' => '@acme', 'excerpt' => 'Spring offer: 20% off all orders this week',
        'reason' => 'Media aspect ratio not supported', 'plan' => 'Growth', 'amount' => 'SAR 199.00', 'receipt' => 'TX-10423', 'days' => '3',
        'feature' => 'AI credits', 'used' => '800', 'limit' => '1000', 'percent' => '80', 'inviter' => 'Sara Ahmed', 'workspace' => 'Acme Trading',
        'role' => 'Member', 'email' => 'omar@example.com', 'scheduled' => '3', 'actor' => 'Omar Ali', 'succeeded' => '2', 'total' => '3',
        'failed' => 'LinkedIn', 'customer' => 'Mohammed', 'channel' => 'WhatsApp', 'minutes' => '72', 'author' => 'Noura',
        'value' => 'SAR 48,000', 'subject' => 'Follow up with Noura', 'automation' => 'Welcome new leads', 'message' => 'New lead from the website',
        'step' => 'Create ad set', 'campaign' => 'November Newsletter', 'service' => 'Analytics', 'ticket' => 'SLZ-10423', 'priority' => 'High',
        'device' => 'Chrome · Windows', 'network' => '188.50.x.x', 'source' => 'Facebook lead ad', 'method' => 'Tamara', 'interest' => 'Sofa set',
        'when' => '24 Nov 2026, 14:05 (AST)', 'end' => '27 Nov 2026', 'expires' => '1 Dec 2026, 09:00 (AST)', 'period_end' => '24 Dec 2026',
        'due' => '24 Nov 2026, 16:00 (AST)',
        'mobile' => '+966 55 123 4567', 'status' => 'Trial', 'language' => 'English', 'country' => 'SA', 'account_id' => '#1042',
        'verification_otp' => '482915', 'otp_expiry' => '10', 'company_name' => 'Socialeaz',
    ];

    private const INTERNAL = ['count', 'choice'];

    public function __construct(private NotificationCatalogue $catalogue) {}

    public function exists(string $key): bool
    {
        return isset(self::AUTH[$key]) || $this->catalogue->has($key);
    }

    /** @return array<string, array{key: string, category: string, tone: string, mandatory: bool, locked: bool}> */
    public function all(): array
    {
        $list = [];
        foreach (self::AUTH as $key => $meta) {
            $list[$key] = ['key' => $key, 'category' => $meta['category'], 'tone' => $meta['tone'], 'mandatory' => true, 'locked' => true];
        }
        foreach ($this->catalogue->all() as $key => $config) {
            $list[$key] = ['key' => $key, 'category' => $config['category'], 'tone' => $config['tone'], 'mandatory' => $config['mandatory'], 'locked' => false];
        }

        return $list;
    }

    public function get(string $key): array
    {
        return $this->all()[$key] ?? throw new \InvalidArgumentException("Unknown email template [$key].");
    }

    public function label(string $key, ?string $locale = null): string
    {
        return isset(self::AUTH[$key]) ? trans('platform_emails.auth_labels.'.substr($key, 5), [], $locale) : NotificationText::label($key, $locale);
    }

    /** Variable names available to this template (general first). */
    public function variables(string $key): array
    {
        if (isset(self::AUTH[$key])) {
            return array_values(array_unique(array_merge(self::GENERAL, self::AUTH[$key]['vars'])));
        }
        $found = [];
        foreach (self::LOCALES as $locale) {
            foreach (NotificationText::eventLines($key, $locale) as $line) {
                if (is_string($line) && preg_match_all('/:([a-z][a-z0-9_]*)/', $line, $m)) {
                    $found = array_merge($found, $m[1]);
                }
            }
        }

        $found = array_merge($found, self::EVENT_VARS[$key] ?? []);

        return array_values(array_unique(array_merge(self::GENERAL, array_diff($found, self::INTERNAL))));
    }

    /** For the editor's variable panel. */
    public function describe(string $key, ?string $locale = null): array
    {
        return array_map(fn ($name) => [
            'name' => $name,
            'token' => '{{ '.$name.' }}',
            'group' => in_array($name, self::GENERAL, true) ? 'general' : 'event',
            'description' => Lang::has('platform_emails.variables.'.$name, $locale) ? trans('platform_emails.variables.'.$name, [], $locale) : ucfirst(str_replace('_', ' ', $name)),
            'example' => self::SAMPLE[$name] ?? ($name === 'action_url' ? url('/n/…') : ($name === 'application_url' ? url('/') : ($name === 'current_year' ? date('Y') : ($name === 'current_date' ? now()->format('j M Y') : '…')))),
        ], $this->variables($key));
    }

    /**
     * The built-in wording as an editable template (the starting point and what Reset restores).
     * Plural lines take their general form ("ends in {{ days }} days").
     */
    public function defaults(string $key, string $locale): array
    {
        $vars = $this->variables($key);
        if ($key === 'auth.verify_email') {
            return $this->verifyDefaults($locale);
        }
        if ($key === 'support.new_signup') {
            return $this->signupDefaults($locale);
        }
        if (isset(self::AUTH[$key])) {
            $prefix = $key === 'auth.verify_email' ? 'verify' : 'reset';
            $line = fn ($part) => $this->tokens((string) trans("notifications.auth.{$prefix}_{$part}", [], $locale), array_merge($vars, ['name', 'email', 'minutes']), ['name' => 'customer_name', 'email' => 'customer_email', 'minutes' => 'expire_minutes']);

            return self::content($line('subject'), $line('preheader'), $line('headline'), [['type' => 'text', 'text' => $line('body')], ['type' => 'text', 'text' => $line('note')]], $line('cta'));
        }
        $lines = NotificationText::eventLines($key, $locale);
        $get = fn ($part) => isset($lines[$part]) ? $this->tokens($lines[$part], $vars) : '';
        $title = $get('title');
        $blocks = array_values(array_filter([
            $get('body') !== '' ? ['type' => 'text', 'text' => $get('body')] : null,
            ['type' => 'details'],
            $get('note') !== '' ? ['type' => 'text', 'text' => $get('note')] : null,
        ]));

        return self::content($get('subject') ?: $title, $get('preheader'), $get('headline') ?: $title, $blocks, $get('cta') ?: (string) trans('notifications.ui.open', [], $locale));
    }

    /** Welcome + option 1 (button) + option 2 (six-digit code). Wording: notifications.auth.verify_* */
    private function verifyDefaults(string $locale): array
    {
        $t = fn ($k) => $this->tokens((string) trans('notifications.auth.verify_'.$k, [], $locale), ['otp_expiry'], ['name' => 'customer_name', 'minutes' => 'otp_expiry']);
        $content = self::content($t('subject'), $t('preheader'), $t('headline'), [
            ['type' => 'text', 'text' => $t('body')],
            ['type' => 'heading', 'text' => $t('option_link')],
            ['type' => 'button', 'label' => $t('cta'), 'url' => '{{ verification_url }}'],
            ['type' => 'text', 'text' => $t('option_link_hint')],
            ['type' => 'divider'],
            ['type' => 'heading', 'text' => $t('option_code')],
            ['type' => 'text', 'text' => $t('code_label')],
            ['type' => 'code', 'text' => '{{ verification_otp }}'],
            ['type' => 'text', 'text' => $t('code_hint')],
            ['type' => 'text', 'text' => $t('note')],
        ], '');
        $content['cta_url'] = '';

        return $content;
    }

    /** The designed built-in template for this email, or null when it uses the standard layout. */
    public function designed(string $key, string $locale): ?array
    {
        return in_array($key, self::DESIGNED, true) ? $this->defaults($key, $locale) : null;
    }

    /** Team alert for a new customer: intro, three detail cards, profile button, reply link. */
    private function signupDefaults(string $locale): array
    {
        $t = fn ($k, $r = []) => (string) trans('notifications.signup.'.$k, $r, $locale);
        $card = fn (array $rows) => ['type' => 'card', 'rows' => array_map(fn ($label, $var) => ['label' => $t($label), 'value' => '{{ '.$var.' }}'], array_keys($rows), $rows)];
        $line = fn ($part) => $this->tokens((string) (NotificationText::eventLines('support.new_signup', $locale)[$part] ?? ''), self::EVENT_VARS['support.new_signup']);

        $content = self::content($line('subject'), $line('preheader'), $line('headline'), [
            ['type' => 'text', 'text' => $t('intro', ['name' => '{{ name }}'])],
            ['type' => 'heading', 'text' => $t('customer')],
            $card(['name' => 'name', 'email' => 'email', 'mobile' => 'mobile', 'business' => 'workspace']),
            ['type' => 'heading', 'text' => $t('account')],
            $card(['plan' => 'plan', 'status' => 'status', 'trial_end' => 'end', 'account_id' => 'account_id']),
            ['type' => 'heading', 'text' => $t('context')],
            $card(['when' => 'when', 'language' => 'language', 'country' => 'country', 'device' => 'device', 'network' => 'network']),
            ['type' => 'text', 'text' => $t('note')],
        ], $line('cta'));
        $content['secondary_text'] = $t('reply');
        $content['secondary_url'] = 'mailto:{{email}}'; // no spaces: a link can't contain them
        $content['footer'] = $t('footer');

        return $content;
    }

    public static function content(string $subject, string $preheader, string $heading, array $blocks, string $cta): array
    {
        return ['subject' => $subject, 'preheader' => $preheader, 'heading' => $heading, 'blocks' => $blocks,
            'cta_text' => $cta, 'cta_url' => '{{ action_url }}', 'secondary_text' => '', 'secondary_url' => '', 'footer' => ''];
    }

    /** ":param" placeholders → "{{ param }}" (longest names first), plural lines → their general form. */
    private function tokens(string $line, array $vars, array $aliases = []): string
    {
        if (str_contains($line, '|')) {
            // Only plural lines carry a leading condition ("[2,*] ..."); "[#:ticket] ..." is literal text.
            $forms = explode('|', $line);
            $line = trim(preg_replace('/^\s*[\{\[][0-9,*\s]*[\]\}]\s*/', '', end($forms)));
        }
        $names = array_unique(array_merge($vars, array_keys($aliases)));
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($names as $name) {
            $line = str_replace(':'.$name, '{{ '.($aliases[$name] ?? $name).' }}', $line);
        }

        return $line;
    }
}
