<?php

// "Create a workspace" options (labels in lang/{en,ar}/workspaces.php) and
// which platforms each growth goal recommends. Platform keys are the
// Connections page cards (ConnectionHub.vue PLATFORMS).
return [
    'industries' => [
        'food_beverage', 'retail', 'real_estate', 'healthcare', 'beauty', 'automotive', 'education',
        'hospitality', 'technology', 'professional_services', 'nonprofit', 'other',
    ],

    // Country => currency (GCC first).
    'countries' => [
        'SA' => 'SAR', 'AE' => 'AED', 'KW' => 'KWD', 'QA' => 'QAR', 'BH' => 'BHD', 'OM' => 'OMR',
        'EG' => 'EGP', 'JO' => 'JOD', 'GB' => 'GBP', 'US' => 'USD',
    ],

    'timezones' => [
        'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Muscat',
        'Africa/Cairo', 'Asia/Amman', 'Europe/London', 'America/New_York', 'UTC',
    ],

    'languages' => ['ar', 'en', 'both'],

    // Where workspace logos are stored (public files, like email-template images).
    'logo_disk' => env('WORKSPACE_LOGO_DISK', 'r2'),

    'goals' => [
        'leads_whatsapp' => ['whatsapp'],
        'paid_ads' => ['meta_ads', 'instagram', 'tiktok'],
        'content_reach' => ['instagram', 'facebook', 'tiktok'],
        'answering_dms' => ['instagram', 'facebook', 'whatsapp'],
        'google_reviews' => ['business_profile'],
    ],

    // Display order when several goals recommend platforms.
    'platform_order' => ['whatsapp', 'instagram', 'facebook', 'meta_ads', 'tiktok', 'business_profile', 'youtube', 'linkedin', 'snapchat', 'x', 'threads'],
];
