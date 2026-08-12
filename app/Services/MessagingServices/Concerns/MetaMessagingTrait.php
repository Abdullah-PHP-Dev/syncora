<?php

namespace App\Services\MessagingServices\Concerns;

use App\Models\Messaging\MessageChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use App\Models\Messaging\Conversation;
/**
 * Shared plumbing for Facebook Messenger, Instagram Direct, and WhatsApp -
 * all three are Graph API products under the same Meta App, so webhook
 * verification (the hub.challenge handshake), payload authenticity
 * (X-Hub-Signature-256, HMAC-SHA256 over the raw body using the App
 * Secret) and the base Graph API call shape are identical across all
 * three, only the message/webhook payload *shapes* differ per product.
 */
trait MetaMessagingTrait
{
    /**
     * GET verification handshake Meta performs when a webhook subscription
     * is first configured (and whenever it's re-verified): respond with
     * the raw hub.challenge value if hub.verify_token matches what this
     * channel was configured with, otherwise reject.
     */
    protected function verifyMetaWebhook(Request $request, string $expectedVerifyToken): ?string
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
     * fake "customer messages" (or fake delivery/read receipts) into the
     * inbox. Meta signs every webhook POST body with the App Secret via
     * the X-Hub-Signature-256 header.
     */
    protected function verifyMetaSignature(Request $request, string $appSecret): bool
    {
        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, substr($signatureHeader, 7));
    }

    protected function graphApiUrl(string $path): string
    {
        $version = adminSetting('messaging.meta.graph_version') ?: 'v21.0';

        return "https://graph.facebook.com/{$version}/" . ltrim($path, '/');
    }

    /**
     * Must be byte-for-byte identical between the authorize request
     * (redirect()) and the token exchange (handleMetaCallback()) - Meta
     * rejects the exchange with redirect_uri_mismatch otherwise.
     */
    protected function metaRedirectUri(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/meta/callback';
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
     * Facebook Page connections only - Instagram Direct now has its own
     * native login flow (InstagramMessagingTrait::redirect()), not this
     * one, since Instagram-scoped tokens issued through Instagram Login
     * aren't interchangeable with Facebook Page tokens issued here (they
     * authenticate against different Graph API domains entirely). WhatsApp
     * is deliberately not included either: Cloud API numbers are set up
     * through Meta's Embedded Signup JS SDK (not a plain OAuth redirect)
     * or a permanent System User token from Business Settings, so that
     * channel type is connected via manual entry instead (see
     * MessageChannelController).
     */
    public function redirect($state)
    {
        $url = 'https://www.facebook.com/' . (adminSetting('messaging.meta.graph_version') ?: 'v21.0') . '/dialog/oauth?' . http_build_query([
            'client_id'     => adminSetting('posts.facebook.client_id'),
            'redirect_uri'  => $this->metaRedirectUri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'pages_show_list,pages_read_engagement,pages_read_user_content,pages_messaging,pages_manage_metadata,business_management',
        ]);

        return Redirect::away($url);
    }

    /**
     * Exchanges the OAuth code for a long-lived user token, then walks
     * /me/accounts (every Page the user administers) to create a
     * Messenger MessageChannel per Page.
     */
    public function handleMetaCallback(string $code): array
    {
        $tokenResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'client_id'     => adminSetting('posts.facebook.client_id'),
            'client_secret' => adminSetting('posts.facebook.client_secret'),
            'redirect_uri'  => $this->metaRedirectUri(),
            'code'          => $code,
        ]);

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error']['message'] ?? 'Failed to exchange code for a Meta access token.'];
        }

        $shortLivedToken = $tokenResponse['data']['access_token'];

        $longLivedResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => adminSetting('posts.facebook.client_id'),
            'client_secret'     => adminSetting('posts.facebook.client_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (!$longLivedResponse['success']) {
            Log::warning('Meta long-lived token exchange failed, falling back to short-lived user token.', [
                'error' => $longLivedResponse['data']['error']['message'] ?? null,
            ]);
        }

        $userToken = $longLivedResponse['success'] ? $longLivedResponse['data']['access_token'] : $shortLivedToken;

        $pagesResponse = $this->apiService->get($this->graphApiUrl('me/accounts'), [], [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token,picture',
        ]);

        if (!$pagesResponse['success']) {
            return ['success' => false, 'error' => $pagesResponse['data']['error']['message'] ?? 'Failed to fetch Pages.'];
        }

        $created = 0;

        foreach ($pagesResponse['data']['data'] ?? [] as $page) {
            MessageChannel::updateOrCreate(
                ['platform' => 'facebook', 'external_id' => $page['id']],
                [
                    'user_id'       => Auth::id(),
                    'name'          => $page['name'],
                    'username'      => null,
                    'avatar_url'    => $page['picture']['data']['url'] ?? null,
                    'access_token'  => $page['access_token'],
                    'refresh_token' => $page['access_token'],
                    'status'        => true,
                ]
            );
            $created++;
        }

        return ['success' => true, 'data' => ['facebook' => $created]];
    }
}
