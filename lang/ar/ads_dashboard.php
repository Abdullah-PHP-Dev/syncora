<?php

/*
 * لوحة الإعلانات (/ads/dashboard) - same keys as lang/en/ads_dashboard.php.
 */
return [
    'title_all' => 'لوحة الإعلانات',
    'title_platform' => 'إعلانات :platform',
    'subtitle_all' => 'الحملات والحسابات الإعلانية والميزانيات عبر جميع المنصات الإعلانية التي تستخدمها.',
    'subtitle_platform' => 'حملات :platform وحساباتها الإعلانية وميزانياتها.',
    'chip_active' => '{0} لا توجد حملات نشطة|{1} حملة نشطة واحدة|{2} حملتان نشطتان|[3,10] :count حملات نشطة|[11,*] :count حملة نشطة',
    'chip_daily' => ':amount يومياً مُعدّة',
    'chip_accounts' => '{0} لا توجد حسابات إعلانية|{1} حساب إعلاني واحد|{2} حسابان إعلانيان|[3,10] :count حسابات إعلانية|[11,*] :count حساباً إعلانياً',
    'all_campaigns' => 'جميع حملات :platform',
    'create_campaign' => 'إنشاء حملة',

    'empty_title' => 'لا توجد حسابات إعلانية بعد',
    'empty_text' => 'اربط حساباً إعلانياً من صفحة الاتصالات وستظهر هنا حملاته وحساباته الإعلانية وميزانياته.',
    'go_connections' => 'الانتقال إلى الاتصالات',

    'platforms_aria' => 'المنصات',
    'all_platforms' => 'جميع المنصات',
    'needs_reconnect' => ':platform - يحتاج إلى إعادة الربط',

    'kpi' => [
        'total' => 'إجمالي الحملات',
        'total_sub' => ':groups مجموعة إعلانية · :ads إعلان',
        'active' => 'الحملات النشطة',
        'active_sub' => ':count متوقفة',
        'daily' => 'الميزانية اليومية',
        'lifetime' => 'الميزانية الإجمالية',
        'configured' => 'المُعدّة عبر الحملات',
    ],
    'strip' => ['accounts' => 'الحسابات الإعلانية', 'ad_groups' => 'المجموعات الإعلانية', 'ads' => 'الإعلانات', 'creatives' => 'التصاميم الإعلانية'],

    'comparison' => 'مقارنة المنصات',
    'comparison_sub' => 'حصة الحملات والميزانية اليومية المُعدّة',
    'comparison_none' => 'لم تُنشأ أي حملات على أي منصة بعد.',
    'campaign_count' => '{0} لا حملات|{1} حملة واحدة|{2} حملتان|[3,10] :count حملات|[11,*] :count حملة',
    'per_day_configured' => ':amount/يومياً مُعدّة',

    'recent' => 'أحدث الحملات',
    'recent_sub' => 'الأحدث عبر جميع المنصات',
    'platform_campaigns' => 'حملات :platform',
    'platform_campaigns_sub' => 'كل حملة أُنشئت هنا',
    'manage' => 'إدارة',
    'no_campaigns' => 'لا توجد حملات بعد.',
    'create_first' => 'أنشئ الحملة الأولى',
    'table' => ['campaign' => 'الحملة', 'platform' => 'المنصة', 'status' => 'الحالة', 'budget' => 'الميزانية', 'created' => 'تاريخ الإنشاء', 'start' => 'البدء'],
    'per_day' => ':amount/يومياً',
    'unknown' => 'غير معروف',

    'note' => 'ستظهر هنا <strong>بيانات الإنفاق ومرات الظهور والنقرات والتحويلات</strong> بعد مزامنة التقارير من كل منصة. تعرض هذه الصفحة حالياً الحملات والحسابات الإعلانية والميزانيات المُعدّة في التطبيق.',

    'status_title' => 'حالة الحملات',
    'donut_campaigns' => 'حملات',
    'donut_aria' => ':count حملة',
    'slices' => ['active' => 'نشطة', 'paused' => 'متوقفة', 'other' => 'مسودة أو منتهية'],

    'quick' => 'إجراءات سريعة',
    'quick_sub' => 'ابدأ حملة على منصة',
    'quick_new' => 'حملة :platform جديدة',
    'quick_count' => ':count حتى الآن',

    'accounts' => 'الحسابات الإعلانية',
    'accounts_sub' => ':count على :platform',
    'account_active' => 'نشط',
    'account_reconnect' => 'إعادة الربط',

    'platforms' => ['facebook' => 'Meta / فيسبوك', 'google' => 'إعلانات Google', 'openai' => 'إعلانات ChatGPT'],

    'statuses' => [
        'active' => 'نشطة', 'enable' => 'مفعّلة', 'enabled' => 'مفعّلة', 'running' => 'قيد التشغيل', 'paused' => 'متوقفة',
        'pending' => 'قيد الانتظار', 'review' => 'قيد المراجعة', 'disable' => 'معطّلة', 'disabled' => 'معطّلة', 'error' => 'خطأ',
        'rejected' => 'مرفوضة', 'failed' => 'فشلت', 'archived' => 'مؤرشفة', 'deleted' => 'محذوفة', 'draft' => 'مسودة', 'completed' => 'مكتملة',
    ],

    'objectives' => [
        'OUTCOME_AWARENESS' => 'الوعي', 'OUTCOME_TRAFFIC' => 'الزيارات', 'OUTCOME_ENGAGEMENT' => 'التفاعل', 'OUTCOME_LEADS' => 'العملاء المحتملون',
        'OUTCOME_SALES' => 'المبيعات', 'OUTCOME_APP_PROMOTION' => 'الترويج للتطبيق', 'LEAD_GENERATION' => 'جذب العملاء المحتملين', 'REACH' => 'الوصول',
        'TRAFFIC' => 'الزيارات', 'CONVERSIONS' => 'التحويلات', 'VIDEO_VIEWS' => 'مشاهدات الفيديو', 'APP_INSTALLS' => 'تثبيتات التطبيق',
        'ENGAGEMENT' => 'التفاعل', 'WEBSITE_VISITS' => 'زيارات الموقع', 'BRAND_AWARENESS' => 'الوعي بالعلامة التجارية',
    ],
];
