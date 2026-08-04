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
 * Most platforms in the Posts module still have no working "connect an
 * account" flow (post_accounts rows aren't created anywhere for them -
 * confirmed by searching for PostAccount::create/updateOrCreate before
 * writing the first of these). This currently covers WhatsApp, Threads,
 * Pinterest, and Facebook/Instagram - X, LinkedIn, TikTok, and YouTube/
 * Google still have no connect flow.
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
     * Facebook Login for Business - a standard server-side OAuth redirect
     * that resolves every Page the user administers via /me/accounts, the
     * same call the Messaging module's Meta connection already makes (see
     * MetaMessagingTrait::handleMetaCallback()), but requesting posting
     * scopes (pages_manage_posts, instagram_content_publish) instead of
     * messaging ones. One Page can produce up to two post_accounts rows -
     * a Facebook one always, plus an Instagram one if that Page has a
     * linked Instagram professional account - both sharing the Page's own
     * access token, since Instagram Content Publishing authenticates
     * through the linked Page, not a separate IG-specific token.
     *
     * Reuses posts.facebook.client_id/client_secret/base_url - already
     * configured and used by MetaPostService/InstagramPostService's own
     * token-refresh calls, not a separate credential set from this app's
     * other Facebook App connections.
     */
    public function redirectMeta()
    {
        $state = Str::uuid()->toString();
        session(['meta_oauth_state' => $state]);

        $url = 'https://www.facebook.com/' . $this->metaGraphVersion() . '/dialog/oauth?' . http_build_query([
            'client_id'     => adminSetting('posts.facebook.client_id'),
            'redirect_uri'  => $this->metaCallbackUrl(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'pages_show_list,pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish',
        ]);

        return Redirect::away($url);
    }

    public function callbackMeta(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('meta_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'Facebook/Instagram connection failed or was cancelled.');
        }

        $baseUrl = $this->metaBaseUrl();
        $clientId = adminSetting('posts.facebook.client_id');
        $clientSecret = adminSetting('posts.facebook.client_secret');

        $tokenResponse = $api->request('get', $baseUrl . 'oauth/access_token', [], [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $this->metaCallbackUrl(),
            'code'          => $request->query('code'),
        ]);

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error']['message'] ?? 'Failed to exchange code for a Facebook access token.');
        }

        $shortLivedToken = $tokenResponse->json()['access_token'];

        // Exchanged for a long-lived user token (~60 days) before resolving
        // Pages, so the resulting Page access tokens inherit that longer
        // life too - same two-step exchange the Messaging module's Meta
        // connection already does, falling back to the short-lived token
        // if this second call fails rather than losing the connection
        // entirely over it.
        $longLivedResponse = $api->request('get', $baseUrl . 'oauth/access_token', [], [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $userToken = $longLivedResponse->successful() ? $longLivedResponse->json()['access_token'] : $shortLivedToken;

        $pagesResponse = $api->request('get', $baseUrl . 'me/accounts', [], [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token,picture,instagram_business_account{id,username,profile_picture_url}',
        ]);

        if (!$pagesResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $pagesResponse->json()['error']['message'] ?? 'Failed to fetch Facebook Pages.');
        }

        $created = ['facebook' => 0, 'instagram' => 0];
        // Page access tokens derived this way don't have Meta's usual
        // short expiry, but ensureValidToken() still needs a concrete
        // future timestamp to compare against - 60 days mirrors the long-
        // lived user token's own lifetime and keeps its periodic
        // fb_exchange_token refresh call harmless or a no-op either way.
        $expiresAt = Carbon::now()->addDays(60);

        foreach ($pagesResponse->json()['data'] ?? [] as $page) {
            PostAccount::updateOrCreate(
                ['platform' => 'facebook', 'account_id' => $page['id'], 'user_id' => Auth::id()],
                [
                    'name'         => $page['name'],
                    'username'     => $page['name'],
                    'image'        => $page['picture']['data']['url'] ?? null,
                    'access_token' => $page['access_token'],
                    'expires_in'   => $expiresAt,
                    'is_active'    => true,
                    'status'       => 'active',
                ]
            );
            $created['facebook']++;

            if (!empty($page['instagram_business_account']['id'])) {
                $ig = $page['instagram_business_account'];

                PostAccount::updateOrCreate(
                    ['platform' => 'instagram', 'account_id' => $ig['id'], 'user_id' => Auth::id()],
                    [
                        'name'         => $ig['username'] ?? $page['name'],
                        'username'     => $ig['username'] ?? null,
                        'image'        => $ig['profile_picture_url'] ?? null,
                        'access_token' => $page['access_token'],
                        'expires_in'   => $expiresAt,
                        'is_active'    => true,
                        'status'       => 'active',
                    ]
                );
                $created['instagram']++;
            }
        }

        return redirect()->route('admin.posts.create')->with(
            'success',
            "Connected {$created['facebook']} Facebook Page(s) and {$created['instagram']} Instagram account(s)."
        );
    }

    private function metaGraphVersion(): string
    {
        preg_match('#/(v[\d.]+)/#', $this->metaBaseUrl(), $matches);

        return $matches[1] ?? 'v21.0';
    }

    private function metaBaseUrl(): string
    {
        return adminSetting('posts.facebook.base_url') ?: 'https://graph.facebook.com/v21.0/';
    }

    private function metaCallbackUrl(): string
    {
        return url('/admin/post-accounts/meta/callback');
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
        // url() resolves from this environment's real APP_URL -
        // config('services.app_url') is a separate, currently misconfigured
        // value (pointed at an unrelated domain) that would build a
        // redirect_uri Threads/Meta would reject as not matching what's
        // registered, the same issue already found and worked around for
        // the Messaging module's OAuth flows and for Meta below.
        return url('/admin/post-accounts/threads/callback');
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
        return url('/admin/post-accounts/pinterest/callback');
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
        ], ['user.fields' => 'profile_image_url,username,name']);

        if (!$userResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected, but failed to fetch the X account profile.');
        }

        $user = $userResponse->json()['data'];

        PostAccount::updateOrCreate(
            ['platform' => 'x', 'account_id' => $user['id'], 'user_id' => Auth::id()],
            [
                'name'          => $user['name'] ?? $user['username'],
                'username'      => $user['username'] ?? null,
                'image'         => $user['profile_image_url'] ?? null,
                'access_token'  => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in'    => Carbon::now()->addSeconds($token['expires_in'] ?? 7200),
                'is_active'     => true,
                'status'        => 'active',
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'X account connected.');
    }

    private function xCallbackUrl(): string
    {
        return url('/admin/post-accounts/x/callback');
    }

    /**
     * LinkedIn - standard OAuth 2.0 authorization code flow.
     * LinkedInPostService posts as an Organization
     * (urn:li:organization:{account_id}, not a personal profile - see its
     * publishPost()), so after the user token exchange this resolves
     * every Organization the user has an admin role on via
     * organizationAcls, creating one post_accounts row per Organization -
     * all sharing the same user-level access token, since LinkedIn
     * (unlike Facebook Pages) doesn't hand out separate per-organization
     * tokens.
     */
    public function redirectLinkedin()
    {
        $state = Str::uuid()->toString();
        session(['linkedin_oauth_state' => $state]);

        $url = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => adminSetting('posts.linkedin.client_id'),
            'redirect_uri'  => $this->linkedinCallbackUrl(),
            'state'         => $state,
            'scope'         => 'openid profile w_member_social r_organization_admin w_organization_social rw_organization_admin',
        ]);

        return Redirect::away($url);
    }

    public function callbackLinkedin(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('linkedin_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'LinkedIn connection failed or was cancelled.');
        }

        $tokenResponse = $api->request('post', 'https://www.linkedin.com/oauth/v2/accessToken', [], [
            'grant_type'    => 'authorization_code',
            'code'          => $request->query('code'),
            'client_id'     => adminSetting('posts.linkedin.client_id'),
            'client_secret' => adminSetting('posts.linkedin.client_secret'),
            'redirect_uri'  => $this->linkedinCallbackUrl(),
        ], 'form');

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error_description'] ?? 'Failed to exchange code for a LinkedIn access token.');
        }

        $token = $tokenResponse->json();
        $accessToken = $token['access_token'];
        $baseUrl = adminSetting('posts.linkedin.base_url') ?: 'https://api.linkedin.com/rest/';
        $headers = [
            'Authorization'             => 'Bearer ' . $accessToken,
            'LinkedIn-Version'          => '202401',
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        // Every Organization this user has an admin role on - each becomes
        // its own postable account, the same "resolve every postable
        // entity the user manages" shape as Meta's /me/accounts walk above.
        $aclsResponse = $api->request('get', $baseUrl . 'organizationAcls', $headers, [
            'q'    => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
        ]);

        if (!$aclsResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected, but could not fetch your LinkedIn Organizations.');
        }

        $created = 0;

        foreach ($aclsResponse->json()['elements'] ?? [] as $acl) {
            $orgUrn = $acl['organization'] ?? null;

            if (!$orgUrn || !preg_match('#urn:li:organization:(\d+)#', $orgUrn, $matches)) {
                continue;
            }

            $orgId = $matches[1];
            $orgResponse = $api->request('get', $baseUrl . 'organizations/' . $orgId, $headers);
            $org = $orgResponse->successful() ? $orgResponse->json() : [];

            PostAccount::updateOrCreate(
                ['platform' => 'linkedin', 'account_id' => $orgId, 'user_id' => Auth::id()],
                [
                    'name'         => $org['localizedName'] ?? 'LinkedIn Organization',
                    'username'     => $org['vanityName'] ?? null,
                    'access_token' => $accessToken,
                    'expires_in'   => Carbon::now()->addSeconds($token['expires_in'] ?? 5184000),
                    'is_active'    => true,
                    'status'       => 'active',
                ]
            );
            $created++;
        }

        if ($created === 0) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected to LinkedIn, but no Organization Page was found where you have admin access - posting through this app requires a Company Page, not a personal profile.');
        }

        return redirect()->route('admin.posts.create')->with('success', "Connected {$created} LinkedIn Organization(s).");
    }

    private function linkedinCallbackUrl(): string
    {
        return url('/admin/post-accounts/linkedin/callback');
    }

    /**
     * TikTok Content Posting API - OAuth 2.0 with PKCE (mandatory here,
     * unlike X's optional-but-recommended PKCE), on TikTok's own domain
     * (www.tiktok.com/v2/auth/authorize, not a Graph-API-style host). The
     * user's open_id (returned directly in the token response) is what
     * every later Content Posting API call is scoped to - no separate
     * "list pages" step the way Meta/LinkedIn need, since a TikTok
     * account always posts as itself. TikTok app review is required
     * before most scopes work outside of a small allow-listed developer
     * group - worth knowing going in, the same category of real
     * distribution constraint already flagged for other platforms in this
     * app.
     */
    public function redirectTiktok()
    {
        $codeVerifier = bin2hex(random_bytes(32));
        session(['tiktok_code_verifier' => $codeVerifier]);

        $state = Str::uuid()->toString();
        session(['tiktok_oauth_state' => $state]);

        $codeChallenge = hash('sha256', $codeVerifier);

        $url = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key'            => adminSetting('posts.tiktok.client_id'),
            'response_type'         => 'code',
            'scope'                 => 'user.info.basic,video.publish,video.upload',
            'redirect_uri'          => $this->tiktokCallbackUrl(),
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return Redirect::away($url);
    }

    public function callbackTiktok(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('tiktok_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'TikTok connection failed or was cancelled.');
        }

        $codeVerifier = session('tiktok_code_verifier');

        if (!$codeVerifier) {
            return redirect()->route('admin.posts.create')->with('error', 'Missing PKCE code verifier - please restart the connection flow.');
        }

        $tokenResponse = $api->request('post', 'https://open.tiktokapis.com/v2/oauth/token/', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'client_key'    => adminSetting('posts.tiktok.client_id'),
            'client_secret' => adminSetting('posts.tiktok.client_secret'),
            'code'          => $request->query('code'),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->tiktokCallbackUrl(),
            'code_verifier' => $codeVerifier,
        ], 'form');

        session()->forget(['tiktok_code_verifier', 'tiktok_oauth_state']);

        if (!$tokenResponse->successful() || !empty($tokenResponse->json()['error'])) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error_description'] ?? 'Failed to exchange code for a TikTok access token.');
        }

        $token = $tokenResponse->json();

        $profileResponse = $api->request('get', 'https://open.tiktokapis.com/v2/user/info/', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ], ['fields' => 'open_id,display_name,avatar_url']);

        $profile = $profileResponse->successful() ? ($profileResponse->json()['data']['user'] ?? []) : [];
    dd($profile);
        PostAccount::updateOrCreate(
            ['platform' => 'tiktok', 'account_id' => $token['open_id'], 'user_id' => Auth::id()],
            [
                'name'          => $profile['display_name'] ?? 'TikTok Account',
                'username'      => $profile['display_name'] ?? null,
                'image'         => $profile['avatar_url'] ?? null,
                'access_token'  => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in'    => Carbon::now()->addSeconds($token['expires_in'] ?? 86400),
                'is_active'     => true,
                'status'        => 'active',
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'TikTok account connected.');
    }

    private function tiktokCallbackUrl(): string
    {
        return url('/admin/post-accounts/tiktok/callback');
    }

    /**
     * Google OAuth 2.0 - one flow covering both YouTube uploads and
     * Google Business Profile local posts, the two Google-backed
     * platforms in this module (YoutubePostService/GooglePostService),
     * requesting both products' scopes together the same way Meta's flow
     * above covers Facebook and Instagram in one authorization. Two
     * different account-enumeration calls follow: the user's own YouTube
     * channel (a personal channel always posts as the authenticated user,
     * no "pages" concept), and every Business Profile location under
     * every account they manage (which does have a pages-like structure).
     */
    public function redirectGoogle()
    {
        $state = Str::uuid()->toString();
        session(['google_oauth_state' => $state]);

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => adminSetting('posts.google.client_id'),
            'redirect_uri'  => $this->googleCallbackUrl(),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'scope'         => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/business.manage',
            'state'         => $state,
        ]);

        return Redirect::away($url);
    }

    public function callbackGoogle(Request $request, ApiPostService $api)
    {
        if (!$request->filled('code') || $request->query('state') !== session('google_oauth_state')) {
            return redirect()->route('admin.posts.create')->with('error', 'Google connection failed or was cancelled.');
        }

        $tokenResponse = $api->request('post', 'https://oauth2.googleapis.com/token', [], [
            'code'          => $request->query('code'),
            'client_id'     => adminSetting('posts.google.client_id'),
            'client_secret' => adminSetting('posts.google.client_secret'),
            'redirect_uri'  => $this->googleCallbackUrl(),
            'grant_type'    => 'authorization_code',
        ], 'form');

        if (!$tokenResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse->json()['error_description'] ?? 'Failed to exchange code for a Google access token.');
        }

        $token = $tokenResponse->json();
        $accessToken = $token['access_token'];
        $expiresAt = Carbon::now()->addSeconds($token['expires_in'] ?? 3600);
        $headers = ['Authorization' => 'Bearer ' . $accessToken];
        $created = ['youtube' => 0, 'google' => 0];

        // YouTube - the authenticated user's own channel.
        $channelResponse = $api->request('get', 'https://www.googleapis.com/youtube/v3/channels', $headers, [
            'part' => 'snippet',
            'mine' => 'true',
        ]);

        if ($channelResponse->successful() && !empty($channelResponse->json()['items'])) {
            $channel = $channelResponse->json()['items'][0];

            PostAccount::updateOrCreate(
                ['platform' => 'youtube', 'account_id' => $channel['id'], 'user_id' => Auth::id()],
                [
                    'name'          => $channel['snippet']['title'] ?? 'YouTube Channel',
                    'username'      => $channel['snippet']['customUrl'] ?? null,
                    'image'         => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                    'access_token'  => $accessToken,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'expires_in'    => $expiresAt,
                    'is_active'     => true,
                    'status'        => 'active',
                ]
            );
            $created['youtube']++;
        }

        // Google Business Profile - every location under every account the
        // user manages, mirroring GooglePostService's accounts/{parent}/
        // locations/{account_id} nesting (parent_account_id stores the raw
        // GBP account id a location belongs to - GooglePostService reads
        // it directly as a URL path segment, not as a post_accounts.id
        // foreign key, despite the model's own parentAccount() relation
        // assuming the latter; this matches what publishPost() actually
        // needs to work).
        $accountBaseUrl = adminSetting('posts.google.account_base_url') ?: 'https://mybusinessaccountmanagement.googleapis.com/v1/';
        $accountsResponse = $api->request('get', $accountBaseUrl . 'accounts', $headers);

        if ($accountsResponse->successful()) {
            foreach ($accountsResponse->json()['accounts'] ?? [] as $gbpAccount) {
                $accountName = $gbpAccount['name'] ?? null; // "accounts/{id}"

                if (!$accountName) {
                    continue;
                }

                $locationsResponse = $api->request('get', "https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations", $headers, [
                    'readMask' => 'name,title',
                ]);

                if (!$locationsResponse->successful()) {
                    continue;
                }

                $gbpAccountId = str_replace('accounts/', '', $accountName);

                foreach ($locationsResponse->json()['locations'] ?? [] as $location) {
                    $locationId = str_replace('locations/', '', $location['name'] ?? '');

                    if (!$locationId) {
                        continue;
                    }

                    PostAccount::updateOrCreate(
                        ['platform' => 'google', 'account_id' => $locationId, 'user_id' => Auth::id()],
                        [
                            'name'              => $location['title'] ?? 'Google Business location',
                            'parent_account_id' => $gbpAccountId,
                            'access_token'      => $accessToken,
                            'refresh_token'     => $token['refresh_token'] ?? null,
                            'expires_in'        => $expiresAt,
                            'is_active'         => true,
                            'status'            => 'active',
                        ]
                    );
                    $created['google']++;
                }
            }
        }

        if ($created['youtube'] === 0 && $created['google'] === 0) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected to Google, but no YouTube channel or Business Profile location was found.');
        }

        return redirect()->route('admin.posts.create')->with(
            'success',
            "Connected {$created['youtube']} YouTube channel(s) and {$created['google']} Google Business location(s)."
        );
    }

    private function googleCallbackUrl(): string
    {
        return url('/admin/post-accounts/google/callback');
    }

    public function destroy(PostAccount $account)
    {
        abort_unless($account->user_id === Auth::id(), 403);

        $account->delete();

        return back()->with('success', 'Account disconnected.');
    }
}
