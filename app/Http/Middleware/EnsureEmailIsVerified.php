<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * New self-registered accounts can't use the app until they confirm their email.
 *
 * Applies to signed-in pages only (routes behind `auth`); public pages stay open.
 * Who must verify is decided by User::mustVerifyBeforeUse(): sellers who signed up after
 * `notifications.verification_since`. Older accounts, staff and invited teammates are never blocked.
 * Runs before UseSellerWorkspace, so it checks the real person, not the workspace identity.
 */
class EnsureEmailIsVerified
{
    /** Reachable while unverified: the verification screens themselves and signing out. */
    private const ALLOWED = ['verification.notice', 'verification.verify', 'verification.send', 'verification.code', 'verification.change-email', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();
        if (! $user || ! $route || ! method_exists($user, 'mustVerifyBeforeUse') || ! $user->mustVerifyBeforeUse()) {
            return $next($request);
        }
        $name = (string) $route->getName();
        foreach (self::ALLOWED as $allowed) {
            if ($name === $allowed || str_ends_with($name, '.'.$allowed)) {
                return $next($request);
            }
        }
        if (! in_array('auth', $route->gatherMiddleware(), true) && ! collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'))) {
            return $next($request); // public page
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Please verify your email address to continue.'), 'redirect' => route('verification.notice')], 403);
        }

        return redirect()->route('verification.notice');
    }
}
