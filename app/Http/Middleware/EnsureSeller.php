<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSeller
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasRole('seller') && !$request->user()->isTeamMember(), 403);

        return $next($request);
    }
}
