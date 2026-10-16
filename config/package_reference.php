<?php

// Allocation supplied in Screenshot 2026-10-17 at 2.24.45 AM.
// These are package settings, not claims of implemented seller capabilities.
return [
    'features' => [
        'publishing_accounts' => ['name_en' => 'Social accounts for publishing', 'name_ar' => 'حسابات النشر الاجتماعي', 'type' => 'capacity'],
        'advertising_accounts' => ['name_en' => 'Social accounts for advertising', 'name_ar' => 'حسابات الإعلانات الاجتماعية', 'type' => 'capacity'],
        'scheduled_published_posts' => ['name_en' => 'Scheduled/published posts', 'name_ar' => 'المنشورات المجدولة والمنشورة', 'type' => 'quota'],
        'ai_credits' => ['name_en' => 'AI credits', 'name_ar' => 'رصيد الذكاء الاصطناعي', 'type' => 'quota'],
        'hashtag_generation' => ['name_en' => 'Hashtag generation', 'name_ar' => 'توليد الوسوم', 'type' => 'quota'],
        'comments_replies' => ['name_en' => 'Comments and replies', 'name_ar' => 'التعليقات والردود', 'type' => 'benefit'],
        'messaging_inbox_access' => ['name_en' => 'Unified messaging inbox', 'name_ar' => 'صندوق الرسائل الموحد', 'type' => 'benefit'],
        'analytics_history_days' => ['name_en' => 'Analytics history (days)', 'name_ar' => 'سجل التحليلات (أيام)', 'type' => 'capacity'],
        'media_storage_gb' => ['name_en' => 'Media Gallery (GB)', 'name_ar' => 'معرض الوسائط (جيجابايت)', 'type' => 'capacity'],
        'team_members' => ['name_en' => 'Team members', 'name_ar' => 'أعضاء الفريق', 'type' => 'capacity'],
        'content_categories' => ['name_en' => 'Content categories', 'name_ar' => 'تصنيفات المحتوى', 'type' => 'capacity'],
        'post_approval' => ['name_en' => 'Post approval', 'name_ar' => 'الموافقة على المنشورات', 'type' => 'benefit'],
        'data_export' => ['name_en' => 'Data export', 'name_ar' => 'تصدير البيانات', 'type' => 'benefit'],
        'data_import' => ['name_en' => 'Data import', 'name_ar' => 'استيراد البيانات', 'type' => 'benefit'],
        'google_analytics' => ['name_en' => 'Google Analytics integration', 'name_ar' => 'تكامل Google Analytics', 'type' => 'boolean'],
        'email_marketing_access' => ['name_en' => 'Email Marketing', 'name_ar' => 'التسويق بالبريد الإلكتروني', 'type' => 'benefit'],
        'ai_agent_access' => ['name_en' => 'AI Agent', 'name_ar' => 'وكيل الذكاء الاصطناعي', 'type' => 'benefit'],
        'support_level' => ['name_en' => 'Priority support', 'name_ar' => 'الدعم ذو الأولوية', 'type' => 'benefit'],
    ],
    'packages' => [
        'free_trial' => [
            'aliases' => [], 'name_en' => 'Free Trial', 'name_ar' => 'التجربة المجانية', 'price' => 0,
            'description_en' => 'Explore the essentials with a 14-day trial.', 'description_ar' => 'اكتشف الميزات الأساسية خلال تجربة مجانية لمدة 14 يوماً.',
            'period' => 'trial',
            'values' => [2, 1, 15, 100, 10, 'Limited', 'Preview', 7, 1, 1, 2, 'No', 'No', 'No', false, 'No', 'No', 'No'],
        ],
        'basic' => [
            'aliases' => ['starter'], 'name_en' => 'Basic', 'name_ar' => 'الأساسية', 'price' => 49,
            'description_en' => 'The essentials for building a consistent social presence.', 'description_ar' => 'الأساسيات لبناء حضور منتظم على شبكات التواصل.',
            'period' => 'monthly',
            'values' => [4, 1, 50, 100, 30, 'Basic', 'Basic', 30, 5, 1, 5, 'No', 'Basic', 'No', false, 'Add-on', 'No', 'Standard'],
        ],
        'pro' => [
            'aliases' => [], 'name_en' => 'Pro', 'name_ar' => 'الاحترافية', 'price' => 149,
            'description_en' => 'More capacity and advanced tools for growing teams.', 'description_ar' => 'سعة أكبر وأدوات متقدمة للفرق المتنامية.',
            'period' => 'monthly',
            'values' => [10, 5, 300, 500, 150, 'Expanded', 'Full supported channels', 90, 25, 3, 20, 'Basic', 'Yes', 'Limited', true, 'Add-on or included allowance', 'Limited', 'Priority'],
        ],
        'business' => [
            'aliases' => ['empire'], 'name_en' => 'Business', 'name_ar' => 'الأعمال', 'price' => 399,
            'description_en' => 'Scale your content, collaboration, and customer engagement.', 'description_ar' => 'وسّع نطاق المحتوى والتعاون والتواصل مع العملاء.',
            'period' => 'monthly',
            'values' => [25, 15, 1000, 2000, 500, 'Expanded', 'Full supported channels', 365, 100, 10, -1, 'Multi-user workflow', 'Yes', 'Yes', true, 'Included allowance', 'Advanced', 'Priority'],
        ],
    ],
];
