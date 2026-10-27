<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class PreservePaymentReturnSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('callback/payment') && $request->isMethod('post') && $request->query('notification') !== '1') {
            // Cross-site POSTs omit SameSite=Lax cookies. Bounce before StartSession can
            // replace the customer's login cookie with a new anonymous session cookie.
            // Provider body fields are not trusted or needed: GET verifies our saved reference.
            return new RedirectResponse($request->getRequestUri(), 303, [
                'Cache-Control' => 'no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return $next($request);
    }
}
