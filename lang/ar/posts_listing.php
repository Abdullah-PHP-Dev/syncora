<?php

// Posts listing page (admin/posts/listing - PostsDashboard.vue).
return [
    'page_title' => 'منشورات التواصل الاجتماعي',

    'header' => [
        'badge' => 'إدارة وسائل التواصل الاجتماعي',
        'title' => 'منشورات التواصل الاجتماعي',
        'subtitle' => 'أدر جميع منشوراتك على وسائل التواصل الاجتماعي وجدولها وتابعها من مكان واحد.',
        'export' => 'تصدير',
        'create_post' => 'إنشاء منشور',
    ],

    'composer' => [
        'placeholder' => 'بماذا تفكر يا :name؟',
        'photo' => 'صورة',
        'video' => 'فيديو',
    ],

    'tabs' => [
        'all' => 'الكل',
        'posts' => '{0} لا منشورات|{1} منشور واحد|{2} منشوران|[3,10] :count منشورات|[11,*] :count منشور',
    ],

    'toolbar' => [
        'search_placeholder' => 'ابحث في المنشورات بالعنوان أو المحتوى...',
        'all_status' => 'كل الحالات',
        'latest_first' => 'الأحدث أولاً',
        'oldest_first' => 'الأقدم أولاً',
        'grid_view' => 'عرض شبكي',
        'list_view' => 'عرض قائمة',
    ],

    'status' => [
        'published' => 'منشور',
        'scheduled' => 'مجدول',
        'failed' => 'فشل',
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'in_progress' => 'قيد التنفيذ',
        'draft' => 'مسودة',
    ],

    'list' => [
        'recent_posts' => 'أحدث المنشورات',
        'available' => '{0} لا توجد منشورات|{1} منشور واحد متاح|{2} منشوران متاحان|[3,10] :count منشورات متاحة|[11,*] :count منشوراً متاحاً',
        'empty' => 'لم يتم العثور على منشورات.',
        'actions_for' => 'إجراءات :title',
        'post' => 'المنشور',
        'preview' => 'معاينة',
        'view_comments' => 'عرض التعليقات',
        'promote_on' => 'روّج على :platform',
        'showing' => 'عرض :from - :to من :total منشور',
        'previous' => 'الصفحة السابقة',
        'next' => 'الصفحة التالية',
    ],

    'quick' => [
        'title' => 'إنشاء منشور',
        'close' => 'إغلاق',
        'audience' => 'الأصدقاء',
        'post_to' => 'النشر على',
        'schedule_later' => 'الجدولة لوقت لاحق',
        'add_to_post' => 'أضف إلى منشورك',
        'photo_video' => 'صورة/فيديو',
        'tag_people' => 'إشارة إلى أشخاص',
        'feeling' => 'شعور/نشاط',
        'location' => 'الموقع',
        'posting' => 'جارٍ النشر...',
        'schedule_post' => 'جدولة المنشور',
        'post' => 'نشر',
    ],

    'alerts' => [
        'success' => 'تم بنجاح!',
        'published' => 'تم نشر المنشور بنجاح!',
        'scheduled' => 'تمت جدولة المنشور بنجاح!',
        'partial' => 'نجاح جزئي',
        'error' => 'خطأ!',
        'generic_error' => 'حدث خطأ أثناء النشر.',
        'snapchat_note' => 'لا يوفر سناب شات واجهة للنشر التلقائي - شارك هذا المنشور يدوياً بدلاً من ذلك:',
        'share_snapchat' => 'مشاركة على سناب شات',
    ],
];
