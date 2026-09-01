<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Http\Request;

/**
 * Unified "connect a social account" entry point covering the four
 * platforms where posting, messaging, and ads scopes can genuinely be
 * requested in one consent screen - see SocialAuthService for why only
 * these four, and where every other platform's existing connect flow
 * (PostAccountController, MessageChannelController, the AdServices
 * classes) still lives unchanged.
 */
class SocialAccountController extends Controller
{
    public function __construct(private SocialAuthService $service)
    {
    }

    public function redirect(string $platform, Request $request)
    {
        if (!$this->service->isSupported($platform)) {
            abort(404);
        }

        // Only the chats-dashboard Manage Channels modal's Meta Messenger
        // tile appends ?return_to=dashboard - every other caller of this
        // shared route (Posts dashboard's Add Account modal, the full
        // admin.chats.channels page) gets the unchanged default landing
        // page, same as before this existed. See SocialAuthService's
        // returnRoute().
        session(['social_oauth_return_to' => $request->query('return_to') === 'dashboard' ? 'dashboard' : null]);

        return $this->service->redirect($platform);
    }

    public function callback(string $platform, Request $request)
    {
        if (!$this->service->isSupported($platform)) {
            abort(404);
        }

        return $this->service->callback(
            $platform,
            (string) $request->query('code'),
            $request->query('state'),
            $request->query('code_verifier')
        );
    }
}
