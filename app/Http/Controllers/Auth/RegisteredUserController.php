<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Onboarding\FreePlanUnavailable;
use App\Services\Onboarding\SellerRegistrationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the two-step registration wizard (organization, then account).
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, SellerRegistrationService $registration): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            // Same limit as the business profile's business_name.
            'organization_name' => ['required', 'string', 'min:2', 'max:150', 'not_regex:/[<>]/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        try {
            $user = $registration->register($data['organization_name'], $data['email'], $data['password']);
        } catch (FreePlanUnavailable $e) {
            Log::critical($e->getMessage(), ['email_domain' => Str::after($data['email'], '@')]);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['registration' => __('onboarding.errors.unavailable')]);
        } catch (UniqueConstraintViolationException) {
            // Two submissions raced past the unique rule; the transaction rolled back.
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => __('validation.unique', ['attribute' => __('onboarding.fields.email')])]);
        }

        event(new UserRegistered($user));

        Auth::login($user);
        $request->session()->regenerate();
        // Always land in the new account's own (default) workspace.
        $request->session()->forget('seller_workspace_member');

        // Onboarding: connect a social account first - Posts, Inbox and
        // Comments stay locked until one is (EnsureConnectedAccount).
        return redirect()->route('admin.connections.index')->with('success', __('onboarding.welcome', [
            'organization' => $user->name,
            'plan' => $user->subscription?->bundle?->name ?? '',
        ]));
    }
}
