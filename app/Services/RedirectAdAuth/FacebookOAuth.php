<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Redirect;
use App\Models\AdAccount;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FacebookOAuth
{
    protected $platform, $adAccountModel, $mediaAccountModel, $config, $httpClient;

    public function __construct($platform, AdAccount $adAccountModel)
    {
        $this->platform = $platform;
        $this->adAccountModel = $adAccountModel;
        $this->config = config("services.ads.facebook");
        $this->httpClient =  Http::class;
    }

    public function redirect($platform, $state, $codeVerifier)
    {
        $clientId = $this->config['app_id'];

        return redirect("https://www.facebook.com/v25.0/dialog/oauth?client_id={$clientId}&redirect_uri={$this->getCallbackUrl()}&state={$state}&code_verifier={$codeVerifier}&scope=ads_management,ads_read");
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/facebook/callback';
     //   return config('app.url') . '/admin/ads/facebook/callback';
    }

    private function callback($platform)
    {
        $redirectUri = $this->getCallbackUrl();
        $code = request()->input('code');
        $endpoint = adminSetting('ads.facebook.endpoint.access_token');

        $response = $this->httpClient::get($endpoint, [
            'client_id' => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code, // this must be from the Facebook callback
        ]);

        $data = $response->json();

        if (!$response->successful()) {
           return $this->errorResponse($data);
        }

        $accessToken = data_get($data, 'access_token');
        $expiresIn = data_get($data, 'expires_in', 3600); // Default to 3600 seconds if not found
        $response = $this->getFBAdAccount($accessToken);

        $accountId = str_replace('act_', '', $response['facebook_account_id']);
        $instagramId = $response['instagram_account_id'];
    
        $InstagramDataToInsert = [
            'access_token' => $accessToken,
            'refresh_token_token' =>  data_get($data, 'refresh_token') ?? null,
            'account_id' => $instagramId,
            'expires_at' => Carbon::now()->addSeconds($expiresIn),
        ];
    }


    private function errorResponse($errors) {
        return ['success' => false, 'message' => $errors['data']];
    }


    private function getFBAdAccount($accessToken) {
        $endpoint = adminSetting('ads.facebook.account.endpoint');

        // Get Account 
        $response = $this->httpClient::get(
            $endpoint,
            [
                'fields' => 'id,name,account_id,account_status,currency',
                'access_token' => $accessToken,
            ]
        );

        $account = $response->json();
       
        if (!$response->successful()) {
            return $this->errorResponse($account['error']['error_user_title'] ?? $account['error']['message']);
        }

        $accounts = [];
        foreach ($account['data'] as $account) {
            if ($account['account_status']) {
                $accountId = $account['id'];
                $endpoint = adminSetting('ads.instagram.account.endpoint');
               // $this->config['base_url'] . $accountId . '/instagram_accounts',
                $response = $this->httpClient::get(
                    $this->config['base_url'] . $accountId . '/instagram_accounts',
                    [
                        'access_token' => $accessToken,
                    ]
                );

                $instaRes = $response->json();
                
                if (!$response->successful()) {
                    return $this->errorResponse($instaRes['error']['error_user_title'] ?? $instaRes['error']['message']);
                }

                if (!empty($response->json()['data'])) {
                    $accounts = [
                        'facebook_account_id' => $accountId,
                        'instagram_account_id' => $response->json()['data'][0]['id'],
                    ];

                    break;
                }
    
            }
        }

        return ['success' => true, 'facebook_account_id' => $accounts['facebook_account_id'], 'instagram_account_id' => $accounts['instagram_account_id'] ];
    }
}
