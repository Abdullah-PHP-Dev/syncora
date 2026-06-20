<?php

namespace App\Services\RedirectAdAuth;

use Illuminate\Support\Facades\Redirect;

class InstagramOAuth
{
    public function redirect($platform, $state, $codeVerifier)
    {
        $clientId = adminSetting('ads.google.client_id');

        return redirect("https://www.facebook.com/v25.0/dialog/oauth?client_id={$clientId}&redirect_uri={$this->getCallbackUrl()}&state={$state}&code_verifier={$codeVerifier}&scope=ads_management,ads_read");
    }

    private function getCallbackUrl()
    {
        return config('services.app_url') . '/admin/social/auth/facebook/callback';
     //   return config('app.url') . '/admin/ads/facebook/callback';
    }
}
