<?php

namespace App\Http\Middleware;

use App\Models\SellerTeamMember;
use App\Services\PackageAccessService;
use App\Services\SellerTeamService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UseSellerWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->user();
        $id = $request->session()->get('seller_workspace_member');
        $route = $request->route()?->getName() ?? '';
        // Workspace selection, invitations, and logout always use the real identity.
        if (! $actor || ! $id || Str::is(['seller-workspaces.*', 'logout'], $route)) {
            return $next($request);
        }
        abort_if($actor->is_active === false || $actor->isTeamMember(), 403);
        $member = SellerTeamMember::whereKey($id)->where('user_id', $actor->id)->first();
        if (! $member || ! app(SellerTeamService::class)->usable($member)) {
            $request->session()->forget('seller_workspace_member');
            abort(403, __('seller_team.unavailable'));
        }
        $access = app(PackageAccessService::class);
        $broadcast = $request->is('broadcasting/auth') && $request->isMethod('post')
            && preg_match('/^private-(?:inbox\.[0-9]+|conversation\.[0-9]+)$/D', (string) $request->input('channel_name'));
        $modules = $broadcast ? ['inbox'] : $access->modulesForRoute($route);
        // Unmapped routes (billing, profiles, team administration, integrations,
        // platform administration, etc.) never inherit the owner's identity.
        abort_unless($modules !== [] && ! in_array('team_management', $modules, true), 403, __('seller_team.permission_denied'));
        // Provider credentials and OAuth connections remain owner-managed.
        abort_if(Str::is(['admin.post-accounts.*', 'admin.messaging.auth.*', '*.redirect', '*.callback', '*.connect', '*.disconnect'], $route), 403, __('seller_team.permission_denied'));
        $write = ! $broadcast && (! $request->isMethodSafe() || Str::is(config('package_access.write_get_routes'), $route));
        foreach ($modules as $module) {
            $permission = $member->permissions[$module] ?? 'none';
            abort_unless($permission === 'write' || ($permission === 'read' && ! $write), 403, __('seller_team.permission_denied'));
            if ($broadcast) {
                // Broadcast routes do not pass through the seller module middleware.
                $access->authorize($member->owner, $module, false);
            }
        }
        $request->attributes->set('workspace_actor', $actor);
        $request->attributes->set('workspace_member', $member);
        // Request-only identity: the login session remains the member's own account.
        // Existing controllers scope all workspace resources and quotas to this owner.
        Auth::guard('web')->setUser($member->owner);
        try {
            return $next($request);
        } finally {
            Auth::guard('web')->setUser($actor);
        }
    }
}
