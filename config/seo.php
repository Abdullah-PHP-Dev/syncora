<?php

return [
    'base_url' => env('SEO_BASE_URL', 'https://socialeaz.com'),
    'locales' => ['en', 'ar'],
    'default_locale' => 'en',
    'indexable' => env('SEO_INDEXABLE', true),
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_verification' => env('BING_SITE_VERIFICATION'),
    // GA4 measurement ID fallback - the admin setting google.analytics.measurement_id
    // takes precedence. Rendered on the production host only.
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    // Google Tag Manager container. Rendered on the production host only.
    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID', 'GTM-53W9VXBN'),
];
