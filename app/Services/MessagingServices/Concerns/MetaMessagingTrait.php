<?php

namespace App\Services\MessagingServices\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Required on every server-side Graph call authenticated via an access
     * token whenever the Meta App has "Require App Secret" enabled
     * (App Dashboard > Settings > Advanced) - without it Graph rejects the
     * call with "API calls from the server require an appsecret_proof
     * argument" regardless of how valid the access token itself is. Same
     * App Secret already used for X-Hub-Signature-256 verification above
     * (posts.facebook.client_secret) - Messenger/Instagram/WhatsApp
     * genuinely share one Meta App, confirmed by their own signature checks
     * already all reading this same setting.
     */
    protected function metaAppSecretProof(string $accessToken): string
    {
        return hash_hmac('sha256', $accessToken, (string) adminSetting('posts.facebook.client_secret'));
    }

    protected function graphApiCall(string $method, string $path, array $params, string $accessToken)
    {
        $headers = ['Authorization' => "Bearer {$accessToken}"];
        $url = $this->graphApiUrl($path);
        $params['appsecret_proof'] = $this->metaAppSecretProof($accessToken);

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
