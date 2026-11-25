<?php

namespace App\Services\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * Renders an event's wording from lang/{locale}/notifications.php for one reader.
 * Placeholders are filled from the stored params; dates are shown in the reader's
 * timezone with its abbreviation. Customer-provided text is passed through as data
 * (Blade / Vue escape it), never translated.
 */
class NotificationText
{
    public const PARTS = ['title', 'body', 'subject', 'preheader', 'headline', 'cta', 'note'];

    /** @return array<string, ?string> */
    public static function render(string $event, array $payload, string $locale, string $timezone, int $count = 1): array
    {
        $params = self::params($payload, $locale, $timezone);
        if (isset($params['legacy_title'])) {
            // Bell items moved over from the former CRM notifier keep their stored text.
            return ['title' => (string) $params['legacy_title'], 'body' => $params['legacy_body'] ?? null, 'subject' => (string) $params['legacy_title'],
                'preheader' => null, 'headline' => (string) $params['legacy_title'], 'cta' => trans('notifications.ui.open', [], $locale), 'note' => null];
        }
        $params['count'] = $count;
        // Plural forms follow the group size, or a number the event names (e.g. days left).
        $number = $count > 1 ? $count : (int) ($params['choice'] ?? 1);
        $lines = self::eventLines($event, $locale);
        $out = [];
        foreach (self::PARTS as $part) {
            $raw = $count > 1 && isset($lines['group_'.$part]) ? $lines['group_'.$part] : ($lines[$part] ?? null);
            $out[$part] = is_string($raw) ? self::fill($raw, $number, $params, $locale) : null;
        }
        $out['title'] ??= $event;
        $out['subject'] ??= $out['title'];
        $out['headline'] ??= $out['title'];
        $out['cta'] ??= trans('notifications.ui.open', [], $locale);

        return $out;
    }

    /**
     * An event's lines. Event names contain a dot ("publishing.post_failed"), which Laravel's
     * translator would read as nesting, so the events array is read directly (English fallback).
     */
    public static function eventLines(string $event, string $locale): array
    {
        $all = Lang::get('notifications.events', [], $locale);
        $lines = is_array($all) ? ($all[$event] ?? null) : null;
        if ($lines === null && $locale !== 'en') {
            $fallback = Lang::get('notifications.events', [], 'en');
            $lines = is_array($fallback) ? ($fallback[$event] ?? null) : null;
        }

        return $lines ?? [];
    }

    /** The settings-page label of an event (same dotted-key reason as eventLines). */
    public static function label(string $event, ?string $locale = null): string
    {
        $labels = Lang::get('notifications.preferences.event_labels', [], $locale ?? app()->getLocale());

        return is_array($labels) && isset($labels[$event]) ? $labels[$event] : $event;
    }

    /** Plural choice (only for lines with pipes - "[#:ticket] ..." must stay literal) and :placeholders. */
    public static function fill(string $line, int $number, array $params, string $locale): string
    {
        if (str_contains($line, '|')) {
            $line = app('translator')->getSelector()->choose($line, $number, $locale);
        }
        uksort($params, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
        foreach ($params as $key => $value) {
            $value = (string) $value;
            $line = str_replace([':'.$key, ':'.\Illuminate\Support\Str::ucfirst($key), ':'.\Illuminate\Support\Str::upper($key)],
                [$value, \Illuminate\Support\Str::ucfirst($value), \Illuminate\Support\Str::upper($value)], $line);
        }

        return $line;
    }

    /** A plain (non-event) translation line with plural support. */
    public static function line(string $key, int $number, array $params, string $locale): string
    {
        $raw = Lang::get($key, [], $locale);

        return is_string($raw) ? self::fill($raw, $number, $params, $locale) : $key;
    }

    public static function params(array $payload, string $locale, string $timezone): array
    {
        $params = $payload['params'] ?? [];
        foreach ($payload['dates'] ?? [] as $name => $iso) {
            $params[$name] = self::date($iso, $locale, $timezone);
        }
        // Names that must follow the reader's language are stored as keys.
        if (isset($params['feature_key'])) {
            $params['feature'] = config('package_reference.features.'.$params['feature_key'].'.name_'.$locale, $params['feature_key']);
        }
        if (isset($params['reason_key'])) {
            $params['reason'] = trans('notifications.reasons.'.$params['reason_key'], [], $locale);
        }
        if (isset($params['role_key'])) {
            $params['role'] = trans('notifications.roles.'.$params['role_key'], [], $locale);
        }
        foreach ($params as $k => $v) {
            $params[$k] = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        }

        return $params;
    }

    public static function date($value, string $locale, string $timezone, bool $time = true): string
    {
        $date = Carbon::parse($value)->setTimezone($timezone)->locale($locale);

        return $date->translatedFormat($time ? 'j M Y, H:i' : 'j M Y').($time ? ' ('.$date->format('T').')' : '');
    }
}
