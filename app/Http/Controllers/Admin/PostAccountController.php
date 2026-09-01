<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\PostServices\ApiPostService;
use App\Services\PostServices\InstagramPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Connect flows for the posting-only platforms: WhatsApp (manual entry),
 * Threads, Pinterest, X, and Instagram (standalone Instagram Login).
 * Facebook, Google, LinkedIn, and TikTok connect through
 * SocialAccountController instead (admin.social-accounts.redirect) - see
 * SocialAuthService - since their OAuth model supports requesting
 * posting + ads + messaging scopes together in one redirect.
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

        SocialAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'platform_account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'                    => $validated['name'],
                'username'                => $data['display_phone_number'] ?? null,
                'access_token'            => $validated['access_token'],
                'is_token_valid'          => true,
                'has_posting_permission'  => true,
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

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'platform_account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'                   => $validated['business_name'] ?: ($data['verified_name'] ?? 'WhatsApp Business'),
                'username'               => $data['display_phone_number'] ?? null,
                'access_token'           => $accessToken,
                'is_token_valid'         => true,
                'has_posting_permission' => true,
                'metadata'               => ['settings' => ['waba_id' => $validated['waba_id']]],
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

        SocialAccount::updateOrCreate(
            ['platform' => 'threads', 'platform_account_id' => $threadsUserId, 'user_id' => Auth::id()],
            [
                'name'                   => $profileData['username'] ?? 'Threads Account',
                'username'               => $profileData['username'] ?? null,
                'avatar_url'             => $profileData['threads_profile_picture_url'] ?? null,
                'access_token'           => $accessToken,
                'expires_at'             => Carbon::now()->addSeconds($expiresIn),
                'is_token_valid'         => true,
                'has_posting_permission' => true,
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'Threads account connected.');
    }

    private function threadsCallbackUrl(): string
    {
        // oauthCallbackUrl() (see app/Helpers/Helper.php) reverse-resolves
        // from routes/web.php itself rather than a hand-typed path string -
        // config('services.app_url') is a separate, misconfigured value
        // (pointed at an unrelated domain) that would build a redirect_uri
        // Threads/Meta would reject as not matching what's registered, and
        // strips the locale prefix a bare route() call would otherwise add
        // (this route lives inside the LaravelLocalization group). Same
        // reasoning applied across every callback URL in Ads/Posting/
        // Messaging.
        return oauthCallbackUrl('admin.post-accounts.threads.callback');
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

        SocialAccount::updateOrCreate(
            ['platform' => 'pinterest', 'platform_account_id' => $profileData['username'] ?? $token['access_token'], 'user_id' => Auth::id()],
            [
                'name'                   => $profileData['username'] ?? 'Pinterest Account',
                'username'               => $profileData['username'] ?? null,
                'avatar_url'             => $profileData['profile_image'] ?? null,
                'followers_count'        => $profileData['follower_count'] ?? null,
                'following_count'        => $profileData['following_count'] ?? null,
                'views_count'            => $profileData['monthly_views'] ?? null,
                'media_count'            => $profileData['pin_count'] ?? null,
                'access_token'           => $token['access_token'],
                'refresh_token'          => $token['refresh_token'] ?? null,
                'expires_at'             => Carbon::now()->addSeconds($token['expires_in'] ?? 2592000),
                'is_token_valid'         => true,
                'has_posting_permission' => true,
                'metadata'               => ['settings' => ['board_id' => $boardId]],
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
        return oauthCallbackUrl('admin.post-accounts.pinterest.callback');
    }

    /**
     * X (Twitter) - OAuth 2.0 Authorization Code + PKCE, the same auth
     * model already used for X DMs in the Messaging module (see
     * XMessagingService::redirect()), but under its own
     * posts.x.client_id/secret (XPostService reads that namespace, not
     * messaging.x.*) and posting scopes rather than DM ones. X has no
     * "Pages" concept - a connected account always posts as the
     * authenticated user themselves, so this always creates exactly one
     * post_accounts row per connection, keyed by that user's own numeric
     * X id.
     */
    public function redirectX()
    {
        $codeVerifier = Str::random(64);
        session(['x_posts_code_verifier' => $codeVerifier]);

        $state = Str::uuid()->toString();
        session(['x_posts_oauth_state' => $state]);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $url = 'https://x.com/i/oauth2/authorize?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => adminSetting('posts.x.client_id'),
            'redirect_uri'          => $this->xCallbackUrl(),
            'scope'                 => 'tweet.read tweet.write users.read offline.access',
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return Redirect::away($url);
    }

    public function callbackX(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('x_posts_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'X connection failed or was cancelled.');
        }

        $codeVerifier = session('x_posts_code_verifier');

        if (!$codeVerifier) {
            return redirect()->route('admin.posts.create')->with('error', 'Missing PKCE code verifier - please restart the connection flow.');
        }

        $tokenResponse = $api->request('post', 'https://api.x.com/2/oauth2/token', [], [
            'grant_type'    => 'authorization_code',
            'code'          => $request->query('code'),
            'client_id'     => adminSetting('posts.x.client_id'),
            'redirect_uri'  => $this->xCallbackUrl(),
            'code_verifier' => $codeVerifier,
        ], 'form');

        session()->forget(['x_posts_code_verifier', 'x_posts_oauth_state']);

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error_description'] ?? 'Failed to exchange code for an X access token.');
        }

        $token = $tokenResponse->json();
        $baseUrl = adminSetting('posts.x.base_url') ?: 'https://api.x.com/2/';

        $userResponse = $api->request('get', $baseUrl . 'users/me', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ], ['user.fields' => 'profile_image_url,username,name,public_metrics']);

        if (!$userResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected, but failed to fetch the X account profile.');
        }

        $user = $userResponse->json()['data'];
        $metrics = $user['public_metrics'] ?? [];

        SocialAccount::updateOrCreate(
            ['platform' => 'x', 'platform_account_id' => $user['id'], 'user_id' => Auth::id()],
            [
                'name'                   => $user['name'] ?? $user['username'],
                'username'               => $user['username'] ?? null,
                'avatar_url'             => $user['profile_image_url'] ?? null,
                'followers_count'        => $metrics['followers_count'] ?? null,
                'following_count'        => $metrics['following_count'] ?? null,
                'media_count'            => $metrics['tweet_count'] ?? null,
                'access_token'           => $token['access_token'],
                'refresh_token'          => $token['refresh_token'] ?? null,
                'expires_at'             => Carbon::now()->addSeconds($token['expires_in'] ?? 7200),
                'is_token_valid'         => true,
                'has_posting_permission' => true,
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'X account connected.');
    }

    private function xCallbackUrl(): string
    {
        return oauthCallbackUrl('admin.post-accounts.x.callback');
    }

    public function redirectInstagram()
    {
        $state = Str::uuid()->toString();
        session(['instagram_oauth_state' => $state]);

        $url = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'force_reauth' =>true,
            'response_type' => 'code',
            'client_id'     => adminSetting('posts.instagram.client_id'),
            'redirect_uri'  => $this->instagramCallbackUrl(),
            'state'         => $state,
            'scope'        => 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments,instagram_business_content_publish,instagram_business_manage_insights',
        ]);

        return Redirect::away($url);
    }

    public function callbackInstagram(Request $request, ApiPostService $api, InstagramPostService $instagramPostService)
    {
        // 1. Validate State and Authorization Code
        if (!$request->filled('code') || $request->query('state') !== session('instagram_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'Instagram connection failed or state mismatched.');
        }

        session()->forget('instagram_oauth_state');

        $clientId     = adminSetting('posts.instagram.client_id');
        $clientSecret = adminSetting('posts.instagram.client_secret');
        $redirectUri  = $this->instagramCallbackUrl();

        // 2. Exchange authorization code for a short-lived access token
        $tokenResponse = $api->request('post', 'https://api.instagram.com/oauth/access_token', [], [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
            'code'          => $request->query('code'),
        ], 'form');

        if (!$tokenResponse->successful()) {
            $errorMsg = $tokenResponse->json()['error_message'] ?? 'Failed to obtain access token from Instagram.';
            return redirect()->route('admin.posts.create')->with('error', $errorMsg);
        }

        $tokenData   = $tokenResponse->json();
        $shortToken  = $tokenData['access_token'] ?? null;
        $igUserId    = $tokenData['user_id'] ?? null;

        if (!$shortToken) {
            return redirect()->route('admin.posts.create')->with('error', 'Invalid token response received from Instagram.');
        }

        // 3. Exchange short-lived token for a 60-day long-lived access token
        $longLivedResponse = $api->request('get', 'https://graph.instagram.com/access_token', [], [
            'grant_type'    => 'ig_exchange_token',
            'client_secret' => $clientSecret,
            'access_token'  => $shortToken,
        ]);

        $accessToken = $shortToken;
        $expiresIn   = 5184000; // Default: 60 days in seconds

        if ($longLivedResponse->successful()) {
            $longLivedData = $longLivedResponse->json();
            $accessToken   = $longLivedData['access_token'] ?? $accessToken;
            $expiresIn     = $longLivedData['expires_in'] ?? $expiresIn;
        }

        // 4. Fetch Connected Instagram Business / Creator Account Profile
        $userResponse = $api->request('get', "https://graph.instagram.com/v20.0/me", [], [
            'fields'       => 'id,username,name,profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if (!$userResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected to Instagram, but failed to fetch profile details.');
        }

        $igUser = $userResponse->json();
        $accId  = $igUser['id'] ?? $igUserId;

        // 5. Store / Update SocialAccount Record
        $instagramAccount = SocialAccount::updateOrCreate(
            [
                'platform'             => 'instagram',
                'platform_account_id'  => $accId,
                'user_id'              => Auth::id(),
            ],
            [
                'name'                   => $igUser['name'] ?? $igUser['username'] ?? 'Instagram Business',
                'username'               => $igUser['username'] ?? null,
                // Bug fix: this used to write an 'avatar' key, which isn't
                // a real column on the old PostAccount model's fillable
                // (nor is it 'avatar_url'/'image'), so it was silently
                // dropped by mass-assignment protection and the standalone
                // Instagram Login flow never actually persisted an avatar.
                // Now correctly targets avatar_url.
                'avatar_url'             => $igUser['profile_picture_url'] ?? null,
                'access_token'           => $accessToken,
                'expires_at'             => Carbon::now()->addSeconds($expiresIn),
                'is_token_valid'         => true,
                'has_posting_permission' => true,
                // Tags this account as a standalone Instagram Login token
                // (graph.instagram.com), distinct from callbackMeta()'s
                // Facebook Page tokens (graph.facebook.com) - see
                // InstagramPostService::resolveBaseUrl().
                'metadata'               => ['settings' => ['auth_type' => 'instagram_login']],
            ]
        );

        // Each of these three is independently failure-tolerant - this
        // outer try/catch is a deliberate second safety net so the
        // account above stays saved even if a stats/subscribe/backfill
        // call fails (eg. too small for Insights, or subscribed_apps
        // rejecting the standalone Instagram Login token's permission
        // set - a newer, less battle-tested API surface than the
        // Page-linked flow).
        try {
            $instagramPostService->syncAccountStats($instagramAccount);
        } catch (\Throwable $e) {
            Log::warning('Instagram stats sync failed after connect.', ['account_id' => $instagramAccount->id, 'error' => $e->getMessage()]);
        }
        try {
            $instagramPostService->subscribeToWebhooks($instagramAccount);
        } catch (\Throwable $e) {
            Log::warning('Instagram webhook subscribe failed after connect.', ['account_id' => $instagramAccount->id, 'error' => $e->getMessage()]);
        }
        try {
            $instagramPostService->backfillRecentPosts($instagramAccount);
        } catch (\Throwable $e) {
            Log::warning('Instagram post backfill failed after connect.', ['account_id' => $instagramAccount->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('admin.posts.create')->with('success', "Successfully connected Instagram account (@{$igUser['username']}).");
    }

    private function instagramCallbackUrl(): string
    {
        return oauthCallbackUrl('admin.post-accounts.instagram.callback');
    }

    public function destroy(SocialAccount $account)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $account->delete();

        return back()->with('success', 'Account disconnected.');
    }
}
