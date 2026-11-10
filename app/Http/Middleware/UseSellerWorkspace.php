<?php

namespace App\Http\Middleware;

use App\Models\SellerTeamMember;
use App\Services\PackageAccessService;
use App\Services\SellerTeamService;
use App\Services\WorkspaceService;
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
        if (! $actor || Str::is(['seller-workspaces.*', 'logout'], $route)) {
            if ($actor && ! $id && $route !== 'logout' && $request->session()->has(WorkspaceService::SESSION_KEY)) {
                // For display only (sidebar switcher); the request keeps the real identity.
                $workspace = app(WorkspaceService::class)->active($request, $actor);
                if ($workspace) {
                    $request->attributes->set('workspace', $workspace);
                }
            }

            return $next($request);
        }
        if (! $id) {
            // A teammate-only account (joined from an invitation, no organization of its own)
            // has nothing to show outside a workspace: send it to its workspace list.
            if ($this->teammateOnly($actor) && ! Str::is(['password.*', 'verification.*', 'profile.*', 'admin.profiles.*', 'seller-workspaces.*', 'login', 'register', 'logout'], $route)) {
                abort_if($request->expectsJson(), 403, __('seller_team.unavailable'));

                return redirect()->route('seller-workspaces.index');
            }

            return $this->ownedWorkspace($request, $next, $actor, $route);
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
        // An Admin also manages connections and the team; everything still runs
        // as the owner, so the owner's plan, seats and capacities apply unchanged.
        $adminRoute = $member->allowsRoute($route);
        // Unmapped routes (billing, profiles, team administration, integrations,
        // platform administration, etc.) never inherit the owner's identity.
        abort_unless($adminRoute || ($modules !== [] && ! in_array('team_management', $modules, true)), 403, __('seller_team.permission_denied'));
        // Provider credentials and OAuth connections remain with the owner (and Admins).
        abort_if(! $member->isAdmin() && Str::is(['admin.post-accounts.*', 'admin.messaging.auth.*', '*.redirect', '*.callback', '*.connect', '*.disconnect'], $route), 403, __('seller_team.permission_denied'));
        $write = ! $broadcast && (! $request->isMethodSafe() || Str::is(config('package_access.write_get_routes'), $route));
        foreach ($modules as $module) {
            abort_unless($member->canUse($module, $write), 403, __('seller_team.permission_denied'));
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

    /**
     * One of the actor's own brand/client workspaces is open: run the request
     * as that workspace's account, so every module's user_id scoping applies
     * to it. Ownership is re-checked on every request (WorkspaceService::active).
     */
    private function teammateOnly($actor): bool
    {
        return ! $actor->hasAnyRole(['seller', 'admin', 'customer_support']) && ! $actor->isWorkspaceAccount();
    }

    private function ownedWorkspace(Request $request, Closure $next, $actor, string $route)
    {
        // Nothing opened: the person's own account (their default workspace).
        if (! $request->session()->has(WorkspaceService::SESSION_KEY)) {
            return $next($request);
        }
        $workspace = app(WorkspaceService::class)->active($request, $actor);
        if (! $workspace) {
            return $next($request);
        }
        $request->attributes->set('workspace', $workspace);
        // Billing, sign-in and personal settings stay with the person, not the brand.
        $personal = Str::is(['admin.subscription.*', 'payment.*', 'payments.*', 'password.*', 'verification.*', 'admin.profiles.*', 'profile.*'], $route)
            || $request->is('*subscription/*', 'subscription/*');
        if ($workspace->isDefault() || $personal) {
            return $next($request);
        }
        $account = $workspace->account;
        abort_unless($account && $account->is_active !== false, 403);
        $request->attributes->set('workspace_actor', $actor);
        Auth::guard('web')->setUser($account);
        try {
            return $next($request);
        } finally {
            Auth::guard('web')->setUser($actor);
        }
    }
}
