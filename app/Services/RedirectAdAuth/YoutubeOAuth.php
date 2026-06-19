<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Redirect;

class YoutubeOAuth
{
    public function redirect($state)
    {
        $clientId = config('services.ads.google.app_id');

        $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([

            'client_id' => $clientId,
            'access_type' => 'offline',
            'redirect_uri' => $this->getCallbackUrl(),
            'response_type' => 'code',
            'state' => $state,
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/adwords'
            ]),
            'prompt' => 'consent'
        ]);

        return Redirect::away($url);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/google/callback';
     //   return config('app.url') . '/admin/ads/google/callback';
    }
}
