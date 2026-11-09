<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Workspace accounts belong to their owner and are never signed into directly.
        if ($request->user()->isWorkspaceAccount()) {
            Auth::guard('web')->logout();
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $request->session()->regenerate();

        $request->session()->forget(['url.intended', 'seller_workspace_member', \App\Services\WorkspaceService::SESSION_KEY]);

        // Several brands or clients: choose where to work first.
        if (app(\App\Services\WorkspaceService::class)->needsPicker($request->user())) {
            return redirect()->route('seller-workspaces.index');
        }

        return redirect(app(\App\Services\DashboardService::class)->url($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
