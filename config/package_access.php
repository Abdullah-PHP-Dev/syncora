<?php

return [
    'modules' => [
        'team_management' => ['label' => 'Team management', 'source' => 'team_members', 'numeric' => true],
        'publishing' => ['label' => 'Social publishing', 'source' => 'publishing_accounts', 'numeric' => true],
        'advertising' => ['label' => 'Ads manager', 'source' => 'advertising_accounts', 'numeric' => true],
        'inbox' => ['label' => 'Unified inbox', 'source' => 'messaging_inbox_access'],
        'comments' => ['label' => 'Comments and replies', 'source' => 'comments_replies'],
        'media' => ['label' => 'Media Gallery', 'source' => 'media_storage_gb', 'numeric' => true],
        'ai_content' => ['label' => 'AI content generation', 'source' => 'ai_credits', 'numeric' => true],
        'ai_agent' => ['label' => 'AI Agent', 'source' => 'ai_agent_access'],
        'email_marketing' => ['label' => 'Email Marketing', 'source' => 'email_marketing_access'],
        'data_export' => ['label' => 'Data export', 'source' => 'data_export'],
        'data_import' => ['label' => 'Data import', 'source' => 'data_import'],
        'google_analytics' => ['label' => 'Google Analytics integration', 'source' => 'google_analytics'],
        'post_approval' => ['label' => 'Post approval', 'source' => 'post_approval'],
    ],
    // Specific rules run before general module rules. Multiple matches all apply.
    'routes' => [
        'data_export' => ['admin.knowledge-base.export', 'admin.email.campaigns.export', 'admin.google.api.reports.export'],
        'data_import' => ['admin.knowledge-base.import', 'admin.email.lists.subscribers.import'],
        'ai_content' => ['admin.posts.generate-ai-*', 'admin.posts.ask*', 'admin.email.templates.generateAi', 'admin.knowledge-base.improve'],
        'ai_agent' => ['admin.ai-copilot.*', 'admin.knowledge-base.*', 'admin.chats.copilot.*', 'admin.chats.resume-ai'],
        'comments' => ['admin.comments.*', 'admin.posts.comments.*', 'admin.post-comments.*', 'admin.tiktok-comments.*'],
        'publishing' => ['admin.posts.*', 'admin.post-accounts.*', 'admin.post-categories.*', 'admin.categories.*'],
        'advertising' => ['admin.ads.*'],
        'inbox' => ['admin.chats.*', 'admin.messaging.*'],
        'media' => ['admin.media-gallery.*'],
        'email_marketing' => ['admin.email.*'],
        'google_analytics' => ['admin.google.*'],
    ],
    'write_get_routes' => ['*.redirect', '*.callback', '*.connect', '*.sync', '*.auth.*', '*.create', '*.edit'],
    // These values are interpreted once during migration, never as runtime permissions.
    'legacy_tiers' => [
        'No' => ['mode' => 'disabled', 'tier' => 'none'],
        'Preview' => ['mode' => 'preview', 'tier' => 'preview'],
        'Limited' => ['mode' => 'included', 'tier' => 'limited'],
        'Basic' => ['mode' => 'included', 'tier' => 'basic'],
        'Expanded' => ['mode' => 'included', 'tier' => 'expanded'],
        'Full supported channels' => ['mode' => 'included', 'tier' => 'full'],
        'Yes' => ['mode' => 'included', 'tier' => 'full'],
        'Advanced' => ['mode' => 'included', 'tier' => 'advanced'],
        'Multi-user workflow' => ['mode' => 'included', 'tier' => 'multi_user'],
        'Add-on' => ['mode' => 'addon_required', 'tier' => 'addon'],
        'Add-on or included allowance' => ['mode' => 'addon_required', 'tier' => 'addon'],
        'Included allowance' => ['mode' => 'included', 'tier' => 'included'],
    ],
];
