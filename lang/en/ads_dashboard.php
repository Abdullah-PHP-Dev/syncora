<?php

/*
 * Ads dashboard (/ads/dashboard) - resources/js/components/ads/AdsDashboard.vue,
 * passed to the page as JSON by admin/ads/dashboard.blade.php.
 * Keep lang/ar/ads_dashboard.php in step with this file.
 */
return [
    'title_all' => 'Ads Dashboard',
    'title_platform' => ':platform Ads',
    'subtitle_all' => 'Campaigns, ad accounts and budgets across every advertising platform you use.',
    'subtitle_platform' => ':platform campaigns, ad accounts and budgets.',
    'chip_active' => '{1} :count active campaign|[0,*] :count active campaigns',
    'chip_daily' => ':amount per day configured',
    'chip_accounts' => '{1} :count ad account|[0,*] :count ad accounts',
    'all_campaigns' => 'All :platform campaigns',
    'create_campaign' => 'Create campaign',

    'empty_title' => 'No ad accounts yet',
    'empty_text' => 'Connect an advertising account in Connections and its campaigns, ad accounts and budgets show up here.',
    'go_connections' => 'Go to Connections',

    'platforms_aria' => 'Platforms',
    'all_platforms' => 'All platforms',
    'needs_reconnect' => ':platform - needs reconnecting',

    'kpi' => [
        'total' => 'Total campaigns',
        'total_sub' => ':groups ad groups · :ads ads',
        'active' => 'Active campaigns',
        'active_sub' => ':count paused',
        'daily' => 'Daily budget',
        'lifetime' => 'Lifetime budget',
        'configured' => 'Configured across campaigns',
    ],
    'strip' => ['accounts' => 'Ad accounts', 'ad_groups' => 'Ad groups', 'ads' => 'Ads', 'creatives' => 'Creatives'],

    'comparison' => 'Platform comparison',
    'comparison_sub' => 'Share of campaigns and configured daily budget',
    'comparison_none' => 'No campaigns created on any platform yet.',
    'campaign_count' => '{1} :count campaign|[0,*] :count campaigns',
    'per_day_configured' => ':amount/day configured',

    'recent' => 'Recent campaigns',
    'recent_sub' => 'Latest across all platforms',
    'platform_campaigns' => ':platform campaigns',
    'platform_campaigns_sub' => 'Every campaign created here',
    'manage' => 'Manage',
    'no_campaigns' => 'No campaigns yet.',
    'create_first' => 'Create the first one',
    'table' => ['campaign' => 'Campaign', 'platform' => 'Platform', 'status' => 'Status', 'budget' => 'Budget', 'created' => 'Created', 'start' => 'Start'],
    'per_day' => ':amount/day',
    'unknown' => 'unknown',

    'note' => '<strong>Spend, impressions, clicks and conversions</strong> will appear here once reporting is synced from each platform. Today this page shows the campaigns, ad accounts and budgets configured in the app.',

    'status_title' => 'Campaign status',
    'donut_campaigns' => 'campaigns',
    'donut_aria' => ':count campaigns',
    'slices' => ['active' => 'Active', 'paused' => 'Paused', 'other' => 'Draft or ended'],

    'quick' => 'Quick actions',
    'quick_sub' => 'Start a campaign on a platform',
    'quick_new' => 'New :platform campaign',
    'quick_count' => ':count so far',

    'accounts' => 'Ad accounts',
    'accounts_sub' => ':count on :platform',
    'account_active' => 'Active',
    'account_reconnect' => 'Reconnect',

    'platforms' => ['facebook' => 'Meta / Facebook', 'google' => 'Google Ads', 'openai' => 'ChatGPT Ads'],

    'statuses' => [
        'active' => 'active', 'enable' => 'enabled', 'enabled' => 'enabled', 'running' => 'running', 'paused' => 'paused',
        'pending' => 'pending', 'review' => 'in review', 'disable' => 'disabled', 'disabled' => 'disabled', 'error' => 'error',
        'rejected' => 'rejected', 'failed' => 'failed', 'archived' => 'archived', 'deleted' => 'deleted', 'draft' => 'draft', 'completed' => 'completed',
    ],

    'objectives' => [
        'OUTCOME_AWARENESS' => 'Awareness', 'OUTCOME_TRAFFIC' => 'Traffic', 'OUTCOME_ENGAGEMENT' => 'Engagement', 'OUTCOME_LEADS' => 'Leads',
        'OUTCOME_SALES' => 'Sales', 'OUTCOME_APP_PROMOTION' => 'App promotion', 'LEAD_GENERATION' => 'Lead generation', 'REACH' => 'Reach',
        'TRAFFIC' => 'Traffic', 'CONVERSIONS' => 'Conversions', 'VIDEO_VIEWS' => 'Video views', 'APP_INSTALLS' => 'App installs',
        'ENGAGEMENT' => 'Engagement', 'WEBSITE_VISITS' => 'Website visits', 'BRAND_AWARENESS' => 'Brand awareness',
    ],
];
