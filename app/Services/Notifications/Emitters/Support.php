<?php

namespace App\Services\Notifications\Emitters;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/** Small helpers shared by the module emitters. */
class Support
{
    /** The real person acting in this request (not the workspace account they act as), or null for jobs/webhooks. */
    public static function actorId(): ?int
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }
        $request = request();
        $actor = $request->attributes->get('workspace_actor') ?? $request->user();

        return $actor?->id;
    }

    public static function url(string $route, array $params = [], string $fallback = 'dashboard'): ?string
    {
        try {
            return Route::has($route) ? route($route, $params) : (Route::has($fallback) ? route($fallback) : null);
        } catch (\Throwable) {
            return Route::has($fallback) ? route($fallback) : null;
        }
    }

    public static function platform(?string $platform): string
    {
        return match ($platform) {
            null, '' => 'Social',
            'meta', 'facebook' => 'Facebook',
            'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn',
            'x', 'twitter' => 'X', 'whatsapp' => 'WhatsApp', 'google' => 'Google', 'snapchat' => 'Snapchat',
            'pinterest' => 'Pinterest', 'threads' => 'Threads',
            default => Str::headline($platform),
        };
    }

    public static function excerpt(?string $text, int $limit = 80): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return $clean === '' ? '—' : Str::limit($clean, $limit);
    }

    /** "Chrome on Windows" from a user agent; never the raw string. */
    public static function device(?string $userAgent = null): string
    {
        $ua = $userAgent ?? (string) request()->userAgent();
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'])
            ->first(fn ($name, $needle) => str_contains($ua, $needle)) ?? 'a browser';
        $os = collect(['Windows' => 'Windows', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])
            ->first(fn ($name, $needle) => str_contains($ua, $needle)) ?? 'an unknown device';

        return "$browser · $os";
    }

    /** Network prefix only (IPv4 /16, IPv6 /32), never the full address. */
    public static function network(?string $ip = null): string
    {
        $ip = $ip ?? (string) request()->ip();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 2)).'.x.x';
        }

        return $ip === '' ? '—' : implode(':', array_slice(explode(':', $ip), 0, 2)).'::';
    }

    public static function money($amount, ?string $currency = null): string
    {
        return ($currency ?: 'SAR').' '.number_format((float) $amount, 2);
    }
}
