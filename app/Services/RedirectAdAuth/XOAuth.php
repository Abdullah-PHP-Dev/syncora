<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class XOAuth
{
    public function redirect($state)
    {
        $clientId = adminSetting('ads.x.client_id');
        $secret = adminSetting('ads.x.client_secret');

        $oauthTimestamp = time();

            // Step 1: Build initial OAuth parameters
            $oauthParams = [
                'oauth_callback' => $this->getCallbackUrl(),
                'oauth_consumer_key' => $clientId,
                'oauth_nonce' => $state,
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => $oauthTimestamp,
                'oauth_version' => '1.0',
            ];
     
            // Step 2: Create base string for signature
            $baseString = $this->buildBaseString(
                'https://api.x.com/oauth/request_token',
                'POST',
                $oauthParams
            );

            // Step 3: Create composite key
            $compositeKey = rawurlencode($secret) . '&';

            // Step 4: Generate signature
            $oauthSignature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true));
            $oauthParams['oauth_signature'] = $oauthSignature;

            // Step 5: Build Authorization header
            $authHeader = 'OAuth ' . collect($oauthParams)->map(function ($value, $key) {
                return rawurlencode($key) . '="' . rawurlencode($value) . '"';
            })->implode(', ');

            // Step 6: Make request to get request token
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                // 'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.x.com/oauth/request_token');

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to get request token', 'details' => $response->json()['errors'][0]['message'] ?? 'X Authorization error'], 401);
            }

            parse_str($response->body(), $tokens);

            if (!isset($tokens['oauth_token'])) {
                return response()->json(['error' => 'Missing oauth_token', 'raw' => $tokens], 400);
            }

            session(['oauth_token_secret' => $tokens['oauth_token_secret'], 'x_state' => $state]);

            return redirect('https://api.x.com/oauth/authorize?oauth_token=' . $tokens['oauth_token']);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/x/callback';
     //   return config('app.url') . '/admin/ads/x/callback';
    }

    private function buildBaseString($baseURI, $method, $params)
    {
        ksort($params);
        $r = [];
        foreach ($params as $key => $value) {
            $r[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return $method . '&' . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
    }
}
