<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function dashboard(Request $request, DashboardService $dashboards, \App\Services\TeamDashboardService $metrics): View
    {
        return view($dashboards->viewName($request->user()), $request->user()->isTeamMember() ? $metrics->data($request->user()) : []);
    }
}
