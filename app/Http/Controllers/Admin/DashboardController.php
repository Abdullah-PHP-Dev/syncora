<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
	public function dashboard()
	{
		// Distinct connected platforms only - the "Connect Social Media"
		// card just needs to know whether at least one account exists per
		// platform (to show "Connected" vs a connect button), not the full
		// SocialAccount rows themselves.
		$connectedPlatforms = SocialAccount::where('user_id', Auth::id())
			->where('is_token_valid', true)
			->distinct()
			->pluck('platform')
			->all();

		return view('admin.dashboard', compact('connectedPlatforms'));
	}
}
