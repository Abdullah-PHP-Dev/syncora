<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        // New sellers continue onboarding: connect a social account first (EnsureConnectedAccount).
        $next = $user->hasRole('seller') && \Illuminate\Support\Facades\Route::has('admin.connections.index')
            ? route('admin.connections.index')
            : app(\App\Services\DashboardService::class)->url($user);

        return redirect()->intended($next)->with('connect_notice', [
            'type' => 'success',
            'title' => __('Email verified'),
            'message' => __('Your account is ready. Connect your first social account to get started.'),
        ]);
    }
}
