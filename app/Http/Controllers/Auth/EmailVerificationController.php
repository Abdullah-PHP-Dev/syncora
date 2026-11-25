<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * The verification screen (six-digit code) and the magic-link confirmation page.
 * Every result is idempotent: verifying twice, from two tabs or two devices, is harmless.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private EmailVerification $verification) {}

    /** The six-digit code screen. JSON: polled so a link confirmed elsewhere continues here. */
    public function show(Request $request): View|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $next = $this->verification->destination($user);
        if ($request->expectsJson()) {
            return response()->json(['verified' => $user->hasVerifiedEmail(), 'redirect' => $next]);
        }
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($next);
        }

        return view('auth.verify-email', ['props' => [
            'maskedEmail' => EmailVerification::mask($user->email),
            'codeMinutes' => EmailVerification::CODE_MINUTES,
            'resendWait' => $this->verification->resendWait($user),
            'justRegistered' => (bool) session('just_registered'),
            'emailFailed' => (bool) session('verification_email_failed'),
            'urls' => [
                'verify' => route('verification.code'),
                'resend' => route('verification.send'),
                'change' => route('verification.change-email'),
                'status' => route('verification.notice'),
                'logout' => route('logout'),
                'support' => route('contact'),
            ],
            'locales' => $this->localeLinks(),
            'locale' => app()->getLocale(),
            'csrf' => csrf_token(),
            'labels' => trans('verification.screen'),
        ]]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $code = preg_replace('/\D/', '', $data['code']);
        $user = $request->user();
        $result = $this->verification->verifyCode($user, $code);

        return match ($result) {
            EmailVerification::VERIFIED, EmailVerification::ALREADY => response()->json([
                'verified' => true, 'message' => __('verification.screen.success'), 'redirect' => $this->verification->destination($user),
            ]),
            default => response()->json(['verified' => false, 'error' => $result, 'message' => __('verification.errors.'.$result)], 422),
        };
    }

    /** Sends a new code and link (the previous ones stop working). 60-second cooldown. */
    public function resend(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['verified' => true, 'redirect' => $this->verification->destination($user)])
                : redirect()->intended($this->verification->destination($user));
        }
        if ($wait = $this->verification->resendWait($user)) {
            return $this->respond($request, false, __('verification.errors.cooldown', ['seconds' => $wait]), ['wait' => $wait], 429);
        }
        $sent = $this->verification->send($user);

        return $sent
            ? $this->respond($request, true, __('verification.screen.resent'), ['wait' => EmailVerification::RESEND_SECONDS])
            : $this->respond($request, false, __('verification.errors.send_failed'), ['wait' => 0], 503);
    }

    /** Fixes a mistyped address before verifying (only while unverified). */
    public function changeEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('verification.errors.already')], 422);
        }
        $data = $request->validate(['email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)]]);
        if ($data['email'] !== $user->email) {
            $user->forceFill(['email' => $data['email'], 'email_verified_at' => null])->save();
        }
        $sent = $this->verification->send($user);

        return response()->json([
            'ok' => $sent, 'maskedEmail' => EmailVerification::mask($user->email), 'wait' => $sent ? EmailVerification::RESEND_SECONDS : 0,
            'message' => $sent ? __('verification.screen.changed') : __('verification.errors.send_failed'),
        ], $sent ? 200 : 503);
    }

    /** The email's link. Opening it never verifies (scanners open links): it asks to confirm. */
    public function showLink(Request $request, string $token): View
    {
        [$state, $user] = $this->verification->inspectLink($token);

        return view('auth.verify-link', $this->linkView($request, $state === EmailVerification::VERIFIED ? 'confirm' : $state, $token, $user));
    }

    public function confirmLink(Request $request, string $token): View|RedirectResponse
    {
        [$state, $user] = $this->verification->confirmLink($token);
        if (in_array($state, [EmailVerification::VERIFIED, EmailVerification::ALREADY], true) && $user && Auth::id() === $user->id) {
            return redirect()->to($this->verification->destination($user))->with('connect_notice', [
                'type' => 'success', 'title' => __('verification.screen.success'), 'message' => __('verification.link.continue_hint'),
            ]);
        }

        return view('auth.verify-link', $this->linkView($request, $state === EmailVerification::VERIFIED ? 'done' : $state, $token, $user));
    }

    private function linkView(Request $request, string $state, string $token, ?User $user): array
    {
        $signedInAsOwner = $user && Auth::id() === $user->id;

        return [
            'state' => $state, 'token' => $token,
            'maskedEmail' => $user ? EmailVerification::mask($user->email) : null,
            // Where to go next: the app when signed in as this person, else sign in.
            'next' => $signedInAsOwner ? $this->verification->destination($user) : route('login'),
            'retry' => Auth::check() ? route('verification.notice') : route('login'),
            'signedIn' => $signedInAsOwner,
            'locales' => $this->localeLinks(),
        ];
    }

    private function respond(Request $request, bool $ok, string $message, array $extra = [], int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message] + $extra, $status);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }

    private function localeLinks(): array
    {
        $links = [];
        foreach (['en' => 'English', 'ar' => 'العربية'] as $code => $name) {
            $links[] = ['code' => $code, 'name' => $name, 'url' => rescue(fn () => LaravelLocalization::getLocalizedURL($code, null, [], true), url()->current(), false)];
        }

        return $links;
    }
}
