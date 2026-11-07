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
        'tiktok' => App\Services\Connections\Drivers\TikTokDriver::class,
        'snapchat' => App\Services\Connections\Drivers\SnapchatDriver::class,
        'threads' => App\Services\Connections\Drivers\ThreadsDriver::class,
        'pinterest' => App\Services\Connections\Drivers\PinterestDriver::class,
        'openai' => App\Services\Connections\Drivers\OpenAiDriver::class,
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
        'google.business_profile' => true, // Business Profile API access approved; business.manage is on the verified consent screen.
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
            'engagement' => ['pages_manage_engagement', 'instagram_manage_comments', 'instagram_business_manage_comments'],
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

        'tiktok' => [
            'posting' => ['video.publish', 'video.upload'],
            'messaging' => [],
            'ads' => [],
            'insights' => ['user.info.stats', 'video.list'],
        ],

        'snapchat' => [
            'posting' => [],
            'messaging' => [],
            'ads' => ['snapchat-marketing-api'],
            'insights' => [],
        ],

        'threads' => [
            'posting' => ['threads_content_publish'],
            'messaging' => [],
            'ads' => [],
            'insights' => ['threads_manage_insights'],
        ],

        'pinterest' => [
            'posting' => ['pins:write'],
            'messaging' => [],
            'ads' => [],
            'insights' => ['user_accounts:read'],
        ],

        // Google grants full scope URLs.
        'google' => [
            'posting' => ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube', 'https://www.googleapis.com/auth/business.manage'],
            'messaging' => [],
            'ads' => ['https://www.googleapis.com/auth/adwords'],
            'insights' => ['https://www.googleapis.com/auth/analytics.readonly', 'https://www.googleapis.com/auth/webmasters.readonly', 'https://www.googleapis.com/auth/yt-analytics.readonly', 'https://www.googleapis.com/auth/business.manage'],
        ],

    ],

    /*
    | Capability registry (docs/connection-capabilities.md): what a customer
    | can pick on a platform's panel, and the consent (driver step) that
    | grants it. Only capabilities SocialEaz has a module for are listed.
    |
    |   step      driver step whose consent grants it; null = no consent can
    |   approval  flag that is on once the provider approved the app for it
    |             (off -> "Additional approval required", not selectable)
    |   partial   true when only part of the capability works (see the note)
    |
    | Texts: lang connections.catalog.<platform>.<capability>.{account,note}.
    */
    'catalog' => [

        'meta' => [
            'posting' => ['step' => App\Services\Connections\Drivers\MetaDriver::LOGIN],
            'ads' => ['step' => App\Services\Connections\Drivers\MetaDriver::LOGIN],
            // Messenger + Instagram DMs; WhatsApp is its own sign-in.
            'messaging' => ['step' => App\Services\Connections\Drivers\MetaDriver::LOGIN, 'partial' => true],
            'engagement' => ['step' => App\Services\Connections\Drivers\MetaDriver::LOGIN],
            'insights' => ['step' => App\Services\Connections\Drivers\MetaDriver::LOGIN],
        ],

        'google' => [
            'posting' => ['step' => App\Services\Connections\Drivers\GoogleDriver::OAUTH],
            'ads' => ['step' => App\Services\Connections\Drivers\GoogleDriver::OAUTH],
            'insights' => ['step' => App\Services\Connections\Drivers\GoogleDriver::OAUTH],
            // Comment replies need youtube.force-ssl, which isn't on the
            // verified consent screen (SocialAuthService::redirectGoogle).
            'engagement' => ['step' => null],
        ],

        'x' => [
            'posting' => ['step' => App\Services\Connections\Drivers\XDriver::OAUTH2],
            'messaging' => ['step' => App\Services\Connections\Drivers\XDriver::OAUTH2],
            'ads' => ['step' => App\Services\Connections\Drivers\XDriver::ADS, 'approval' => 'x.ads'],
        ],

        'linkedin' => [
            'posting' => ['step' => App\Services\Connections\Drivers\LinkedInDriver::PAGES],
            'insights' => ['step' => App\Services\Connections\Drivers\LinkedInDriver::PAGES, 'partial' => true],
            'ads' => ['step' => App\Services\Connections\Drivers\LinkedInDriver::ADS],
            // LinkedIn has no page-messaging API.
            'messaging' => ['step' => null],
        ],

        'tiktok' => [
            'posting' => ['step' => App\Services\Connections\Drivers\TikTokDriver::LOGIN_KIT],
            'insights' => ['step' => App\Services\Connections\Drivers\TikTokDriver::LOGIN_KIT],
            'engagement' => ['step' => App\Services\Connections\Drivers\TikTokDriver::COMMENTS],
            'ads' => ['step' => App\Services\Connections\Drivers\TikTokDriver::BUSINESS],
            'messaging' => ['step' => null, 'approval' => 'tiktok.business_messaging'],
        ],

        'snapchat' => [
            'ads' => ['step' => App\Services\Connections\Drivers\SnapchatDriver::MARKETING],
            'posting' => ['step' => null, 'approval' => 'snapchat.public_profile'],
        ],

        'threads' => [
            'posting' => ['step' => App\Services\Connections\Drivers\ThreadsDriver::LOGIN],
        ],

        'pinterest' => [
            'posting' => ['step' => App\Services\Connections\Drivers\PinterestDriver::LOGIN],
            'insights' => ['step' => App\Services\Connections\Drivers\PinterestDriver::LOGIN],
        ],

    ],

    /*
    | A step whose consent belongs to a different API product than the
    | platform's main one, keyed by step: its scopes are mapped here instead
    | of by `capabilities` above. TikTok's account-holder consent (Accounts
    | API) lists video.publish and user.info.stats too, but this app posts
    | and reads stats through Login Kit - only its comments are used.
    */
    'step_capabilities' => [
        App\Services\Connections\Drivers\TikTokDriver::COMMENTS => [
            'engagement' => ['comment.list', 'comment.list.manage'],
        ],
    ],

];
