<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\DashboardService;
use App\Services\TeamDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(Request $request, DashboardService $dashboards, \App\Services\WorkspaceOverviewService $overview, TeamDashboardService $team): View
    {
        $user = $request->user();
        $dashboards->viewName($user);
        if ($user->isTeamMember()) {
            return view('admin.overview-team', $team->data($user));
        }
        $days = (int) $request->query('days', 30);
        abort_unless(in_array($days, [7, 30, 90], true), 422);

        return view('admin.overview', $overview->data($user, $days));
    }

	public function dashboard(Request $request, DashboardService $dashboards, TeamDashboardService $metrics): View
	{
		$user = $request->user();
		$view = $dashboards->viewName($user);

		if ($user->isTeamMember()) {
			return view($view, $metrics->data($user));
		}

		// Distinct connected platforms only - the "Connect Social Media"
		// card just needs to know whether at least one account exists per
		// platform (to show "Connected" vs a connect button), not the full
		// SocialAccount rows themselves.
		$connectedPlatforms = SocialAccount::where('user_id', $user->id)
			->where('is_token_valid', true)
			->distinct()
			->pluck('platform')
			->all();

		return view($view, compact('connectedPlatforms'));
	}
}
