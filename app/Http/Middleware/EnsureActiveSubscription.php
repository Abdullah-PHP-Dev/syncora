<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
	public function handle(Request $request, Closure $next)
	{
		$user = auth()->user();



		if (! $user || ! $user->hasRole('seller') || $user->isTeamMember()) {
			abort(403);
		}


		if (!$user->hasActiveSubscription()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('package_access.expired'), 'code' => 'subscription_required', 'upgrade_url' => route('admin.subscription.select')], 403);
            }
			return redirect()->route('admin.subscription.select')
				->with('error', 'Please choose a subscription plan');
		}


		return $next($request);
	}
}
