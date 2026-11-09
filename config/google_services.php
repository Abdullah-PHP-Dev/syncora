<?php

use App\Services\Connections\Drivers\GoogleDriver;

/*
|--------------------------------------------------------------------------
| Google services - the single source of Google scopes
|--------------------------------------------------------------------------
| Every Google product SocialEaz offers in the Connection Hub, how it is
| authorised, and the exact scopes it needs. SocialAuthService (main
| Google consent), GoogleSuiteScopes (read-only Analytics consent) and
| GoogleServiceStatus all read this file - scopes are never hard-coded
| elsewhere.
|
| auth:
|   oauth   - the user's Google consent. `step` is the GoogleDriver consent it
|             belongs to (same OAuth client, same callback URL). Connecting one
|             service asks only for its scopes; include_granted_scopes keeps
|             everything granted earlier (Google OAuth 2.0: incremental
|             authorization, "request scopes in context").
|   api_key - no Google sign-in (PageSpeed Insights is a public API).
|   none    - not integrated; shown as "Not available yet", never as connected.
|
| asset_kind: the Hub asset group (GoogleDriver::assetKind) the service's
| accounts appear in. flag: a connections.flags switch that must be on.
*/

return [

    // Always requested with a main-consent service: identifies which Google
    // account granted it (ID token `sub`), so several Google accounts can be
    // connected side by side. Non-sensitive; the Analytics consent on the same
    // OAuth client already requests them.
    'identity_scopes' => ['openid', 'email'],

    // A main consent started without choosing a service (Ads dashboard, older
    // entry points) keeps requesting the full verified set, as before.
    'default_extra_scopes' => [
        GoogleDriver::OAUTH => ['https://www.googleapis.com/auth/analytics.readonly'],
    ],

    'services' => [
        'youtube' => [
            'auth' => 'oauth',
            'step' => GoogleDriver::OAUTH,
            'scopes' => ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube'],
            'asset_kind' => 'youtube',
            'icon' => 'bxl-youtube',
            'brand' => 'youtube',
            'manage_route' => ['admin.posts.index', ['platform' => 'youtube']],
        ],
        'google_ads' => [
            'auth' => 'oauth',
            'step' => GoogleDriver::OAUTH,
            'scopes' => ['https://www.googleapis.com/auth/adwords'],
            // Ads accounts connected through the earlier Ads-only Google app still count.
            'legacy_steps' => [GoogleDriver::ADS_LEGACY],
            'asset_kind' => 'google_ads',
            'icon' => 'bx-bullseye',
            'brand' => 'google',
            'manage_route' => ['admin.ads.campaigns.index', ['platform' => 'google']],
        ],
        'business_profile' => [
            'auth' => 'oauth',
            'step' => GoogleDriver::OAUTH,
            'scopes' => ['https://www.googleapis.com/auth/business.manage'],
            'asset_kind' => 'business_profile',
            'flag' => 'google.business_profile',
            'icon' => 'bx-store',
            'brand' => 'google',
            'manage_route' => ['admin.google.business-profile', []],
        ],
        'analytics' => [
            'auth' => 'oauth',
            'step' => GoogleDriver::ANALYTICS,
            'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
            'icon' => 'bx-line-chart',
            'brand' => 'google',
            'manage_route' => ['admin.google.analytics', []],
        ],
        'search_console' => [
            'auth' => 'oauth',
            'step' => GoogleDriver::ANALYTICS,
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'icon' => 'bx-search-alt',
            'brand' => 'google',
            'manage_route' => ['admin.google.search-console', []],
        ],
        'pagespeed' => [
            'auth' => 'api_key',
            'icon' => 'bx-tachometer',
            'brand' => 'google',
            'manage_route' => ['admin.google.website-performance', []],
        ],
        'tag_manager' => [
            'auth' => 'none',
            'icon' => 'bx-purchase-tag-alt',
            'brand' => 'google',
            'manage_route' => ['admin.google.tag-manager', []],
        ],
        'maps' => [
            'auth' => 'none',
            'icon' => 'bx-map',
            'brand' => 'google',
            'manage_route' => null,
        ],
    ],
];
