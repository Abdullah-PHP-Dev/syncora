<?php

namespace App\Services\MessagingServices\Concerns;

use Illuminate\Http\Request;

/**
 * Instagram Direct's own webhook verify token / app secret
 * (messaging.instagram.*), kept independent from Facebook Messenger's
 * messaging.meta.* settings (see MetaMessagingTrait) so Instagram can be
 * configured under a different Meta App - or just with its own dedicated
 * webhook credentials - without being forced to share Facebook's.
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
            'GET'  => $this->apiService->get($url, $headers, $params),
            'POST' => $this->apiService->post($url, $headers, $params),
            default => ['success' => false, 'data' => null],
        };

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error']['message'] ?? 'Graph API request failed.'];
        }

        return ['success' => true, 'data' => $response['data']];
    }
}
