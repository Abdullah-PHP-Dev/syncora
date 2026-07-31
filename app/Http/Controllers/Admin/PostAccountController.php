<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostAccount;
use App\Services\PostServices\ApiPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * No platform in the Posts module currently has a working "connect an
 * account" flow (post_accounts rows aren't created anywhere in this
 * codebase - confirmed by searching for PostAccount::create/
 * updateOrCreate before writing this). Retrofitting that for the existing
 * seven platforms is out of scope here; this adds just enough for
 * WhatsApp and Threads specifically, since without it there's no way to
 * create the post_accounts rows those services need at all.
 */
class PostAccountController extends Controller
{
    /**
     * Verifies the phone number ID/token live against the Graph API
     * before saving, same pattern as the Messaging module's WhatsApp
     * connect flow (MessageChannelController::storeWhatsApp).
     */
    public function storeWhatsApp(Request $request, ApiPostService $api)
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'phone_number_id' => ['required', 'string'],
            'access_token'    => ['required', 'string'],
        ]);

        $baseUrl = adminSetting('posts.whatsapp.base_url') ?: 'https://graph.facebook.com/v21.0/';

        $check = $api->request(
            'get',
            $baseUrl . $validated['phone_number_id'],
            ['Authorization' => 'Bearer ' . $validated['access_token']],
            ['fields' => 'display_phone_number,verified_name']
        );

        if (!$check->successful()) {
            return back()->withErrors(['phone_number_id' => 'Could not verify this phone number ID/token with Meta.'])->withInput();
        }

        $data = $check->json();

        PostAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'         => $validated['name'],
                'username'     => $data['display_phone_number'] ?? null,
                'access_token' => $validated['access_token'],
                'is_active'    => true,
                'status'       => 'active',
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'WhatsApp number connected - it now appears as a channel in the composer.');
    }

    /**
     * WhatsApp Embedded Signup - the "connect with Facebook" alternative
     * to storeWhatsApp()'s manual entry. Unlike the OAuth redirects used
     * everywhere else in this app, Embedded Signup is a Facebook JS SDK
     * popup flow (FB.login() with a config_id configured in the Meta App
     * dashboard, under WhatsApp > Embedded Signup) - the browser never
     * navigates to a callback URL; instead the WABA ID, phone_number_id,
     * and an exchangeable authorization code arrive via postMessage to
     * the page that opened the popup (see the JS in posts/create.blade.php),
     * which then AJAX-POSTs them here.
     *
     * Requires messaging.meta.app_id/app_secret (shared with the
     * Messaging module's Meta connection) and messaging.meta.
     * whatsapp_config_id (created manually in Meta's App Dashboard - not
     * something obtainable via API, same category of prerequisite as the
     * ad campaign module's developer_token).
     */
    public function storeWhatsappEmbedded(Request $request, ApiPostService $api)
    {
        $validated = $request->validate([
            'code'            => ['required', 'string'],
            'phone_number_id' => ['required', 'string'],
            'waba_id'         => ['required', 'string'],
            'business_name'   => ['nullable', 'string', 'max:255'],
        ]);

        $baseUrl = adminSetting('posts.whatsapp.base_url') ?: 'https://graph.facebook.com/v21.0/';

        // Embedded Signup's code exchange doesn't use a redirect_uri the
        // way a browser-navigation OAuth callback does - the code was
        // never attached to a redirect in the first place, it came back
        // via postMessage inside the same page.
        $tokenResponse = $api->request('get', $baseUrl . 'oauth/access_token', [], [
            'client_id'     => adminSetting('messaging.meta.app_id'),
            'client_secret' => adminSetting('messaging.meta.app_secret'),
            'code'          => $validated['code'],
        ]);

        if (!$tokenResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => $tokenResponse->json()['error']['message'] ?? 'Failed to exchange the Embedded Signup code for an access token.',
            ], 422);
        }

        $accessToken = $tokenResponse->json()['access_token'];

        $check = $api->request(
            'get',
            $baseUrl . $validated['phone_number_id'],
            ['Authorization' => 'Bearer ' . $accessToken],
            ['fields' => 'display_phone_number,verified_name']
        );

        if (!$check->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Connected, but could not verify the phone number that Embedded Signup returned.',
            ], 422);
        }

        $data = $check->json();

        $account = PostAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'         => $validated['business_name'] ?: ($data['verified_name'] ?? 'WhatsApp Business'),
                'username'     => $data['display_phone_number'] ?? null,
                'access_token' => $accessToken,
                'is_active'    => true,
                'status'       => 'active',
                'settings'     => ['waba_id' => $validated['waba_id']],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp number connected via Facebook.',
            'account' => $account,
        ]);
    }

    /**
     * Threads Login - a standard server-side OAuth redirect (unlike
     * WhatsApp's popup-based Embedded Signup above), using Threads' own
     * threads.net/graph.threads.net endpoints rather than the regular
     * Facebook Graph API this app's other Meta connections use. Requires
     * a separate Threads App ID/Secret (posts.threads.client_id/secret) -
     * Threads apps get their own App ID shown under Meta App Dashboard >
     * Threads > API Setup, distinct from the main Facebook App ID used
     * for Messenger/Instagram/WhatsApp.
     */
    public function redirectThreads()
    {
        $state = Str::uuid()->toString();
        session(['threads_oauth_state' => $state]);

        $url = adminSetting('posts.threads.auth_url') . '?' . http_build_query([
            'client_id'     => adminSetting('posts.threads.client_id'),
            'redirect_uri'  => $this->threadsCallbackUrl(),
            'scope'         => 'threads_basic,threads_content_publish',
            'response_type' => 'code',
            'state'         => $state,
        ]);

        return Redirect::away($url);
    }

    public function callbackThreads(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('threads_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'Threads connection failed or was cancelled.');
        }

        $tokenResponse = $api->request('post', adminSetting('posts.threads.token_url'), [], [
            'client_id'     => adminSetting('posts.threads.client_id'),
            'client_secret' => adminSetting('posts.threads.client_secret'),
            'code'          => $request->query('code'),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->threadsCallbackUrl(),
        ], 'form');

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error_message'] ?? 'Failed to connect Threads.');
        }

        $shortLived = $tokenResponse->json();

        // Threads user_id comes back directly in the short-lived exchange
        // response - no separate profile-lookup call needed just to get it.
        $threadsUserId = $shortLived['user_id'];

        $longLivedResponse = $api->request('get', 'https://graph.threads.net/access_token', [], [
            'grant_type'    => 'th_exchange_token',
            'client_secret' => adminSetting('posts.threads.client_secret'),
            'access_token'  => $shortLived['access_token'],
        ]);

        $accessToken = $longLivedResponse->successful() ? $longLivedResponse->json()['access_token'] : $shortLived['access_token'];
        $expiresIn = $longLivedResponse->successful() ? ($longLivedResponse->json()['expires_in'] ?? 5184000) : 3600;

        $profile = $api->request('get', "https://graph.threads.net/v1.0/{$threadsUserId}", [], [
            'fields'       => 'username,threads_profile_picture_url',
            'access_token' => $accessToken,
        ]);

        $profileData = $profile->successful() ? $profile->json() : [];

        PostAccount::updateOrCreate(
            ['platform' => 'threads', 'account_id' => $threadsUserId, 'user_id' => Auth::id()],
            [
                'name'         => $profileData['username'] ?? 'Threads Account',
                'username'     => $profileData['username'] ?? null,
                'image'        => $profileData['threads_profile_picture_url'] ?? null,
                'access_token' => $accessToken,
                'expires_in'   => Carbon::now()->addSeconds($expiresIn),
                'is_active'    => true,
                'status'       => 'active',
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'Threads account connected.');
    }

    private function threadsCallbackUrl(): string
    {
        return config('services.app_url') . '/admin/post-accounts/threads/callback';
    }

    /**
     * Pinterest OAuth - a standard server-side redirect like Threads, but
     * every Pin requires a board_id (Pinterest has no plain profile feed
     * to post to) - see PinterestPostService's class docblock for why a
     * default board is resolved/created here rather than adding a
     * board-picker to the shared composer.
     */
    public function redirectPinterest()
    {
        $state = Str::uuid()->toString();
        session(['pinterest_oauth_state' => $state]);

        $url = adminSetting('posts.pinterest.auth_url') . '?' . http_build_query([
            'client_id'     => adminSetting('posts.pinterest.client_id'),
            'redirect_uri'  => $this->pinterestCallbackUrl(),
            'response_type' => 'code',
            'scope'         => 'boards:read,boards:write,pins:read,pins:write,user_accounts:read',
            'state'         => $state,
        ]);

        return Redirect::away($url);
    }

    public function callbackPinterest(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('pinterest_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'Pinterest connection failed or was cancelled.');
        }

        $credentials = base64_encode(adminSetting('posts.pinterest.client_id') . ':' . adminSetting('posts.pinterest.client_secret'));

        $tokenResponse = $api->request('post', adminSetting('posts.pinterest.token_url'), [
            'Authorization' => "Basic {$credentials}",
        ], [
            'grant_type'   => 'authorization_code',
            'code'         => $request->query('code'),
            'redirect_uri' => $this->pinterestCallbackUrl(),
        ], 'form');

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['message'] ?? 'Failed to connect Pinterest.');
        }

        $token = $tokenResponse->json();
        $baseUrl = adminSetting('posts.pinterest.base_url') ?: 'https://api.pinterest.com/v5/';

        $profile = $api->request('get', $baseUrl . 'user_account', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ]);

        $profileData = $profile->successful() ? $profile->json() : [];

        $boardId = $this->resolveDefaultPinterestBoard($api, $baseUrl, $token['access_token'], $profileData['username'] ?? null);

        if (!$boardId) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected to Pinterest, but no board could be found or created for posting.');
        }

        PostAccount::updateOrCreate(
            ['platform' => 'pinterest', 'account_id' => $profileData['username'] ?? $token['access_token'], 'user_id' => Auth::id()],
            [
                'name'          => $profileData['username'] ?? 'Pinterest Account',
                'username'      => $profileData['username'] ?? null,
                'image'         => $profileData['profile_image'] ?? null,
                'access_token'  => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in'    => Carbon::now()->addSeconds($token['expires_in'] ?? 2592000),
                'is_active'     => true,
                'status'        => 'active',
                'settings'      => ['board_id' => $boardId],
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'Pinterest account connected.');
    }

    /**
     * Reuses the account's first existing board if it has one, otherwise
     * creates a dedicated board for posts made through this app - either
     * way every Pin created afterwards needs this ID.
     */
    private function resolveDefaultPinterestBoard(ApiPostService $api, string $baseUrl, string $accessToken, ?string $username): ?string
    {
        $boards = $api->request('get', $baseUrl . 'boards', ['Authorization' => 'Bearer ' . $accessToken]);

        if ($boards->successful() && !empty($boards->json()['items'])) {
            return $boards->json()['items'][0]['id'];
        }

        $created = $api->request('post', $baseUrl . 'boards', ['Authorization' => 'Bearer ' . $accessToken], [
            'name'        => config('app.name') . ' Posts',
            'description' => 'Pins published from ' . config('app.name'),
        ]);

        return $created->successful() ? ($created->json()['id'] ?? null) : null;
    }

    private function pinterestCallbackUrl(): string
    {
        return config('services.app_url') . '/admin/post-accounts/pinterest/callback';
    }

    public function destroy(PostAccount $account)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $account->delete();

        return back()->with('success', 'Account disconnected.');
    }
}
