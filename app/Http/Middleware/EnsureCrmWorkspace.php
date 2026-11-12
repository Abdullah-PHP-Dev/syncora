<?php

namespace App\Http\Middleware;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Route model binding (SubstituteBindings) runs before UseSellerWorkspace
 * switches the request to the workspace account, so CRM models are bound
 * without their workspace scope (BelongsToWorkspace::resolveRouteBinding).
 * This runs after the switch and turns any record from another workspace
 * into a 404. Applied to every CRM route group.
 */
class EnsureCrmWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && in_array(BelongsToWorkspace::class, class_uses_recursive($parameter), true)) {
                abort_unless((int) $parameter->workspace_id === (int) Auth::id() && \App\Services\Crm\CrmVisibility::allows($parameter), 404);
            }
        }

        return $next($request);
    }
}
