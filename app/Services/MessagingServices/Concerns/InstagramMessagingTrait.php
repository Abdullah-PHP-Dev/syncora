<?php

namespace App\Services\MessagingServices\Concerns;

use App\Models\Messaging\MessageChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        return "https://graph.facebook.com/{$version}/" . ltrim($path, '/');
    }

    protected function instagramRedirectUri(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/instagram/callback';
    }

    /**
     * Updated Redirect to use Business Login OAuth Flow
     */
    public function redirect($state)
    { 
        $version = adminSetting('messaging.meta.graph_version') ?: 'v21.0';

        $url = "https://www.facebook.com/{$version}/dialog/oauth?" . http_build_query([
            'client_id'     => (string) adminSetting('posts.facebook.client_id'),
            'redirect_uri'  => $this->instagramRedirectUri(),
            'state'         => $state,
            'response_type' => 'code',
            // Correct Business permissions to allow messaging + sender profile fetching
            'scope'         => 'instagram_basic,instagram_manage_messages,pages_show_list,pages_read_engagement',
        ]);

        return Redirect::away($url);
    }

    /**
     * Updated Callback Exchange
     */
    public function handleInstagramCallback(string $code): array
    {
        $clientId = adminSetting('posts.instagram.client_id');
        $clientSecret = adminSetting('posts.instagram.client_secret');

        // 1. Exchange short-lived token via graph.facebook.com
        $tokenResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $this->instagramRedirectUri(),
            'code'          => $code,
        ]);

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error']['message'] ?? 'Failed to exchange code.'];
        }

        $shortLivedToken = $tokenResponse['data']['access_token'];

        // 2. Exchange for 60-day Long-Lived User Token
        $longLivedResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $userToken = $longLivedResponse['success'] 
            ? $longLivedResponse['data']['access_token'] 
            : $shortLivedToken;

        $expiresIn = $longLivedResponse['success'] 
            ? ($longLivedResponse['data']['expires_in'] ?? 5184000) 
            : 3600;

        // 3. Get Instagram Business Account ID & Page Access Token via /me/accounts
        $pagesResponse = $this->apiService->get($this->graphApiUrl('me/accounts'), [], [
            'access_token' => $userToken,
            'fields'       => 'id,name,access_token,instagram_business_account{id,username,profile_picture_url,name}',
        ]);

        if (!$pagesResponse['success'] || empty($pagesResponse['data']['data'])) {
            return ['success' => false, 'error' => 'No connected Instagram Business Accounts found.'];
        }

        $created = 0;

        foreach ($pagesResponse['data']['data'] as $page) {
            if (!empty($page['instagram_business_account']['id'])) {
                $ig = $page['instagram_business_account'];

                // IMPORTANT: Use the PAGE access token! This grants full permission to fetch sender profiles!
                $pageAccessToken = $page['access_token'];

                $channel = MessageChannel::updateOrCreate(
                    ['platform' => 'instagram', 'external_id' => $ig['id']],
                    [
                        'user_id'       => Auth::id(),
                        'name'          => $ig['name'] ?? $ig['username'] ?? 'Instagram Business',
                        'username'      => $ig['username'] ?? null,
                        'avatar_url'    => $ig['profile_picture_url'] ?? null,
                        'access_token'  => $pageAccessToken, // Page Token gives complete access like Dashboard manual token
                        'refresh_token' => $userToken,
                        'expires_at'    => now()->addSeconds($expiresIn),
                        'status'        => true,
                        'meta'          => ['auth_type' => 'instagram_business'],
                    ]
                );

                $created++;

                try { $this->syncChannelDetails($channel); } catch (\Throwable $e) {}
                try { $this->subscribeToWebhooks($channel); } catch (\Throwable $e) {}
                try { $this->backfillRecentConversations($channel); } catch (\Throwable $e) {}
            }
        }

        return ['success' => true, 'data' => ['instagram' => $created]];
    }
}
