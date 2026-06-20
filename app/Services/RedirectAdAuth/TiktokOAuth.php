<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Redirect;

class TiktokOAuth
{
    public function redirect($state)
    {
        $clientId = adminSetting('ads.tiktok.client_id');

        $tiktokAuthUrl = "https://business-api.tiktok.com/portal/auth?" . http_build_query([
            'app_id' => $clientId,
            'state' => $state,
            'grant_type' => 'authorization_code',
            'scope' => 'refresh_token',
            'redirect_uri' => $this->getCallbackUrl(),
        ]);

        return redirect()->away($tiktokAuthUrl);
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/tiktok/callback';
     //   return config('app.url') . '/admin/ads/tiktok/callback';
    }
}
