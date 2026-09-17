<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user() && $request->user()->is_active === false, 403, __('This account is disabled.'));
        return $next($request);
    }
}
