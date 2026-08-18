<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
	public function handle(Request $request, Closure $next)
	{
		$user = auth()->user();

		if (! $user || ! $user->hasRole('seller')) {
			abort(403);
		}


		if (!$user->hasActiveSubscription()) {
			return redirect('/admin/subscription/select')
				->with('error', 'Please choose a subscription plan');
		}


		return $next($request);
	}
}
