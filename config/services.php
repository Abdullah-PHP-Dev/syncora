<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // The platform's own Cloudflare zone - used by CloudflareDnsService to
    // automate DNS record creation for SendGrid Domain Authentication.
    // Not a per-seller credential: only domains that actually resolve
    // under this one zone get automatic DNS (validated live against
    // Cloudflare's own API, never assumed) - every other seller domain
    // keeps using the existing manual DNS-instructions flow untouched.
    'cloudflare' => [
        'api_token'  => env('CLOUDFLARE_API_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'zone_id'    => env('CLOUDFLARE_ZONE_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payment' => [

        'tamara' => [
            'base_url' => env('TAMARA_BASE_URL'),
            'api_token' => env('TAMARA_API_TOKEN'),
            'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
            'payment_type' => env('TAMARA_PAYMENT_TYPE'),
            'instalments' => env('TAMARA_INSTALMENTS', 3),
        ],

	    'tap' => [
		    'secret_key'  => env('TAP_SECRET_KEY'),
		    'public_key'  => env('TAP_PUBLIC_KEY'),
		    'merchant_id' => env('TAP_MERCHANT_ID'),
	    ]
    ],
    // No trailing slash - every consumer of this value (OAuth redirect_uri
    // builders across the Messaging and Ads modules) appends a leading-
    // slash path directly, eg. config('services.app_url') . '/...'.
    // A trailing slash here produced a double slash in every callback URL,
    // which would mismatch the redirect_uri registered with each OAuth
    // provider (Meta/X/Zalo/Slack/etc all require an exact match).
    'app_url' => 'https://socialeaz.com',

    /*
    | Localhost X Chat worker (xchat-worker/, X's official Chat XDK) - decrypts
    | encrypted X Chat webhooks and encrypts replies. Never expose it publicly.
    */
    'xchat_worker' => [
        'url'     => env('XCHAT_WORKER_URL', 'http://127.0.0.1:8790'),
        'token'   => env('XCHAT_WORKER_TOKEN'),
        'timeout' => (int) env('XCHAT_WORKER_TIMEOUT', 20),
    ],

];
