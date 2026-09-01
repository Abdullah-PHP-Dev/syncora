<?php

namespace App\Services\SocialAuth;

use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\ApiService;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\MessagingServices\InstagramMessengerService;
use App\Services\PostServices\InstagramPostService;
use App\Services\PostServices\LinkedInPostService;
use App\Services\PostServices\MetaPostService;
use App\Services\PostServices\YoutubePostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

/**
 * Single combined-consent OAuth flow for the four platforms where it's
 * actually achievable in one app registration: Facebook/Meta, Google,
 * LinkedIn, TikTok. Every other platform (X, Pinterest, Threads, WhatsApp,
 * Snapchat, Telegram, LINE, Teams, Matrix, Discord, Slack, Zalo) keeps its
 * existing separate connect flow in PostAccountController/
 * MessageChannelController/the AdServices classes - those platforms either
 * use bot tokens/static credentials with no OAuth consent screen at all, or
 * (TikTok Ads specifically, see below) a genuinely separate OAuth
 * authorization server from the one used here.
 *
 * Reuses the `posts.{platform}.client_id/client_secret` admin settings as
 * the one app registration for all three capabilities, since that's
 * already the richest/most-used registration for each of these platforms,
 * and Messaging already reuses it for Facebook (see MetaMessagingTrait).
 * Getting real posting+messaging+ads scopes approved on one app is a
 * platform-side app-review step (Meta App Review, LinkedIn's Advertising
 * API + Community Management API products, Google Cloud OAuth
 * verification) - outside what code alone can grant, same as any OAuth
 * scope request.
 */
class SocialAuthService
{
    private const SUPPORTED_PLATFORMS = ['facebook', 'google', 'linkedin', 'tiktok'];

    public function __construct(
        private ApiService $api,
        private MetaPostService $metaPostService,
        private InstagramPostService $instagramPostService,
        private YoutubePostService $youtubePostService,
        private LinkedInPostService $linkedInPostService,
        private FacebookMessengerService $facebookMessengerService,
        private InstagramMessengerService $instagramMessengerService,
    ) {
    }

    public function isSupported(string $platform): bool
    {
        return in_array($platform, self::SUPPORTED_PLATFORMS, true);
    }

    public function redirect(string $platform)
    {
        $state = Str::uuid()->toString();
        session(["social_oauth_state_{$platform}" => $state]);

        return match ($platform) {
            'facebook' => $this->redirectFacebook($state),
            'google' => $this->redirectGoogle($state),
            'linkedin' => $this->redirectLinkedin($state),
            'tiktok' => $this->redirectTiktok($state),
            default => redirect()->route('admin.posts.create')->with('error', "Unsupported platform: {$platform}"),
        };
    }

    public function callback(string $platform, string $code, ?string $state, ?string $codeVerifier = null)
    {
        $expectedState = session("social_oauth_state_{$platform}");
        session()->forget("social_oauth_state_{$platform}");

        if (!$code || $state !== $expectedState) {
            return redirect()->route('admin.posts.create')->with('error', ucfirst($platform) . ' connection failed or was cancelled.');
        }

        return match ($platform) {
            'facebook' => $this->callbackFacebook($code),
            'google' => $this->callbackGoogle($code),
            'linkedin' => $this->callbackLinkedin($code),
            'tiktok' => $this->callbackTiktok($code, $codeVerifier),
            default => redirect()->route('admin.posts.create')->with('error', "Unsupported platform: {$platform}"),
        };
    }

    private function callbackUrl(string $platform): string
    {
        return url("/admin/social-accounts/{$platform}/callback");
    }

    // =====================================================================
    // Facebook / Instagram - posting + messaging + ads all genuinely share
    // one Graph API OAuth app.
    // =====================================================================

    private function redirectFacebook(string $state)
    {
        $graphVersion = adminSetting('posts.facebook.graph_version') ?: 'v21.0';

        $url = "https://www.facebook.com/{$graphVersion}/dialog/oauth?" . http_build_query([
            'client_id' => adminSetting('posts.facebook.client_id'),
            'redirect_uri' => $this->callbackUrl('facebook'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', [
                'pages_show_list', 'pages_manage_posts', 'pages_read_engagement',
                'pages_manage_metadata', 'pages_read_user_content', 'pages_manage_engagement',
                'pages_messaging', 'read_insights', 'business_management',
                'instagram_basic', 'instagram_content_publish', 'instagram_manage_comments',
                'instagram_manage_insights', 'ads_management', 'ads_read',
            ]),
        ]);

        return Redirect::away($url);
    }

    private function callbackFacebook(string $code)
    {
        $baseUrl = adminSetting('posts.facebook.base_url') ?: 'https://graph.facebook.com/v25.0/';
        $clientId = adminSetting('posts.facebook.client_id');
        $clientSecret = adminSetting('posts.facebook.client_secret');

        $tokenResponse = $this->api->get($baseUrl . 'oauth/access_token', [], [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->callbackUrl('facebook'),
            'code' => $code,
        ]);

        if (!$tokenResponse['success']) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse['data']['error']['message'] ?? 'Failed to exchange code for a Facebook access token.');
        }

        $shortLivedToken = $tokenResponse['data']['access_token'];

        $longLivedResponse = $this->api->get($baseUrl . 'oauth/access_token', [], [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $userToken = $longLivedResponse['success'] ? $longLivedResponse['data']['access_token'] : $shortLivedToken;
        $expiresAt = Carbon::now()->addDays(60);
        $userId = Auth::id();

        $pagesConnected = 0;
        $instagramConnected = 0;
        $adAccountsConnected = 0;

        $pagesResponse = $this->api->get($baseUrl . 'me/accounts', [], [
            'access_token' => $userToken,
            'fields' => 'id,name,access_token,picture,category,fan_count,followers_count,instagram_business_account{id,username,profile_picture_url,followers_count}',
        ]);

        foreach ($pagesResponse['data']['data'] ?? [] as $page) {
            $pageAccount = SocialAccount::updateOrCreate(
                ['platform' => 'facebook', 'platform_account_id' => $page['id'], 'user_id' => $userId],
                [
                    'name' => $page['name'],
                    'avatar_url' => $page['picture']['data']['url'] ?? null,
                    'category' => $page['category'] ?? null,
                    'account_type' => 'page',
                    'followers_count' => $page['followers_count'] ?? null,
                    'likes_count' => $page['fan_count'] ?? null,
                    'access_token' => $page['access_token'],
                    'refresh_token' => $page['access_token'],
                    'token_type' => 'page',
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_posting_permission' => true,
                    'has_messaging_permission' => true,
                ]
            );
            $pagesConnected++;

            $pageChannel = MessageChannel::updateOrCreate(
                ['platform' => 'facebook', 'external_id' => $page['id']],
                ['social_account_id' => $pageAccount->id]
            );

            // Each of these is independently failure-tolerant (internally
            // try/caught, logs and returns rather than throwing) - this
            // outer try/catch is a deliberate second safety net so the
            // account/channel above stay saved even if a sync/subscribe/
            // backfill call fails (eg. too small for Insights, or a token
            // missing a scope). Without these, has_posting_permission and
            // has_messaging_permission would be true but comments/DMs would
            // never actually arrive, since Meta only pushes webhook events
            // to Pages that completed the /subscribed_apps opt-in below.
            try {
                $this->metaPostService->subscribeToWebhooks($pageAccount);
            } catch (\Throwable $e) {
                Log::warning('Facebook post webhook subscribe failed after unified connect.', ['account_id' => $pageAccount->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->metaPostService->backfillRecentPosts($pageAccount);
            } catch (\Throwable $e) {
                Log::warning('Facebook post backfill failed after unified connect.', ['account_id' => $pageAccount->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->facebookMessengerService->syncChannelDetails($pageChannel);
            } catch (\Throwable $e) {
                Log::warning('Facebook channel details sync failed after unified connect.', ['channel_id' => $pageChannel->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->facebookMessengerService->subscribeToWebhooks($pageChannel);
            } catch (\Throwable $e) {
                Log::warning('Facebook channel webhook subscribe failed after unified connect.', ['channel_id' => $pageChannel->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->facebookMessengerService->backfillRecentConversations($pageChannel);
            } catch (\Throwable $e) {
                Log::warning('Facebook conversation backfill failed after unified connect.', ['channel_id' => $pageChannel->id, 'error' => $e->getMessage()]);
            }

            if (!empty($page['instagram_business_account']['id'])) {
                $ig = $page['instagram_business_account'];

                $igAccount = SocialAccount::updateOrCreate(
                    ['platform' => 'instagram', 'platform_account_id' => $ig['id'], 'user_id' => $userId],
                    [
                        'name' => $ig['username'] ?? $page['name'],
                        'username' => $ig['username'] ?? null,
                        'avatar_url' => $ig['profile_picture_url'] ?? null,
                        'account_type' => 'business_account',
                        'followers_count' => $ig['followers_count'] ?? null,
                        'access_token' => $page['access_token'],
                        'refresh_token' => $page['access_token'],
                        'token_type' => 'page',
                        'is_token_valid' => true,
                        'expires_at' => $expiresAt,
                        'has_posting_permission' => true,
                        'has_messaging_permission' => true,
                        'metadata' => ['linked_page_id' => $page['id']],
                    ]
                );
                $instagramConnected++;

                $igChannel = MessageChannel::updateOrCreate(
                    ['platform' => 'instagram', 'external_id' => $ig['id']],
                    ['social_account_id' => $igAccount->id]
                );

                try {
                    $this->instagramPostService->subscribeToWebhooks($igAccount);
                } catch (\Throwable $e) {
                    Log::warning('Instagram post webhook subscribe failed after unified connect.', ['account_id' => $igAccount->id, 'error' => $e->getMessage()]);
                }
                try {
                    $this->instagramPostService->backfillRecentPosts($igAccount);
                } catch (\Throwable $e) {
                    Log::warning('Instagram post backfill failed after unified connect.', ['account_id' => $igAccount->id, 'error' => $e->getMessage()]);
                }
                try {
                    $this->instagramMessengerService->syncChannelDetails($igChannel);
                } catch (\Throwable $e) {
                    Log::warning('Instagram channel details sync failed after unified connect.', ['channel_id' => $igChannel->id, 'error' => $e->getMessage()]);
                }
                try {
                    $this->instagramMessengerService->subscribeToWebhooks($igChannel);
                } catch (\Throwable $e) {
                    Log::warning('Instagram channel webhook subscribe failed after unified connect.', ['channel_id' => $igChannel->id, 'error' => $e->getMessage()]);
                }
                try {
                    $this->instagramMessengerService->backfillRecentConversations($igChannel);
                } catch (\Throwable $e) {
                    Log::warning('Instagram conversation backfill failed after unified connect.', ['channel_id' => $igChannel->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $adAccountsResponse = $this->api->get($baseUrl . 'me/adaccounts', [], [
            'access_token' => $userToken,
            'fields' => 'id,name,account_id,account_status,currency,business',
        ]);

        foreach ($adAccountsResponse['data']['data'] ?? [] as $adAccount) {
            if ((int) ($adAccount['account_status'] ?? 0) !== 1) {
                continue;
            }

            $fbAdAccount = SocialAccount::updateOrCreate(
                ['platform' => 'facebook', 'platform_account_id' => $adAccount['id'], 'user_id' => $userId],
                [
                    'name' => $adAccount['name'] ?? 'Facebook Ad Account',
                    'account_type' => 'ad_account',
                    'access_token' => $userToken,
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_ads_permission' => true,
                    'metadata' => [
                        'currency' => $adAccount['currency'] ?? null,
                        'business_id' => $adAccount['business']['id'] ?? null,
                    ],
                ]
            );
            $fbAdAccount->syncAdDetails([
                'currency' => $adAccount['currency'] ?? null,
                'business_id' => $adAccount['business']['id'] ?? null,
                'account_status' => (string) ($adAccount['account_status'] ?? null),
            ]);
            $adAccountsConnected++;
        }

        return redirect()->route('admin.posts.create')->with(
            'success',
            "Connected {$pagesConnected} Facebook Page(s), {$instagramConnected} Instagram account(s), and {$adAccountsConnected} ad account(s)."
        );
    }

    // =====================================================================
    // Google - YouTube + Google Business Profile (posting) + Google Ads,
    // all standard Google OAuth 2.0 scopes on one client.
    // =====================================================================

    private function redirectGoogle(string $state)
    {
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => adminSetting('posts.google.client_id'),
            'redirect_uri' => $this->callbackUrl('google'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/youtube.force-ssl',
                'https://www.googleapis.com/auth/business.manage',
                'https://www.googleapis.com/auth/adwords',
            ]),
            'state' => $state,
        ]);

        return Redirect::away($url);
    }

    private function callbackGoogle(string $code)
    {
        $tokenResponse = $this->api->post('https://oauth2.googleapis.com/token', [], [
            'code' => $code,
            'client_id' => adminSetting('posts.google.client_id'),
            'client_secret' => adminSetting('posts.google.client_secret'),
            'redirect_uri' => $this->callbackUrl('google'),
            'grant_type' => 'authorization_code',
        ], 'form');

        if (!$tokenResponse['success']) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a Google access token.');
        }

        $token = $tokenResponse['data'];
        $accessToken = $token['access_token'];
        $refreshToken = $token['refresh_token'] ?? null;
        $expiresAt = Carbon::now()->addSeconds($token['expires_in'] ?? 3600);
        $userId = Auth::id();

        $youtubeConnected = $this->connectYoutubeChannels($accessToken, $refreshToken, $expiresAt, $userId);
        $adAccountsConnected = $this->connectGoogleAdsCustomers($accessToken, $refreshToken, $expiresAt, $userId);

        return redirect()->route('admin.posts.create')->with(
            'success',
            "Connected {$youtubeConnected} YouTube channel(s) and {$adAccountsConnected} Google Ads account(s)."
        );
    }

    private function connectYoutubeChannels(string $accessToken, ?string $refreshToken, Carbon $expiresAt, int $userId): int
    {
        $response = $this->api->get('https://www.googleapis.com/youtube/v3/channels', [
            'Authorization' => 'Bearer ' . $accessToken,
        ], ['part' => 'snippet,statistics', 'mine' => 'true']);

        $connected = 0;

        foreach ($response['data']['items'] ?? [] as $channel) {
            $channelAccount = SocialAccount::updateOrCreate(
                ['platform' => 'youtube', 'platform_account_id' => $channel['id'], 'user_id' => $userId],
                [
                    'name' => $channel['snippet']['title'] ?? 'YouTube Channel',
                    'avatar_url' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                    'account_type' => 'channel',
                    'subscribers_count' => $channel['statistics']['subscriberCount'] ?? null,
                    'views_count' => $channel['statistics']['viewCount'] ?? null,
                    'media_count' => $channel['statistics']['videoCount'] ?? null,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_posting_permission' => true,
                ]
            );
            $connected++;

            // WebSub (PubSubHubbub) subscription for new-upload push
            // notifications, and a one-time pull of recent videos/comments -
            // without this the channel row exists but YoutubeWebhookController
            // never receives anything for it.
            try {
                $this->youtubePostService->subscribeToWebhooks($channelAccount);
            } catch (\Throwable $e) {
                Log::warning('YouTube webhook subscribe failed after unified connect.', ['account_id' => $channelAccount->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->youtubePostService->backfillRecentPosts($channelAccount);
            } catch (\Throwable $e) {
                Log::warning('YouTube video backfill failed after unified connect.', ['account_id' => $channelAccount->id, 'error' => $e->getMessage()]);
            }
        }

        return $connected;
    }

    /**
     * Requires ads.google.developer_token (a Google Ads MCC credential,
     * independent of which OAuth client minted the token) to already be
     * configured - without it every customer lookup below 401s, the same
     * prerequisite GoogleAdsApiTrait's own ads-only connect flow has.
     */
    private function connectGoogleAdsCustomers(string $accessToken, ?string $refreshToken, Carbon $expiresAt, int $userId): int
    {
        $developerToken = adminSetting('ads.google.developer_token');

        if (empty($developerToken)) {
            return 0;
        }

        $base = adminSetting('ads.google.base_url') ?: 'https://googleads.googleapis.com/v24/';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'developer-token' => $developerToken,
            'Content-Type' => 'application/json',
        ];

        $loginCustomerId = adminSetting('ads.google.login_customer_id');
        if (!empty($loginCustomerId)) {
            $headers['login-customer-id'] = str_replace('-', '', $loginCustomerId);
        }

        $listResponse = $this->api->get($base . 'customers:listAccessibleCustomers', $headers);

        if (!$listResponse['success']) {
            Log::warning('Google Ads listAccessibleCustomers failed during unified connect.', [
                'body' => $listResponse['body'] ?? ($listResponse['error'] ?? null),
            ]);

            return 0;
        }

        $connected = 0;

        foreach ($listResponse['data']['resourceNames'] ?? [] as $resourceName) {
            if (!preg_match('#customers/(\d+)#', $resourceName, $matches)) {
                continue;
            }

            $customerId = $matches[1];
            $detail = [];
            $detailResponse = $this->api->post(
                $base . 'customers/' . $customerId . '/googleAds:search',
                $headers,
                ['query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.manager FROM customer LIMIT 1']
            );

            if ($detailResponse['success']) {
                $detail = $detailResponse['data']['results'][0]['customer'] ?? [];
            }

            if (!empty($detail['manager'])) {
                continue;
            }

            $googleAdAccount = SocialAccount::updateOrCreate(
                ['platform' => 'google', 'platform_account_id' => $customerId, 'user_id' => $userId],
                [
                    'name' => $detail['descriptiveName'] ?? "Google Ads Customer {$customerId}",
                    'account_type' => 'ad_account',
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_ads_permission' => true,
                    'metadata' => ['currency' => $detail['currencyCode'] ?? null],
                ]
            );
            $googleAdAccount->syncAdDetails([
                'currency' => $detail['currencyCode'] ?? null,
            ]);
            $connected++;
        }

        return $connected;
    }

    // =====================================================================
    // LinkedIn - posting (organizations) + ads (sponsored ad accounts), one
    // OAuth app when it's approved for both the Community Management API
    // and Advertising API products.
    // =====================================================================

    private function redirectLinkedin(string $state)
    {
        $url = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => adminSetting('posts.linkedin.client_id'),
            'redirect_uri' => $this->callbackUrl('linkedin'),
            'state' => $state,
            'scope' => 'w_member_social r_organization_admin r_organization_social w_organization_social',
         //   'scope' => 'openid profile w_member_social r_organization_admin r_organization_social w_organization_social rw_organization_admin r_ads rw_ads r_ads_reporting',
        ]);

        return Redirect::away($url);
    }

    private function callbackLinkedin(string $code)
    {
        $tokenResponse = $this->api->post('https://www.linkedin.com/oauth/v2/accessToken', [], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => adminSetting('posts.linkedin.client_id'),
            'client_secret' => adminSetting('posts.linkedin.client_secret'),
            'redirect_uri' => $this->callbackUrl('linkedin'),
        ], 'form');

        if (!$tokenResponse['success']) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a LinkedIn access token.');
        }

        $token = $tokenResponse['data'];
        $accessToken = $token['access_token'];
        $expiresAt = Carbon::now()->addSeconds($token['expires_in'] ?? 5184000);
        $userId = Auth::id();
        $baseUrl = adminSetting('posts.linkedin.base_url') ?: 'https://api.linkedin.com/rest/';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'LinkedIn-Version' => '202401',
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        $orgsConnected = 0;
        $adAccountsConnected = 0;

        $aclsResponse = $this->api->get($baseUrl . 'organizationAcls', $headers, [
            'q' => 'roleAssignee',
            'role' => 'ADMINISTRATOR',
        ]);

        foreach ($aclsResponse['data']['elements'] ?? [] as $acl) {
            $orgUrn = $acl['organization'] ?? null;

            if (!$orgUrn || !preg_match('#urn:li:organization:(\d+)#', $orgUrn, $matches)) {
                continue;
            }

            $orgId = $matches[1];
            $orgResponse = $this->api->get($baseUrl . 'organizations/' . $orgId, $headers);
            $org = $orgResponse['success'] ? $orgResponse['data'] : [];

            $orgAccount = SocialAccount::updateOrCreate(
                ['platform' => 'linkedin', 'platform_account_id' => $orgId, 'user_id' => $userId],
                [
                    'name' => $org['localizedName'] ?? 'LinkedIn Organization',
                    'username' => $org['vanityName'] ?? null,
                    'account_type' => 'organization',
                    'access_token' => $accessToken,
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_posting_permission' => true,
                ]
            );
            $orgsConnected++;

            // subscribeToWebhooks() is a soft no-op on LinkedIn (it only
            // records a callback URL - LinkedIn doesn't push organic
            // engagement events under the standard API tier), but
            // backfillRecentPosts() genuinely pulls in existing posts/
            // comments, so it's still worth calling both for consistency
            // with the other platforms.
            try {
                $this->linkedInPostService->subscribeToWebhooks($orgAccount);
            } catch (\Throwable $e) {
                Log::warning('LinkedIn webhook subscribe failed after unified connect.', ['account_id' => $orgAccount->id, 'error' => $e->getMessage()]);
            }
            try {
                $this->linkedInPostService->backfillRecentPosts($orgAccount);
            } catch (\Throwable $e) {
                Log::warning('LinkedIn post backfill failed after unified connect.', ['account_id' => $orgAccount->id, 'error' => $e->getMessage()]);
            }
        }

        $adAccountsResponse = $this->api->get($baseUrl . 'adAccountUsers', $headers, [
            'q' => 'authenticatedUser',
        ]);

        foreach ($adAccountsResponse['data']['elements'] ?? [] as $entry) {
            $accountUrn = $entry['account'] ?? null;

            if (!$accountUrn || !preg_match('#urn:li:sponsoredAccount:(\d+)#', $accountUrn, $matches)) {
                continue;
            }

            $accountId = $matches[1];
            $accountResponse = $this->api->get($baseUrl . 'adAccounts/' . $accountId, $headers);
            $account = $accountResponse['success'] ? $accountResponse['data'] : [];

            $linkedinAdAccount = SocialAccount::updateOrCreate(
                ['platform' => 'linkedin', 'platform_account_id' => $accountId, 'user_id' => $userId],
                [
                    'name' => $account['name'] ?? "LinkedIn Ad Account {$accountId}",
                    'account_type' => 'ad_account',
                    'access_token' => $accessToken,
                    'is_token_valid' => true,
                    'expires_at' => $expiresAt,
                    'has_ads_permission' => true,
                    'metadata' => ['currency' => $account['currency'] ?? null],
                ]
            );
            $linkedinAdAccount->syncAdDetails([
                'currency' => $account['currency'] ?? null,
            ]);
            $adAccountsConnected++;
        }

        return redirect()->route('admin.posts.create')->with(
            'success',
            "Connected {$orgsConnected} LinkedIn Organization(s) and {$adAccountsConnected} ad account(s)."
        );
    }

    // =====================================================================
    // TikTok - posting only. TikTok's Marketing API (ads) is a genuinely
    // separate OAuth authorization server (ads.tiktok.com/marketing_api/
    // auth, its own token endpoint, its own advertiser_ids response shape)
    // from the Login Kit used for posting (www.tiktok.com/v2/auth/
    // authorize) - there is no combined-scope consent screen TikTok
    // offers between the two, so TikTok Ads keeps its own separate connect
    // flow in AdController/TiktokAdService, now targeting SocialAccount
    // like every other platform, but never through this unified redirect.
    // =====================================================================

    private function redirectTiktok(string $state)
    {
        $codeVerifier = bin2hex(random_bytes(32));
        session(['social_tiktok_code_verifier' => $codeVerifier]);

        $url = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key' => adminSetting('posts.tiktok.client_id'),
            'response_type' => 'code',
            'scope' => 'user.info.basic,video.publish,video.upload,user.info.profile,user.info.stats',
            'redirect_uri' => $this->callbackUrl('tiktok'),
            'state' => $state,
            'code_challenge' => hash('sha256', $codeVerifier),
            'code_challenge_method' => 'S256',
        ]);

        return Redirect::away($url);
    }

    private function callbackTiktok(string $code, ?string $codeVerifier)
    {
        $codeVerifier ??= session('social_tiktok_code_verifier');
        session()->forget('social_tiktok_code_verifier');

        if (!$codeVerifier) {
            return redirect()->route('admin.posts.create')->with('error', 'Missing PKCE code verifier - please restart the connection flow.');
        }

        $tokenResponse = $this->api->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], [
            'client_key' => adminSetting('posts.tiktok.client_id'),
            'client_secret' => adminSetting('posts.tiktok.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->callbackUrl('tiktok'),
            'code_verifier' => $codeVerifier,
        ], 'form');

        if (!$tokenResponse['success'] || !empty($tokenResponse['data']['error'])) {
            return redirect()->route('admin.posts.create')->with('error', $tokenResponse['data']['error_description'] ?? 'Failed to exchange code for a TikTok access token.');
        }

        $token = $tokenResponse['data'];

        $profileResponse = $this->api->get('https://open.tiktokapis.com/v2/user/info/', [
            'Authorization' => 'Bearer ' . $token['access_token'],
        ], ['fields' => 'open_id,display_name,avatar_url,profile_deep_link,username,bio_description,follower_count,following_count,likes_count,video_count']);

        $profile = $profileResponse['success'] ? ($profileResponse['data']['data']['user'] ?? []) : [];

        SocialAccount::updateOrCreate(
            ['platform' => 'tiktok', 'platform_account_id' => $token['open_id'], 'user_id' => Auth::id()],
            [
                'name' => $profile['display_name'] ?? 'TikTok Account',
                'username' => $profile['username'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
                'account_type' => 'profile',
                'followers_count' => $profile['follower_count'] ?? null,
                'likes_count' => $profile['likes_count'] ?? null,
                'following_count' => $profile['following_count'] ?? null,
                'media_count' => $profile['video_count'] ?? null,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'is_token_valid' => true,
                'expires_at' => Carbon::now()->addSeconds($token['expires_in'] ?? 86400),
                'has_posting_permission' => true,
                'metadata' => [
                    'description' => $profile['bio_description'] ?? null,
                    'account_url' => $profile['profile_deep_link'] ?? null,
                ],
            ]
        );

        return redirect()->route('admin.posts.create')->with('success', 'TikTok account connected.');
    }
}
