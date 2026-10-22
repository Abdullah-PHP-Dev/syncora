<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 301 any GET/HEAD path ending in "/" (except the root) to the same path
 * without it, keeping the query string - Laravel's router otherwise serves
 * /en/ and /en as two 200 URLs. The Location is relative so the redirect
 * keeps whatever scheme/host the edge (Cloudflare) received.
 */
class RemoveTrailingSlash
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->getPathInfo();
        if ($path !== '/' && str_ends_with($path, '/') && $request->isMethodSafe() && ! $request->is('api/*')) {
            $query = $request->getQueryString();

            // trim() on both ends also collapses "//host/" so it can never
            // become a protocol-relative (off-site) Location.
            return new RedirectResponse($request->getBaseUrl().'/'.trim($path, '/').($query !== null ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
