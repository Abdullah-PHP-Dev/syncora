<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
	public function handle(Request $request, Closure $next)
	{
		$user = auth()->user();


		// Adjust this if your seller/company relation is different
		$seller = $user?->seller ?? $user;

		if (!$seller) {
			return redirect('/login');
		}

		$bundle = $seller->bundle;
		// seller hasOne SellerBundle


		if (!$bundle) {
			return redirect('/admin/subscription/select')
				->with('error', 'Please choose a subscription plan');
		}


		if ($bundle->status !== 'active') {
			return redirect('/subscription/select')
				->with('error', 'Your subscription is not active');
		}


		if ($bundle->expires_at && now()->gt($bundle->expires_at)) {
			return redirect('/subscription/select')
				->with('error', 'Your subscription has expired');
		}

		return $next($request);
	}
}