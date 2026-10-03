<?php

namespace App\Http\Middleware;

use App\Support\WorkContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Records the platform/module of each full page view - see WorkContext. */
class RememberWorkContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET') && !$request->ajax() && !$request->expectsJson()) {
            WorkContext::capture($request);
        }

        return $next($request);
    }
}
