<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\Connections\ConnectionRecorder;
use App\Services\Connections\Drivers\MetaDriver;
use App\Services\Connections\Drivers\XDriver;
use App\Services\Connections\Drivers\ThreadsDriver;
use App\Services\Connections\Drivers\PinterestDriver;
use App\Support\Connections\GrantedScopes;
use App\Services\PostServices\ApiPostService;
use App\Services\PostServices\InstagramPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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
     * Manual WhatsApp entry on the Connection Hub's Meta card: a Phone
     * Number ID + permanent System User token, verified live against the
     * Graph API before saving. The one WhatsApp manual-entry path (the
     * Inbox's separate form, which skipped the Hub, is gone).
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
            return redirect()->route('admin.connections.index')->with('error', 'Could not verify this Phone Number ID and token with Meta.');
        }

        $data = $check->json();

        $account = SocialAccount::updateOrCreate(
            ['platform' => 'whatsapp', 'platform_account_id' => $validated['phone_number_id'], 'user_id' => Auth::id()],
            [
                'name'                    => $validated['name'],
                'username'                => $data['display_phone_number'] ?? null,
                'access_token'            => $validated['access_token'],
                'is_token_valid'          => true,
                'has_posting_permission'  => true,
            ]
        );
        $this->linkWhatsappInbox($account);

        // Manual entry (pasted token, no OAuth app) - still one Meta step in the Hub.
        ConnectionRecorder::record(Auth::id(), 'meta', MetaDriver::WHATSAPP, $validated['phone_number_id'], [
            'access_token' => $validated['access_token'],
        ], [$account->id]);

        return redirect()->route('admin.connections.index')->with('success', 'WhatsApp number connected for publishing and the inbox.');
    }

    /**
     * One WhatsApp connect serves Publishing and the Inbox
     * (docs/connection-hub-design.md §3): the number becomes an inbox
     * channel too, as MessageChannelController::storeWhatsApp used to do.
     */
    private function linkWhatsappInbox(SocialAccount $account, ?bool $webhookSubscribed = null): void
    {
        $account->forceFill(['has_messaging_permission' => true])->save();

        MessageChannel::updateOrCreate(
            ['platform' => 'whatsapp', 'external_id' => $account->platform_account_id],
            array_filter(['social_account_id' => $account->id, 'webhook_subscribed' => $webhookSubscribed], fn ($v) => $v !== null)
        );
    }

    /**
     * WhatsApp Embedded Signup - the "connect with Facebook" alternative
     * to storeWhatsApp()'s manual entry. Unlike the OAuth redirects used
     * everywhere else in this app, Embedded Signup is a Facebook JS SDK
     * popup flow (FB.login() with a config_id configured in the Meta App
     * dashboard, under WhatsApp > Embedded Signup) - the browser never
     * navigates to a callback URL; instead the WABA ID, phone_number_id,
     * and an exchangeable authorization code arrive via postMessage to
     * the page that opened the popup (the Connection Hub's Meta card, or
     * posts/create.blade.php), which then AJAX-POSTs them here.
     *
     * Uses the one Meta app, posts.facebook (docs/connection-hub-design.md
     * §4), plus an Embedded Signup configuration created manually in
     * Meta's App Dashboard: connections.meta.whatsapp_config_id (the legacy
     * messaging.meta.whatsapp_config_id is still read). See
     * MetaDriver::whatsappSignup().
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
        // The one Meta app (docs/connection-hub-design.md §4) - the same
        // app MetaDriver::whatsappSignup() gives the browser's FB SDK.
        $tokenResponse = $api->request('get', $baseUrl . 'oauth/access_token', [], [
            'client_id'     => adminSetting('posts.facebook.client_id'),
            'client_secret' => adminSetting('posts.facebook.client_secret'),
            'code'          => $validated['code'],
        ]);

        if (!$tokenResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => $tokenResponse->json()['error']['message'] ?? 'Failed to exchange the Embedded Signup code for an access token.',
            ], 422);
        }

        $accessToken = $tokenResponse->json()['access_token'];

        // Best-effort: granted permissions for this Embedded Signup token
        // (docs/connection-hub-design.md §1b). Left unset if Meta doesn't
        // answer for this token type.
        $permissionsResponse = $api->request('get', $baseUrl . 'me/permissions', [], ['access_token' => $accessToken]);
        $grantedScopes = GrantedScopes::fromMetaPermissions($permissionsResponse->successful() ? $permissionsResponse->json() : null);

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
                // business_name is optional and neither the Hub nor the Create
                // Post button sends it - a bare $validated[...] read threw here.
                'name'                   => ($validated['business_name'] ?? null) ?: ($data['verified_name'] ?? 'WhatsApp Business'),
                'username'               => $data['display_phone_number'] ?? null,
                'access_token'           => $accessToken,
                'is_token_valid'         => true,
                ...GrantedScopes::attributes($grantedScopes),
                'has_posting_permission' => true,
                'metadata'               => ['settings' => ['waba_id' => $validated['waba_id']]],
            ]
        );

        // Embedded Signup onboards the customer's own WhatsApp Business
        // Account; inbound messages only reach this app's webhook once
        // that WABA subscribes the app (Meta: Embedded Signup >
        // onboarding, POST /{waba-id}/subscribed_apps). Best-effort - the
        // number still works for sending if this fails.
        $subscribe = $api->request('post', $baseUrl . $validated['waba_id'] . '/subscribed_apps', ['Authorization' => 'Bearer ' . $accessToken]);
        if (!$subscribe->successful() || !$subscribe->json('success')) {
            Log::warning('WhatsApp WABA webhook subscription failed.', [
                'waba_id' => $validated['waba_id'],
                'error'   => $subscribe->json('error.message') ?? $subscribe->status(),
            ]);
        }

        $this->linkWhatsappInbox($account, (bool) ($subscribe->successful() && $subscribe->json('success')));

        ConnectionRecorder::record(Auth::id(), 'meta', MetaDriver::WHATSAPP, $validated['phone_number_id'], [
            'provider_app' => 'posts.facebook',
            'access_token' => $accessToken,
            'granted_scopes' => $grantedScopes,
        ], [$account->id]);

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
            return redirect()->route($this->returnRoute())->with('error', 'Threads connection failed or was cancelled.');
        }

        $tokenResponse = $api->request('post', adminSetting('posts.threads.token_url'), [], [
            'client_id'     => adminSetting('posts.threads.client_id'),
            'client_secret' => adminSetting('posts.threads.client_secret'),
            'code'          => $request->query('code'),
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->threadsCallbackUrl(),
        ], 'form');

        if (!$tokenResponse->successful()) {
            return redirect()->route($this->returnRoute())->with('error', $tokenResponse->json()['error_message'] ?? 'Failed to connect Threads.');
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

        $account = SocialAccount::updateOrCreate(
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

        // Long-lived token, no refresh token: status follows its expiry.
        ConnectionRecorder::record(Auth::id(), 'threads', ThreadsDriver::LOGIN, (string) $threadsUserId, [
            'provider_app' => 'posts.threads',
            'access_token' => $accessToken,
            'expires_at' => Carbon::now()->addSeconds($expiresIn),
        ], [$account->id]);

        return redirect()->route($this->returnRoute())->with('success', 'Threads account connected.');
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
            return redirect()->route($this->returnRoute())->with('error', 'Pinterest connection failed or was cancelled.');
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
            return redirect()->route($this->returnRoute())->with('error', $tokenResponse->json()['message'] ?? 'Failed to connect Pinterest.');
        }

        $token = $tokenResponse->json();
        $grantedScopes = GrantedScopes::fromTokenResponse($token);
        $baseUrl = adminSetting('posts.pinterest.base_url') ?: 'https://api.pinterest.com/v5/';

        $profile = $api->request('get', $baseUrl . 'user_account', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ]);

        $profileData = $profile->successful() ? $profile->json() : [];

        $boardId = $this->resolveDefaultPinterestBoard($api, $baseUrl, $token['access_token'], $profileData['username'] ?? null);

        if (!$boardId) {
            return redirect()->route($this->returnRoute())->with('error', 'Connected to Pinterest, but no board could be found or created for posting.');
        }

        $account = SocialAccount::updateOrCreate(
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
                ...GrantedScopes::attributes($grantedScopes),
                'has_posting_permission' => true,
                'metadata'               => ['settings' => ['board_id' => $boardId]],
            ]
        );

        ConnectionRecorder::record(Auth::id(), 'pinterest', PinterestDriver::LOGIN, $profileData['username'] ?? null, [
            'provider_app' => 'posts.pinterest',
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => Carbon::now()->addSeconds($token['expires_in'] ?? 2592000),
            'refresh_expires_at' => isset($token['refresh_token_expires_in']) ? Carbon::now()->addSeconds($token['refresh_token_expires_in']) : null,
            'granted_scopes' => $grantedScopes,
        ], [$account->id]);

        return redirect()->route($this->returnRoute())->with('success', 'Pinterest account connected.');
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
            'scope'                 => \App\Services\MessagingServices\XMessagingService::OAUTH_SCOPES,
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

        // HTTP Basic Auth (client_secret_basic) - the X Developer Console
        // shows this app registered as "Web App, Automated App or Bot"
        // (Confidential client), not "Native App" (Public client). A
        // confidential client's token endpoint calls must authenticate
        // with client_secret - PKCE's code_verifier alone doesn't
        // substitute for that. This call never sent client_secret
        // anywhere, which would fail token exchange the moment a user
        // actually got past X's consent screen (same gap found and fixed
        // in XMessagingService for the DM flow, which registers under the
        // same X app).
        $tokenResponse = $api->request('post', 'https://api.x.com/2/oauth2/token', [
            'Authorization' => 'Basic ' . base64_encode(
                adminSetting('posts.x.client_id') . ':' . adminSetting('posts.x.client_secret')
            ),
        ], [
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
        $grantedScopes = GrantedScopes::fromTokenResponse($token);
        $baseUrl = adminSetting('posts.x.base_url') ?: 'https://api.x.com/2/';

        $userResponse = $api->request('get', $baseUrl . 'users/me', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ], ['user.fields' => 'profile_image_url,username,name,public_metrics']);

        if (!$userResponse->successful()) {
            return redirect()->route('admin.posts.create')->with('error', 'Connected, but failed to fetch the X account profile.');
        }

        $user = $userResponse->json()['data'];
        $metrics = $user['public_metrics'] ?? [];

        $account = SocialAccount::updateOrCreate(
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
                ...GrantedScopes::attributes($grantedScopes),
                'has_posting_permission' => true,
            ]
        );

        // Same posts.x consent as the Inbox's X connect (design doc §6b).
        ConnectionRecorder::record(Auth::id(), 'x', XDriver::OAUTH2, $user['id'], [
            'provider_app' => 'posts.x',
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => Carbon::now()->addSeconds($token['expires_in'] ?? 7200),
            'granted_scopes' => $grantedScopes,
        ], [$account->id]);

        return redirect()->route($this->returnRoute())->with('success', 'X account connected.');
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
            return redirect()->route($this->returnRoute())->with('error', 'Instagram connection failed or state mismatched.');
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
            return redirect()->route($this->returnRoute())->with('error', $errorMsg);
        }

        $tokenData   = $tokenResponse->json();
        // Instagram Login reports granted scopes as `permissions` (§1b).
        $grantedScopes = GrantedScopes::fromTokenResponse($tokenData);
        $shortToken  = $tokenData['access_token'] ?? null;
        $igUserId    = $tokenData['user_id'] ?? null;

        if (!$shortToken) {
            return redirect()->route($this->returnRoute())->with('error', 'Invalid token response received from Instagram.');
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
            // user_id = the Instagram professional account ID, the same ID
            // a Facebook-Page-linked connect stores (InstagramDuplicates).
            'fields'       => 'id,user_id,username,name,profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if (!$userResponse->successful()) {
            return redirect()->route($this->returnRoute())->with('error', 'Connected to Instagram, but failed to fetch profile details.');
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
                ...GrantedScopes::attributes($grantedScopes),
                'has_posting_permission' => true,
                // Tags this account as a standalone Instagram Login token
                // (graph.instagram.com), distinct from callbackMeta()'s
                // Facebook Page tokens (graph.facebook.com) - see
                // InstagramPostService::resolveBaseUrl().
                'metadata'               => ['settings' => array_filter(['auth_type' => 'instagram_login', 'ig_user_id' => isset($igUser['user_id']) ? (string) $igUser['user_id'] : null])],
            ]
        );

        // The consent, for the Connection Hub (design doc §4: Instagram
        // Login is a secondary step of the Meta card).
        ConnectionRecorder::record(Auth::id(), 'meta', MetaDriver::INSTAGRAM_LOGIN, (string) $accId, [
            'provider_app' => 'posts.instagram',
            'access_token' => $accessToken,
            'expires_at' => Carbon::now()->addSeconds($expiresIn),
            'granted_scopes' => $grantedScopes,
        ], [$instagramAccount->id]);

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

        return redirect()->route($this->returnRoute())->with('success', "Successfully connected Instagram account (@{$igUser['username']}).");
    }

    /** Back to the Connection Hub when it started the flow (MetaDriver::connect). */
    private function returnRoute(): string
    {
        return session()->pull('social_oauth_return_to') === 'hub' && Route::has('admin.connections.index')
            ? 'admin.connections.index'
            : 'admin.posts.create';
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
