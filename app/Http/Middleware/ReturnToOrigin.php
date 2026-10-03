<?php

namespace App\Http\Middleware;

use App\Models\SocialAccount;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Return-to-origin for every account-connect (OAuth) flow - Content
 * (social-accounts / post-accounts), Ads (ads/{platform}) and Messaging
 * (messaging/auth/*) - without touching their individual callbacks.
 *
 *  1. Connect start (…/redirect): remembers where the user was - an
 *     explicit ?return_to=, else the Referer of the page they clicked
 *     "Connect" on - but only an internal, allow-listed app page.
 *  2. Callback (…/callback): each callback still saves the account and
 *     flashes its own success/error exactly as before. If it then sends
 *     the user to one of the generic landing pages (ads dashboard,
 *     channels, composer…), the redirect is pointed back at the
 *     remembered page instead, with ?connected=<new account id> so that
 *     page can preselect it, plus a toast (see layouts.partials.connect-toast).
 *
 * Any other callback target (eg. an intermediate "pick your pages"
 * step) is left alone.
 */
class ReturnToOrigin
{
    public const SESSION_KEY = 'oauth_return';

    /** Route names that start a connect flow. */
    private const START_ROUTES = [
        'admin.social-accounts.redirect',
        'admin.posts.redirect',
        'admin.post-accounts.*.redirect',
        'admin.ads.redirect',
        'admin.messaging.auth.*.redirect',
    ];

    /** Route names that finish one. */
    private const CALLBACK_ROUTES = [
        'admin.social-accounts.callback',
        'admin.post-accounts.*.callback',
        'admin.ads.platform.callback',
        'admin.messaging.auth.*.callback',
    ];

    /** Generic pages callbacks land on - only these get redirected back. */
    private const DEFAULT_LANDINGS = [
        'admin.ads.dashboard',
        'admin.chats.channels',
        'admin.chats.dashboard',
        'admin.posts.create',
        'admin.posts.dashboard',
        'admin.posts.index',
    ];

    /** App areas a user may be returned to (after an optional locale prefix). */
    private const ALLOWED_PREFIXES = ['ads', 'posts', 'chats', 'comments', 'media-gallery', 'ai-copilot', 'knowledge-base', 'email', 'platform'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->routeIs(...self::START_ROUTES)) {
            $this->remember($request);
        }

        $startedAt = now()->subSecond();
        $response = $next($request);

        if ($request->user() && $request->routeIs(...self::CALLBACK_ROUTES) && $response instanceof RedirectResponse) {
            return $this->returnToOrigin($request, $response, $startedAt);
        }

        return $response;
    }

    /**
     * An internal, allow-listed path (+query) or null. Rejects other hosts,
     * protocol-relative //evil.com, backslash tricks, and the OAuth routes
     * themselves (no redirect loops).
     */
    public static function sanitize(?string $url, ?string $currentHost = null): ?string
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x1F\\\\]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || !$currentHost
                || strcasecmp($parts['host'] ?? '', $currentHost) !== 0
                || isset($parts['user'])) {
                return null;
            }
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return null;
        }

        $appPath = preg_replace('#^/[a-z]{2}(?=/|$)#', '', $path);
        $prefixes = implode('|', array_map('preg_quote', self::ALLOWED_PREFIXES));
        if (!preg_match('#^/(' . $prefixes . ')(/|$)#', $appPath) || preg_match('#/(redirect|callback)(/|$)#', $appPath)) {
            return null;
        }

        // Drop a stale ?connected= from an earlier round trip.
        parse_str($parts['query'] ?? '', $query);
        unset($query['connected']);

        return $path . ($query ? '?' . http_build_query($query) : '');
    }

    private function remember(Request $request): void
    {
        $origin = self::sanitize($request->query('return_to'), $request->getHost())
            ?? self::sanitize($request->headers->get('referer'), $request->getHost());

        // Always overwrite: an abandoned earlier connect must not send a
        // later, unrelated one to the wrong page.
        $request->session()->put(self::SESSION_KEY, $origin ? [
            'url'      => $origin,
            'platform' => $request->route('platform') ?? $this->platformFromRoute($request),
            'retry'    => $request->isMethod('GET') ? $request->fullUrlWithQuery(['return_to' => $origin]) : null,
        ] : null);
    }

    private function returnToOrigin(Request $request, RedirectResponse $response, $startedAt): Response
    {
        $origin = $request->session()->pull(self::SESSION_KEY);

        if (!$origin || !$this->isDefaultLanding($response->getTargetUrl())) {
            return $response;
        }

        $session = $request->session();
        $error = $session->get('error');
        $success = $session->get('success');
        $platform = ucfirst(str_replace(['_', '-'], ' ', (string) ($origin['platform'] ?? 'Account')));

        $target = $origin['url'];
        $connectedId = null;

        if (!$error) {
            // The account this callback just created/updated.
            $connectedId = SocialAccount::where('user_id', $request->user()->id)
                ->where('updated_at', '>=', $startedAt)
                ->latest('updated_at')
                ->value('id');

            if ($connectedId) {
                $target .= (str_contains($target, '?') ? '&' : '?') . 'connected=' . $connectedId;
            }
        }

        $session->flash('connect_notice', [
            'type'    => $error ? 'error' : 'success',
            'title'   => $error ? "{$platform} connection could not be completed" : "{$platform} connected",
            'message' => $error ?: ($success ?: "You're ready to continue."),
            'retry'   => $error ? ($origin['retry'] ?? null) : null,
        ]);

        // The toast replaces the page's own success/error alert (no double
        // message) - except on the standalone create-new wizard, which
        // doesn't use the app layout the toast lives in.
        if (!str_contains(parse_url($target, PHP_URL_PATH) ?? '', '/create-new')) {
            $session->forget(['success', 'error']);
        }

        return redirect()->to($target);
    }

    private function isDefaultLanding(string $url): bool
    {
        $path = rtrim(preg_replace('#^/[a-z]{2}(?=/|$)#', '', parse_url($url, PHP_URL_PATH) ?? ''), '/');

        foreach (self::DEFAULT_LANDINGS as $name) {
            $landing = rtrim(preg_replace('#^/[a-z]{2}(?=/|$)#', '', parse_url(route($name), PHP_URL_PATH) ?? ''), '/');
            if ($path === $landing) {
                return true;
            }
        }

        return false;
    }

    private function platformFromRoute(Request $request): ?string
    {
        // admin.post-accounts.instagram.redirect -> instagram
        return preg_match('/\.(?:post-accounts|auth)\.([a-z_\-]+)\.redirect$/', (string) $request->route()?->getName(), $m) ? $m[1] : null;
    }
}
