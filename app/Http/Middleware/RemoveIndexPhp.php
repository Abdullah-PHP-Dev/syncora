<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 301 GET/HEAD requests that spell out the front controller
 * (/index.php/en/pricing, /index.php?x=1) to the clean URL, keeping the
 * query string - otherwise every page is also served as a 200 under
 * /index.php/. Reads the raw request URI, so the redirect target is
 * never itself an /index.php URL and can't loop. Trailing slashes are
 * dropped in the same hop (see RemoveTrailingSlash).
 */
class RemoveIndexPhp
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() && preg_match('#^/index\.php(?=[/?]|$)/*([^?]*)(\?.*)?$#', $request->getRequestUri(), $m)) {
            // Leading slashes are collapsed above and trailing ones here, so
            // "/index.php//evil.example" can't become a protocol-relative Location.
            return new RedirectResponse('/'.rtrim($m[1], '/').($m[2] ?? ''), 301);
        }

        return $next($request);
    }
}
