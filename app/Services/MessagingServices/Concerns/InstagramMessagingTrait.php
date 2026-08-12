<?php

namespace App\Services\MessagingServices\Concerns;

use App\Models\Messaging\MessageChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

/**
 * Instagram Direct's own webhook verify token / app secret
 * (messaging.instagram.*), kept independent from Facebook Messenger's
 * messaging.meta.* settings (see MetaMessagingTrait) so Instagram can be
 * configured under a different Meta App - or just with its own dedicated
 * webhook credentials - without being forced to share Facebook's. The
 * connect flow below (native Instagram Login, not Facebook Login for
 * Business) uses that same app_id/app_secret pair - one Instagram App
 * covers both the webhook signature and the OAuth client credentials,
 * since they're the same underlying Meta App either way.
 */
trait InstagramMessagingTrait
{
    /**
     * GET verification handshake Meta performs when a webhook subscription
     * is first configured (and whenever it's re-verified): respond with
     * the raw hub.challenge value if hub.verify_token matches what this
     * channel was configured with, otherwise reject.
     */
    protected function verifyInstagramWebhook(Request $request, string $expectedVerifyToken): ?string
    {
        if (
            $request->query('hub_mode') === 'subscribe'
            && hash_equals($expectedVerifyToken, (string) $request->query('hub_verify_token'))
        ) {
            return (string) $request->query('hub_challenge');
        }

        return null;
    }

    /**
     * Confirms an inbound webhook POST body genuinely came from Meta -
     * without this, anyone who guesses/finds the webhook URL could inject
     * fake "customer messages" into the inbox. Meta signs every webhook
     * POST body with the App Secret via the X-Hub-Signature-256 header.
     */
    protected function verifyInstagramSignature(Request $request, string $appSecret): bool
    {
        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, substr($signatureHeader, 7));
    }

    /**
     * graph.instagram.com, not graph.facebook.com - Instagram Login issues
     * Instagram-scoped tokens that only work against this domain (using
     * them against graph.facebook.com produces an "invalid token" error
     * even though the token itself is fine). See InstagramPostService's
     * resolveBaseUrl() docblock for the same distinction in the Posts
     * module.
     */
    protected function graphApiUrl(string $path): string
    {
        $version = adminSetting('messaging.instagram.graph_version')
            ?: (adminSetting('messaging.meta.graph_version') ?: 'v21.0');

        return "https://graph.instagram.com/{$version}/" . ltrim($path, '/');
    }

    protected function graphApiCall(string $method, string $path, array $params, string $accessToken)
    {
        $headers = ['Authorization' => "Bearer {$accessToken}"];
        $url = $this->graphApiUrl($path);

        $response = match (strtoupper($method)) {
            'GET'  => $this->apiService->get($url, $headers, $params),
            'POST' => $this->apiService->post($url, $headers, $params),
            default => ['success' => false, 'data' => null],
        };

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Graph API request failed.'];
        }

        return ['success' => true, 'data' => $response['data']];
    }

    /**
     * Must be byte-for-byte identical between the authorize request
     * (redirect()) and the token exchange (handleInstagramCallback()) -
     * Meta rejects the exchange with redirect_uri_mismatch otherwise.
     */
    protected function instagramRedirectUri(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/instagram/callback';
    }

    /**
     * Native Instagram Login (instagram.com/oauth/authorize), not Facebook
     * Login for Business - the Instagram professional account signs in
     * directly with its own credentials, no Facebook Page or Business
     * Manager involved. Mirrors PostAccountController::redirectInstagram()
     * (same product, different module), but scoped down to only what
     * messaging needs.
     */
    public function redirect($state)
    { 

        $url = 'https://www.instagram.com/oauth/authorize?' . http_build_query([
            'force_reauth'   => true,
            'response_type'  => 'code',
            'client_id'      => (string) adminSetting('posts.instagram.client_id'),
            'redirect_uri'   => $this->instagramRedirectUri(),
            'state'          => $state,
            'scope'          => 'instagram_business_basic,instagram_business_manage_messages',
        ]);

        return Redirect::away($url);
    }

    /**
     * Exchanges the OAuth code for a short-lived token (api.instagram.com),
     * then a 60-day long-lived one (graph.instagram.com), then resolves
     * the connected account's own profile - unlike Facebook Login's
     * /me/accounts walk, Instagram Login's token already IS scoped to
     * exactly one Instagram professional account, so there's no Page list
     * to iterate.
     */
    public function handleInstagramCallback(string $code): array
    {
        $clientId = adminSetting('posts.instagram.client_id');
        $clientSecret = adminSetting('posts.instagram.client_secret');
            dd([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->instagramRedirectUri(),
            'code'          => $code,
        ]);
        $tokenResponse = $this->apiService->post('https://api.instagram.com/oauth/access_token', [], [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->instagramRedirectUri(),
            'code'          => $code,
        ], 'form');

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error_message'] ?? 'Failed to obtain an access token from Instagram.'];
        }

        $shortLivedToken = $tokenResponse['data']['access_token'] ?? null;

        if (!$shortLivedToken) {
            return ['success' => false, 'error' => 'Invalid token response received from Instagram.'];
        }

        $longLivedResponse = $this->apiService->get('https://graph.instagram.com/access_token', [], [
            'grant_type'    => 'ig_exchange_token',
            'client_secret' => $clientSecret,
            'access_token'  => $shortLivedToken,
        ]);

        $accessToken = $longLivedResponse['success'] ? ($longLivedResponse['data']['access_token'] ?? $shortLivedToken) : $shortLivedToken;
        $expiresIn = $longLivedResponse['success'] ? ($longLivedResponse['data']['expires_in'] ?? 5184000) : 5184000;

        $profileResponse = $this->apiService->get($this->graphApiUrl('me'), [], [
            'fields'       => 'id,username,name,profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if (!$profileResponse['success']) {
            return ['success' => false, 'error' => 'Connected to Instagram, but failed to fetch the account profile.'];
        }

        $profile = $profileResponse['data'];

        MessageChannel::updateOrCreate(
            ['platform' => 'instagram', 'external_id' => $profile['id']],
            [
                'user_id'       => Auth::id(),
                'name'          => $profile['name'] ?? $profile['username'] ?? 'Instagram Business',
                'username'      => $profile['username'] ?? null,
                'avatar_url'    => $profile['profile_picture_url'] ?? null,
                'access_token'  => $accessToken,
                'refresh_token' => $accessToken,
                'expires_at'    => now()->addSeconds($expiresIn),
                'status'        => true,
                // Tags this as a standalone Instagram Login token
                // (graph.instagram.com) so nothing accidentally sends it
                // against graph.facebook.com.
                'meta'          => ['auth_type' => 'instagram_login'],
            ]
        );

        return ['success' => true, 'data' => ['instagram' => 1]];
    }
}
