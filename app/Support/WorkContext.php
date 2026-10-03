<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The seller's current working context - which platform (and module) they
 * were last on - kept in the session so moving between Content Publishing,
 * Ads Manager and Engagement doesn't ask them to pick the platform again.
 *
 * The URL always wins: a page that names a platform (/ads/tiktok/...,
 * /posts/listing?platform=tiktok) updates the context; the context is only
 * used to build links *into* a module (sidebar), never to override what
 * the URL asks for. No database - a refresh or deep link keeps working
 * because the URL carries the platform.
 */
class WorkContext
{
    private const KEY = 'work_context';

    /** Platforms each module can open directly. */
    public const MODULE_PLATFORMS = [
        'ads'   => ['facebook', 'instagram', 'tiktok', 'x', 'snapchat', 'google', 'youtube', 'linkedin'],
        'posts' => ['facebook', 'instagram', 'tiktok', 'x', 'snapchat', 'google', 'youtube', 'linkedin'],
        'comments' => ['facebook', 'instagram', 'tiktok', 'x', 'linkedin', 'youtube', 'google', 'threads', 'pinterest'],
        'inbox' => ['facebook', 'instagram', 'whatsapp', 'telegram', 'x', 'line', 'zalo', 'discord', 'slack', 'teams', 'google_chat', 'matrix', 'tiktok'],
    ];

    /** Update from the current request (called by RememberWorkContext). */
    public static function capture(Request $request): void
    {
        [$module, $platform] = self::fromRequest($request);

        if (!$module) {
            return;
        }

        $current = $request->session()->get(self::KEY, []);
        $request->session()->put(self::KEY, array_filter([
            'module'   => $module,
            // Overview pages keep the last platform - leaving a platform's
            // page for the overview isn't a choice to forget it.
            'platform' => $platform ?? ($current['platform'] ?? null),
        ]));
    }

    /** [module, platform|null] the request is on, or [null, null]. */
    public static function fromRequest(Request $request): array
    {
        $platform = null;

        if ($request->routeIs('admin.ads.*')) {
            $platform = $request->route('platform');
            return ['ads', self::normalise($platform)];
        }
        if ($request->routeIs('admin.posts.*')) {
            return ['posts', self::normalise($request->query('platform'))];
        }
        if ($request->routeIs('admin.comments.dashboard')) {
            return ['comments', self::normalise($request->query('platform'))];
        }
        if ($request->routeIs('admin.chats.dashboard')) {
            return ['inbox', self::normalise($request->query('platform'))];
        }

        return [null, null];
    }

    public static function platform(): ?string
    {
        return session(self::KEY . '.platform');
    }

    public static function module(): ?string
    {
        return session(self::KEY . '.module');
    }

    /** The context platform if $module can open it, else null. */
    public static function platformFor(string $module): ?string
    {
        $platform = self::platform();

        return $platform && in_array($platform, self::MODULE_PLATFORMS[$module] ?? [], true) ? $platform : null;
    }

    private static function normalise(?string $platform): ?string
    {
        $platform = strtolower(trim((string) $platform));
        $platform = $platform === 'twitter' ? 'x' : $platform;

        return preg_match('/^[a-z_]{1,20}$/', $platform) ? $platform : null;
    }
}
