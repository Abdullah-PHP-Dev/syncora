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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payment' => [

	    'tap' => [
		    'secret_key'  => env('TAP_SECRET_KEY'),
		    'public_key'  => env('TAP_PUBLIC_KEY'),
		    'merchant_id' => env('TAP_MERCHANT_ID'),
	    ]
    ],
    // No trailing slash - every consumer of this value (OAuth redirect_uri
    // builders across the Messaging and Ads modules) appends a leading-
    // slash path directly, eg. config('services.app_url') . '/admin/...'.
    // A trailing slash here produced a double slash in every callback URL,
    // which would mismatch the redirect_uri registered with each OAuth
    // provider (Meta/X/Zalo/Slack/etc all require an exact match).
    'app_url' => 'https://socialeaz.com',
    // 'posts' => [
    //     'facebook' => [
    //         'app_id' => '910004983387413',
    //         'app_secret' => '641598ba8098464ff1b15d8e80c68d27'
    //     ],
    //     'instagram' => [
    //         'app_id' => '910004983387413',
    //         'app_secret' => '641598ba8098464ff1b15d8e80c68d27'
    //     ],
    //     'google' => [
    //         'app_id' => '816293969961-ltb1on97624jlakpmq8vg0quhokklse4.apps.googleusercontent.com',
    //         'app_secret' => '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'
    //     ],
    //     'youtube' => [
    //         'app_id' => '816293969961-ltb1on97624jlakpmq8vg0quhokklse4.apps.googleusercontent.com',
    //         'app_secret' => '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'
    //     ],
    //     'tiktok' => [
    //         'app_id' => 'aw20yzb705kyz78h',
    //         'app_secret' => 'FvNALEGWJNaxLITEWcdwGuyMfkMDCmtC'
    //     ],
    //     'snapchat' => [
    //         'app_id' => '5a193846-d0e2-4da9-9429-bddafbc718b4',
    //         'app_secret' => '623be262fee31a2b9798'
    //     ],
    //     'x' => [
    //         'app_id' => 'b2VNVDczTVp0d3BwSEVRSkYtUXM6MTpjaQ',
    //         'app_secret' => 'M0kv37Wykr6dAuEmk1Seekg1HaHEoR07JfjDF2e-RCPP6L1OxW'
    //     ],
    //     'linkedin' => [
    //         'app_id' => '',
    //         'app_secret' => ''
    //     ],
    // ],

];
