<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;

class SnapchatOAuth
{
    public function redirect($state)
    {
        $clientId = adminSetting('ads.snapchat.client_id');

        $url = 'https://accounts.snapchat.com/login/oauth2/authorize?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $this->getCallbackUrl(),
            'response_type' => 'code',
            'scope'         => 'snapchat-marketing-api snapchat-profile-api',
            'state'         => $state,
        ]);
        
        return Redirect::to($url);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/snapchat/callback';
     //   return config('app.url') . '/admin/ads/snapchat/callback';
    }
}
