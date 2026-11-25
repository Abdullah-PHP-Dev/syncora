<?php

namespace App\Services\PlatformEmails;

use App\Support\Settings;

/**
 * Global look and sender details for every platform email, editable by administrators.
 * Stored as one blob in admin_settings (App\Support\Settings); unset fields fall back to config.
 */
class EmailBranding
{
    public const KEY = 'email_branding';

    public const SOCIAL = ['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok'];

    public static function defaults(): array
    {
        $from = config('notifications.from');

        return [
            'app_name' => 'Socialeaz',
            'logo_url' => asset('assets/img/logo/socialeaz-logo-32.png'),
            'sender_name' => $from['name'],
            'sender_email' => $from['address'],
            'reply_to' => $from['reply_to'],
            'support_email' => $from['reply_to'],
            'website_url' => rtrim((string) config('app.url'), '/'),
            'primary_color' => '#5B3DF5',
            'secondary_color' => '#161B2B',
            'footer_text' => (string) config('notifications.footer_address'),
            'copyright' => '© {{ current_year }} Socialeaz',
            'social' => array_fill_keys(self::SOCIAL, ''),
        ];
    }

    public static function get(): array
    {
        $saved = rescue(fn () => Settings::get(self::KEY), null, false);
        $saved = is_string($saved) ? (json_decode($saved, true) ?: []) : (is_array($saved) ? $saved : []);
        $brand = array_replace(self::defaults(), array_filter($saved, fn ($v) => $v !== null && $v !== ''));
        $brand['social'] = array_replace(self::defaults()['social'], array_intersect_key((array) ($saved['social'] ?? []), self::defaults()['social']));

        return $brand;
    }

    public static function save(array $values): array
    {
        Settings::set(self::KEY, json_encode(array_intersect_key($values, self::defaults()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::get();
    }
}
