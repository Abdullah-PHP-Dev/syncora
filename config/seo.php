<?php

return [
    'base_url' => env('SEO_BASE_URL', 'https://socialeaz.com'),
    'locales' => ['en', 'ar'],
    'default_locale' => 'en',
    'indexable' => env('SEO_INDEXABLE', true),
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_verification' => env('BING_SITE_VERIFICATION'),
];
