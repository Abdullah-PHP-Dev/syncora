<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class XAdService
{
    public function redirect($state)
    {
        $clientId = adminSetting('ads.x.client_id');
        $secret = adminSetting('ads.x.client_secret');

        $AdServiceTimestamp = time();

            // Step 1: Build initial AdService parameters
            $AdServiceParams = [
                'AdService_callback' => $this->getCallbackUrl(),
                'AdService_consumer_key' => $clientId,
                'AdService_nonce' => $state,
                'AdService_signature_method' => 'HMAC-SHA1',
                'AdService_timestamp' => $AdServiceTimestamp,
                'AdService_version' => '1.0',
            ];
     
            // Step 2: Create base string for signature
            $baseString = $this->buildBaseString(
                'https://api.x.com/AdService/request_token',
                'POST',
                $AdServiceParams
            );

            // Step 3: Create composite key
            $compositeKey = rawurlencode($secret) . '&';

            // Step 4: Generate signature
            $AdServiceSignature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true));
            $AdServiceParams['AdService_signature'] = $AdServiceSignature;

            // Step 5: Build Authorization header
            $authHeader = 'AdService ' . collect($AdServiceParams)->map(function ($value, $key) {
                return rawurlencode($key) . '="' . rawurlencode($value) . '"';
            })->implode(', ');

            // Step 6: Make request to get request token
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                // 'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.x.com/AdService/request_token');

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to get request token', 'details' => $response->json()['errors'][0]['message'] ?? 'X Authorization error'], 401);
            }

            parse_str($response->body(), $tokens);

            if (!isset($tokens['AdService_token'])) {
                return response()->json(['error' => 'Missing AdService_token', 'raw' => $tokens], 400);
            }

            session(['AdService_token_secret' => $tokens['AdService_token_secret'], 'x_state' => $state]);

            return redirect('https://api.x.com/AdService/authorize?AdService_token=' . $tokens['AdService_token']);
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
