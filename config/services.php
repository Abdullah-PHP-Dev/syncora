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
    'app_url' => 'https://beaelge.com/',
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
    // 'ads' => [
    //     'facebook' => [
    //         'app_id' => '910004983387413',
    //         'app_secret' => '641598ba8098464ff1b15d8e80c68d27'
    //     ],
    //     'instagram' => [
    //         'app_id' => '910004983387413',
    //         'app_secret' => '641598ba8098464ff1b15d8e80c68d27'
    //     ],
    //     'google' => [
    //         'app_id' => '805156061921-ljvqjli5sq75scg311r6m791l1mn4bor.apps.googleusercontent.com',
    //         'app_secret' => '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'
    //     ],
    //     'youtube' => [
    //         'app_id' => '805156061921-ljvqjli5sq75scg311r6m791l1mn4bor.apps.googleusercontent.com',
    //         'app_secret' => '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'
    //     ],
    //     'tiktok' => [
    //         'app_id' => '7480839935793037328',
    //         'app_secret' => 'c773f651a4e44b49c8e657738a3d50cf15bff365'
    //     ],
    //     'snapchat' => [
    //         'app_id' => '7f009f97-95d9-48ed-9cd8-4a1e2b4e8020',
    //         'app_secret' => '95f067f0eb1b26b954de'
    //     ],
    //     'x' => [
    //         'app_id' => 'GwMY8ViF593RKigiqWiHzN4Ao',
    //         'app_secret' => 'BbI5xjGEQsoSZFAleipk9O7sU7F1hsBQcNgtwrNZtzR57kT7B9'
    //     ],
    //     'linkedin' => [
    //         'app_id' => '',
    //         'app_secret' => ''
    //     ],
    // ]

];
