<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers the server / Cloudflare don't send yet (audit
 * 2026-10-22: X-Frame-Options and X-Content-Type-Options already come from
 * the edge). Never overrides a header that's already set.
 *
 * HSTS only over HTTPS, one year, deliberately without includeSubDomains /
 * preload so no subdomain can be locked out - extend it once every
 * subdomain is confirmed HTTPS-only.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        return $response;
    }
}
