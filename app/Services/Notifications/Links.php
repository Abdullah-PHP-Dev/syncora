<?php

namespace App\Services\Notifications;

use App\Models\Notifications\NotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Notification links. Targets are stored as paths without a locale prefix, so every
 * reader lands in their own language; emails link through a signed /n/{event} route that
 * re-checks access and opens the right workspace before redirecting (rule R16).
 */
class Links
{
    /**
     * Runs $build with every generated link based on APP_URL, not on the host of the current
     * web request. Emails are now sent during the request, so without this they would link to
     * whatever address the person happened to use (e.g. a local syncora.test), and spam
     * filters block links to non-public hosts.
     */
    public static function withPublicRoot(callable $build): mixed
    {
        $root = rtrim((string) config('app.url'), '/');
        if (! preg_match('#^https?://#', $root)) {
            return $build();
        }
        URL::forceRootUrl($root);
        try {
            return $build();
        } finally {
            URL::forceRootUrl(null);
        }
    }

    /** An absolute link made earlier in the request, moved onto APP_URL. */
    public static function onPublicRoot(string $url): string
    {
        $root = rtrim((string) config('app.url'), '/');
        if (! preg_match('#^https?://#', $root) || ! preg_match('#^https?://[^/]+#', $url)) {
            return $url;
        }

        return preg_replace('#^https?://[^/]+#', $root, $url);
    }

    public static function path(string $url): string
    {
        $parts = parse_url($url);
        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        $locales = array_keys(config('laravellocalization.supportedLocales', ['en' => [], 'ar' => []]));
        $path = preg_replace('#^/('.implode('|', array_map('preg_quote', $locales)).')(?=/|$)#', '', $path) ?: '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /** Where a stored path should take this reader. */
    public static function localized(?string $path, string $locale): string
    {
        $path = $path ?: '/dashboard';
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        try {
            return \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getLocalizedURL($locale, url($path), [], true);
        } catch (\Throwable) {
            return url($path);
        }
    }

    /** Signed email link: valid 30 days (7 for security mail). */
    public static function email(NotificationEvent $event, ?User $user): string
    {
        $days = str_starts_with($event->event, 'security.') ? 7 : 30;

        return URL::temporarySignedRoute('notifications.go', now()->addDays($days), ['event' => $event->id, 'user' => $user?->id ?? 0]);
    }

    public static function unsubscribe(NotificationEvent $event, User $user): string
    {
        return URL::temporarySignedRoute('notifications.unsubscribe', now()->addDays(60), ['user' => $user->id, 'event' => $event->event, 'workspace' => (int) $event->workspace_id]);
    }

    public static function preferences(?User $user, ?string $category = null): string
    {
        return $user ? URL::temporarySignedRoute('notifications.manage', now()->addDays(30), ['user' => $user->id, 'category' => $category]) : url('/');
    }
}
