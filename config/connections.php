<?php

/*
|--------------------------------------------------------------------------
| Connection Hub
|--------------------------------------------------------------------------
| docs/connection-hub-design.md. `capabilities` maps each capability to the
| granted scopes (any-of) that unlock it, per platform; drivers and the
| backfill derive SocialConnection::capabilities from granted scopes with it.
*/

return [

    // Platform key => ProviderDriver. Platforms are added as their drivers land.
    'drivers' => [
        'meta' => App\Services\Connections\Drivers\MetaDriver::class,
        'google' => App\Services\Connections\Drivers\GoogleDriver::class,
        'x' => App\Services\Connections\Drivers\XDriver::class,
        'linkedin' => App\Services\Connections\Drivers\LinkedInDriver::class,
    ],

    /*
    | Every "Unclear" row of the audit is a flag (design §8), so the Hub can
    | change shape without a redesign. Override any of them per environment
    | with the admin setting `connections.flags.<flag>`.
    */
    'flags' => [
        'meta.whatsapp_in_main_config' => false,
        'meta.instagram_login' => true,
        'google.oauth_client' => 'posts',
        'google.business_profile' => false,
        'linkedin.single_app' => false,
        'tiktok.business_messaging' => false,
        'tiktok.business_account_posting' => false,
        'snapchat.public_profile' => false,
        'snapchat.combined_scopes' => false,
        'x.ads' => true,
    ],

    'capabilities' => [

        'meta' => [
            'posting' => ['pages_manage_posts', 'instagram_content_publish', 'instagram_business_content_publish', 'whatsapp_business_messaging'],
            'messaging' => ['pages_messaging', 'instagram_manage_messages', 'instagram_business_manage_messages', 'whatsapp_business_messaging'],
            'ads' => ['ads_management', 'ads_read'],
            'insights' => ['read_insights', 'instagram_manage_insights', 'instagram_business_manage_insights'],
        ],

        // X: posts.x OAuth 2.0 scopes; the OAuth 1.0a Ads consent is the
        // GrantedScopes::OAUTH1 marker (no scopes in OAuth 1.0a).
        'x' => [
            'posting' => ['tweet.write'],
            'messaging' => ['dm.write', 'dm.read'],
            'ads' => ['oauth1'],
            'insights' => [],
        ],

        'linkedin' => [
            'posting' => ['w_organization_social', 'w_member_social'],
            'messaging' => [],
            'ads' => ['rw_ads', 'r_ads'],
            'insights' => ['r_organization_admin', 'r_organization_social', 'r_ads_reporting'],
        ],

        // Google grants full scope URLs.
        'google' => [
            'posting' => ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube'],
            'messaging' => [],
            'ads' => ['https://www.googleapis.com/auth/adwords'],
            'insights' => ['https://www.googleapis.com/auth/analytics.readonly', 'https://www.googleapis.com/auth/yt-analytics.readonly', 'https://www.googleapis.com/auth/business.manage'],
        ],

    ],

];
