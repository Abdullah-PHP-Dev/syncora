<?php

namespace App\Services\AdServices;

use Illuminate\Support\Facades\Redirect;

class InstagramAdService
{
    public function redirect($platform, $state, $codeVerifier)
    {
        $clientId = adminSetting('ads.google.client_id');
        
        return redirect("https://www.facebook.com/v25.0/dialog/AdService?client_id={$clientId}&redirect_uri={$this->getCallbackUrl()}&state={$state}&code_verifier={$codeVerifier}&scope=ads_management,ads_read");
    }

    /**
     * Dead code - this class is never instantiated anywhere
     * (SocialAdManagerService's platformMap routes 'instagram' Ads to
     * FacebookAdService instead). Fixed for consistency with every other
     * platform's callback URL anyway, in case this is ever wired up.
     */
    private function getCallbackUrl()
    {
        return oauthCallbackUrl('admin.ads.platform.callback', 'facebook');
    }
}
