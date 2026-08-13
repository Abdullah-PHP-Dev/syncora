<?php

namespace App\Services\MessagingServices\Concerns;

use App\Models\Messaging\MessageChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

trait InstagramMessagingTrait
{
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

    protected function verifyInstagramSignature(Request $request, string $appSecret): bool
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
        $version = adminSetting('messaging.instagram.graph_version')
            ?: (adminSetting('messaging.meta.graph_version') ?: 'v21.0');

        return "https://graph.facebook.com/{$version}/" . ltrim($path, '/');
    }

    protected function graphApiCall(string $method, string $path, array $params, string $accessToken)
    {
        $headers = ['Authorization' => "Bearer {$accessToken}"];
        $url = $this->graphApiUrl($path);

        $response = match (strtoupper($method)) {
            'GET'   => $this->apiService->get($url, $headers, $params),
            'POST'  => $this->apiService->post($url, $headers, $params),
            default => ['success' => false, 'data' => null],
        };

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Graph API request failed.'];
        }

        return ['success' => true, 'data' => $response['data']];
    }

    protected function instagramRedirectUri(): string
    {
        return config('services.app_url') . '/admin/messaging/auth/instagram/callback';
    }

    /**
     * Updated: Facebook Business OAuth URL for Instagram Business Messaging
     */
    public function redirect($state)
    { 
        $version = adminSetting('messaging.meta.graph_version') ?: 'v21.0';

        $url = "https://www.facebook.com/{$version}/dialog/oauth?" . http_build_query([
            'client_id'     => (string) adminSetting('posts.facebook.client_id'),
            'redirect_uri'  => $this->instagramRedirectUri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'instagram_basic,instagram_manage_messages,pages_show_list,pages_read_engagement',
        ]);

        return Redirect::away($url);
    }

    /**
     * Updated: Code exchange to obtain Page Access Tokens
     */
    public function handleInstagramCallback(string $code): array
    {
        $clientId = adminSetting('posts.facebook.client_id');
        $clientSecret = adminSetting('posts.facebook.client_secret');

        // 1. Exchange short-lived token via graph.facebook.com
        $tokenResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $this->instagramRedirectUri(),
            'code'          => $code,
        ]);

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => $tokenResponse['data']['error']['message'] ?? 'Failed to exchange token code.'];
        }

        $shortLivedToken = $tokenResponse['data']['access_token'] ?? null;

        // 2. Exchange for 60-day Long-Lived Token
        $longLivedResponse = $this->apiService->get($this->graphApiUrl('oauth/access_token'), [], [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        $userToken = $longLivedResponse['success'] ? ($longLivedResponse['data']['access_token'] ?? $shortLivedToken) : $shortLivedToken;
        $expiresIn = $longLivedResponse['success'] ? ($longLivedResponse['data']['expires_in'] ?? 5184000) : 3600;

        // 3. Resolve Connected Instagram Business Accounts & Page Tokens
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
                $pageAccessToken = $page['access_token'];

                $channel = MessageChannel::updateOrCreate(
                    ['platform' => 'instagram', 'external_id' => $ig['id']],
                    [
                        'user_id'       => Auth::id(),
                        'name'          => $ig['name'] ?? $ig['username'] ?? 'Instagram Business',
                        'username'      => $ig['username'] ?? null,
                        'avatar_url'    => $ig['profile_picture_url'] ?? null,
                        'access_token'  => $pageAccessToken,
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