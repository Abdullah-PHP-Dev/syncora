<?php

return [
    'social_posts' => ['name_en' => 'Social publishing', 'name_ar' => 'النشر الاجتماعي', 'type' => 'boolean', 'description' => 'Create and publish social posts.', 'default' => true],
    'ads_manager' => ['name_en' => 'Ads manager', 'name_ar' => 'إدارة الإعلانات', 'type' => 'boolean', 'description' => 'Access advertising dashboards and campaign tools.', 'default' => true],
    'messaging' => ['name_en' => 'Unified inbox', 'name_ar' => 'صندوق الوارد الموحد', 'type' => 'boolean', 'description' => 'Access conversations and send replies.', 'default' => true],
    'ai_copilot' => ['name_en' => 'AI Copilot', 'name_ar' => 'مساعد الذكاء الاصطناعي', 'type' => 'boolean', 'description' => 'Use AI suggestions, content generation, and automatic replies.', 'default' => true],
    'email_marketing' => ['name_en' => 'Email marketing', 'name_ar' => 'التسويق بالبريد الإلكتروني', 'type' => 'boolean', 'description' => 'Manage email campaigns, contacts, and templates.', 'default' => true],
    'integrations' => ['name_en' => 'Integrations', 'name_ar' => 'التكاملات', 'type' => 'boolean', 'description' => 'Configure third-party integrations.', 'default' => true],
    'social_accounts' => ['name_en' => 'Connected social accounts', 'name_ar' => 'الحسابات الاجتماعية المتصلة', 'type' => 'capacity', 'description' => 'Maximum connected accounts across all platforms. Existing accounts are retained.', 'default' => -1],
    'posts_per_cycle' => ['name_en' => 'Posts created per billing cycle', 'name_ar' => 'المنشورات المنشأة لكل دورة فوترة', 'type' => 'quota', 'description' => 'Each new post destination counts once, including drafts. Imported historical posts do not count. Deleting a post does not refund usage.', 'default' => -1],
];
