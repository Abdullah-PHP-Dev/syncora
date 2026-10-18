<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Posts, Inbox and Comments need at least one eligible connected social
 * account (SocialAccount::scopeEligible) - without one the seller is sent
 * to the Connection Hub. Runs after 'subscription' / 'package.feature', so
 * an unsubscribed seller still lands on the plan picker first. The Hub
 * itself is never gated, so this can't loop.
 */
class EnsureConnectedAccount
{
    public function handle(Request $request, Closure $next)
    {
        // Inside a seller workspace the owner (UseSellerWorkspace has
        // already swapped them in) is the one whose accounts count.
        $user = $request->user();

        if (! $user || $user->hasEligibleConnection()) {
            return $next($request);
        }

        $message = __('connections.gate.required');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => 'connection_required', 'connect_url' => route('admin.connections.index')], 403);
        }

        // A team member can't open the Hub (UseSellerWorkspace blocks it),
        // so send them to their workspaces list instead.
        if ($request->attributes->has('workspace_member')) {
            return redirect()->route('seller-workspaces.index')->with('error', __('connections.gate.workspace'));
        }

        return redirect()->route('admin.connections.index')->with('error', $message);
    }
}
