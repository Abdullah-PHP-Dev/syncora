<?php

namespace App\Services\AdServices;

/**
 * Instagram Ads - genuinely the same Meta Ads infrastructure as Facebook
 * (same App, same OAuth dialog/scopes, same Graph campaign endpoints, same
 * underlying act_X ad account - there is no separate "Instagram ad account"
 * namespace in the Marketing API). This class exists for two real reasons,
 * not to duplicate FacebookAdService:
 *
 * 1. "Connect Instagram" used to silently register the exact same
 *    redirect_uri as "Connect Facebook" (getCallbackUrl() was hardcoded
 *    to 'facebook' regardless of which tile was clicked) - both flows
 *    were indistinguishable to Meta and to this app's own routing/logs.
 *    Overriding getCallbackUrl() here gives Instagram its own real
 *    callback URL (admin/ads/instagram/callback). PHP resolves this
 *    polymorphically even though redirect()/callback() are only ever
 *    defined once, in the parent - both now correctly use whichever
 *    getCallbackUrl() belongs to the actual runtime class.
 *    IMPORTANT: this new URL must be added to the Facebook App's own
 *    "Valid OAuth Redirect URIs" list in the Developer Console before
 *    the Instagram tile is used, or Meta will reject it with
 *    redirect_uri_mismatch.
 *
 * 2. getInstagramBusinessAccount() (the ad-account-scoped
 *    /instagram_accounts edge) was confirmed on a real account to return
 *    empty even when Meta's own Business Settings shows the Instagram
 *    account as a connected asset of that ad account - undocumented
 *    behavior, not a bug in how that edge is called. Overriding
 *    getFBAdAccount() here merges in a second, already-proven-reliable
 *    source: each Page's own instagram_business_account field (the same
 *    mechanism SocialAuthService::callbackFacebook() already uses
 *    successfully for the non-ads connect flow), added to
 *    getBusinessPages()'s existing fields= list - no extra API call, since
 *    that data is already being fetched.
 *
 * Everything else - store()/update()/destroy()/updateStatus()/
 * syncCampaigns(), token refresh, appsecret_proof - is inherited
 * unchanged. Campaign management for an Instagram placement genuinely
 * has to run against the Facebook ad account (Meta targets Instagram via
 * publisher_platforms/instagram_actor_id on that same act_X account, not
 * a separate endpoint), so __construct() resolving $this->account via
 * platform='facebook' is correct here too, not an oversight.
 */
class InstagramAdService extends FacebookAdService
{
    protected function getCallbackUrl()
    {
        return oauthCallbackUrl('admin.ads.platform.callback', 'instagram');
    }

    protected function getFBAdAccount($accessToken)
    {
        $result = parent::getFBAdAccount($accessToken);

        if (!($result['success'] ?? false)) {
            return $result;
        }

        $result['accounts'] = array_map(function (array $item) {
            $fromPages = collect($item['pages'] ?? [])
                ->pluck('instagram_business_account')
                ->filter()
                ->map(fn (array $ig) => [
                    'id'          => $ig['id'],
                    'username'    => $ig['username'] ?? null,
                    'name'        => $ig['name'] ?? null,
                    // Normalized to match getInstagramBusinessAccount()'s
                    // own field name (profile_pic), which callback()'s
                    // save loop already reads.
                    'profile_pic' => $ig['profile_picture_url'] ?? null,
                ])
                ->values();

            $item['instagrams'] = collect($item['instagrams'] ?? [])
                ->concat($fromPages)
                ->unique('id')
                ->values()
                ->all();

            return $item;
        }, $result['accounts']);

        return $result;
    }
}
