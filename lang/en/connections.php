<?php

// Connection Hub (admin/connections - ConnectionHub.vue). platforms.* is
// the copy each driver's presentation() / steps() returns.
return [
    'page_title' => 'Connections',

    'header' => [
        'title' => 'Connections',
        'subtitle' => 'Manage your social media accounts, ad accounts and chat connections all in one place.',
    ],

    'gate' => [
        'required' => 'Connect at least one social account to use Posts, Inbox and Comments.',
        'workspace' => 'This workspace has no connected social account yet. Ask its owner to connect one to use Posts, Inbox and Comments.',
        'title' => 'Connect your first social account',
        'body' => 'Posts, Inbox and Comments unlock as soon as one account is connected and working.',
        'locked' => 'Connect a social account to unlock',
    ],

    'dismiss' => 'Dismiss',
    'close' => 'Close',
    'cancel' => 'Cancel',
    'connect' => 'Connect',
    'reconnect' => 'Reconnect',
    'upgrade' => 'Upgrade',
    'grant_permissions' => 'Grant permissions',
    'not_available' => 'Not available',
    'verify_connect' => 'Verify and connect',

    'wizard' => [
        'step' => 'Setup :done of :total: :label',
        'next' => "Next, sign in with :label. You'll come straight back here afterwards.",
        'finished' => 'Setup finished',
        'skipped' => '{1} 1 skipped - connect it from its card whenever you like.|[2,*] :count skipped - connect them from their cards whenever you like.',
        'all_connected' => 'All recommended platforms are connected.',
        'finish_later' => 'Finish later',
        'skip' => 'Skip',
        'continue' => 'Continue',
    ],

    'hero' => [
        'title' => 'Your Connected Platforms',
        'text' => 'Manage all your social accounts, ad accounts and messaging connections from one central place. Connect a platform once and Publishing, Ads and Inbox all use it.',
        'social' => 'Social Media Accounts',
        'ads' => 'Ad Accounts',
        'messaging' => 'Messaging Accounts',
    ],

    'tabs' => [
        'all' => 'All Connections',
        'social' => 'Social Media',
        'ads' => 'Ads Manager',
        'messaging' => 'Messaging',
    ],

    'start' => [
        'title' => 'Connect your first platform',
        'text' => 'Sign in once - Publishing, Ads and Inbox all use the same connection.',
    ],

    'sections' => [
        'social' => [
            'title' => 'Social Media Accounts',
            'subtitle' => 'Profiles and pages you publish to.',
            'empty' => 'Connect a social account',
            'add' => 'Add more social media platforms to expand your reach and engagement.',
        ],
        'ads' => [
            'title' => 'Ad Accounts',
            'subtitle' => 'Advertising accounts for your campaigns.',
            'empty' => 'Connect an ad account',
            'add' => 'Add Meta, Google, TikTok, X, LinkedIn, Snapchat or ChatGPT ad accounts.',
        ],
        'messaging' => [
            'title' => 'Messaging Accounts',
            'subtitle' => 'Inboxes your team answers from.',
            'empty' => 'Connect a messaging account',
            'add' => 'Add Messenger, Instagram Direct, X or WhatsApp to your inbox.',
        ],
        'accounts' => '{1} 1 account|[2,*] :count accounts',
        'connect_more' => 'Connect More Accounts',
    ],

    'account' => [
        'checking' => 'Checking…',
        'actions' => 'Account actions',
        'create_post' => 'Create a post',
        'open_inbox' => 'Open inbox',
        'create_campaign' => 'Create campaign',
        'pause' => 'Pause :capability',
        'resume' => 'Resume :capability',
        'check' => 'Check connection',
        'copy' => 'Copy :label',
        'manage' => 'Manage connection',
    ],

    // Row badges in the overview.
    'state' => [
        'disconnected' => 'Disconnected',
        'expiring' => 'Expiring soon',
        'reconnect' => 'Reconnect',
        'off' => 'Off',
        'paused' => 'Paused',
        'connected' => 'Connected',
    ],

    'quick' => [
        'title' => 'Quick Actions',
        'recommended' => 'Connect all recommended',
        'recommended_text' => 'Meta, Google, LinkedIn, X and TikTok in a row',
        'new' => 'Connect New Account',
        'new_text' => 'Add a social, ad or messaging account',
        'ads' => 'Create Ad Campaign',
        'ads_text' => 'Launch your next campaign',
        'post' => 'Schedule a Post',
        'post_text' => 'Plan your content in advance',
    ],

    'status' => [
        'title' => 'Connection Status',
        'aria' => '{1} 1 connection|[2,*] :count connections',
        'total' => 'Total',
        'connections' => 'connections',
        'connected' => 'Connected',
        'expiring' => 'Expiring soon',
        'reconnect' => 'Needs reconnect',
    ],

    'help' => [
        'title' => 'Need Help?',
        'text' => 'Check our documentation or contact our support team for help with connections.',
        'cta' => 'View Help Center',
    ],

    'drawer' => [
        'all_platforms' => 'All platforms',
        'title' => 'Connect a platform',
        'subtitle' => 'Choose a platform to connect or manage.',
    ],

    'card' => [
        'needs_attention' => 'Needs attention',
        'connected' => 'Connected',
        'not_connected' => 'Not connected',
        'accounts' => '{1} 1 account|[2,*] :count accounts',
    ],

    'step' => [
        'connected' => 'Connected',
        'add_number' => 'Add a number',
        'add_key' => 'Add another key',
        'change_accounts' => 'Add or change accounts',
        'enter_manually' => 'Enter manually',
    ],

    'openai' => [
        'help' => 'In OpenAI Ads Manager open <strong>Settings &rsaquo; API keys</strong>, create a key and paste it below. We check it with OpenAI before saving, and store it encrypted.',
        'key' => 'Ads API key',
        'key_placeholder' => 'Paste the key from ads.openai.com',
        'open' => 'Open Ads Manager',
    ],

    'whatsapp' => [
        'help' => 'Paste the Phone Number ID and a permanent access token from a Meta Business System User. We verify them with Meta before saving.',
        'name' => 'Display name',
        'name_placeholder' => 'e.g. Support line',
        'phone_id' => 'Phone Number ID',
        'token' => 'Permanent access token',
    ],

    'conn' => [
        'checked' => 'Checked :time',
        'id' => 'ID :id',
        'check_now' => 'Check now',
        'disconnect' => 'Disconnect',
        'confirm' => "Remove SocialEaz's access?",
        'upgrade_title' => 'One-time upgrade available',
        'no_assets' => 'No accounts linked to this connection yet. Use “Add or change accounts” to choose them.',
    ],

    'asset' => [
        'needs_reconnect' => 'needs reconnect',
        'duplicate' => "Same account as :name. Publishing and inbox are off here so posts don't go out twice.",
        'turn_on' => 'Turn on :capability',
        'turn_off' => 'Turn off :capability',
        'no_permissions' => 'No permissions granted',
        'use' => 'Use this account',
        'stop' => 'Stop using this account',
    ],

    // SocialConnection statuses.
    'statuses' => [
        'active' => 'Active',
        'expiring' => 'Expiring soon',
        'expired' => 'Expired',
        'needs_reauth' => 'Reconnect needed',
        'revoked' => 'Disconnected',
        'error' => 'Check failed',
    ],

    'attention' => [
        'expiring' => 'Access expires soon. Reconnect to keep everything running.',
        'expired' => 'Access expired.',
        'needs_reauth' => 'Facebook needs you to log in again.',
        'revoked' => 'This connection was disconnected.',
        'error' => 'We couldn’t check this connection.',
        'default' => 'Needs attention',
    ],

    'expiry' => [
        'expired' => 'Expired',
        'today' => 'Expires today',
        'days' => '{1} Expires in 1 day|[2,*] Expires in :count days',
    ],

    'ago' => [
        'now' => 'just now',
        'minutes' => ':count min ago',
        'hours' => ':count h ago',
        'days' => ':count d ago',
    ],

    'capabilities' => [
        'posting' => [
            'label' => 'Publishing',
            'meta' => 'Publish and schedule posts to Pages and Instagram',
            'google' => 'Upload and schedule videos to your YouTube channels',
            'x' => 'Publish and schedule posts on X',
            'default' => 'Publish and schedule posts',
        ],
        'messaging' => [
            'label' => 'Inbox',
            'meta' => 'Answer Messenger, Instagram and WhatsApp messages',
            'x' => 'Answer Direct Messages and X Chat',
            'default' => 'Answer direct messages',
        ],
        'ads' => [
            'label' => 'Ads',
            'meta' => 'Create and manage campaigns on your ad accounts',
            'google' => 'Create and manage Google Ads and YouTube campaigns',
            'x' => 'Promote posts on your X Ads accounts',
            'default' => 'Create and manage ad campaigns',
        ],
        'insights' => [
            'label' => 'Insights',
            'meta' => 'Read Page and Instagram insights for reports',
            'google' => 'Read Analytics data for reports',
            'default' => 'Read insights for reports',
        ],
        'engagement' => [
            'label' => 'Comments',
            'tiktok' => 'Read and reply to comments on your TikTok videos',
            'default' => 'Read and reply to comments',
        ],
    ],

    // How each kind of account appears in the overview.
    'views' => [
        'page' => ['title' => 'Facebook', 'subtitle' => 'Business Page', 'id' => 'Page ID'],
        'instagram' => ['title' => 'Instagram', 'subtitle' => 'Business Account', 'id' => 'Account ID'],
        'youtube' => ['title' => 'YouTube', 'subtitle' => 'Channel', 'id' => 'Channel ID'],
        'x_account' => ['title' => 'X', 'subtitle' => 'Account', 'id' => 'Account ID'],
        'linkedin_page' => ['title' => 'LinkedIn', 'subtitle' => 'Company Page', 'id' => 'Page ID'],
        'tiktok_account' => ['title' => 'TikTok', 'subtitle' => 'Account', 'id' => 'Account ID'],
        'tiktok_comments' => ['title' => 'TikTok', 'subtitle' => 'Comments', 'id' => 'Business ID'],
        'threads_profile' => ['title' => 'Threads', 'subtitle' => 'Profile', 'id' => 'Profile ID'],
        'pinterest_account' => ['title' => 'Pinterest', 'subtitle' => 'Account', 'id' => 'Account ID'],
        'ad_account' => ['title' => 'Meta Ads', 'subtitle' => 'Ad Account', 'id' => 'Account ID'],
        'google_ads' => ['title' => 'Google Ads', 'subtitle' => 'Ad Account', 'id' => 'Customer ID'],
        'x_ads' => ['title' => 'X Ads', 'subtitle' => 'Ad Account', 'id' => 'Account ID'],
        'linkedin_ads' => ['title' => 'LinkedIn Ads', 'subtitle' => 'Ad Account', 'id' => 'Account ID'],
        'tiktok_ads' => ['title' => 'TikTok Ads', 'subtitle' => 'Advertiser Account', 'id' => 'Advertiser ID'],
        'openai_ads' => ['title' => 'ChatGPT Ads', 'subtitle' => 'Ad Account', 'id' => 'Account ID'],
        'snapchat_ads' => ['title' => 'Snapchat Ads', 'subtitle' => 'Ad Account', 'id' => 'Account ID'],
        'whatsapp' => ['title' => 'WhatsApp', 'subtitle' => 'Business Number', 'id' => 'Phone ID'],
    ],

    // The inbox side of social accounts that also take messages.
    'inbox_views' => [
        'page' => ['title' => 'Messenger', 'subtitle' => 'Facebook Page inbox', 'id' => 'Page ID'],
        'instagram' => ['title' => 'Instagram Direct', 'subtitle' => 'Instagram inbox', 'id' => 'Account ID'],
        'x_account' => ['title' => 'X Messages', 'subtitle' => 'DMs and X Chat', 'id' => 'Account ID'],
    ],

    'toast' => [
        'paused' => 'Paused :capability for :name.',
        'resumed' => 'Resumed :capability for :name.',
        'copied' => ':label copied: :id',
        'copy_failed' => 'Couldn’t copy. :label: :id',
        'copy_plain' => ':label: :id',
        'save_failed' => 'Couldn’t save that change. Please try again.',
        'checked' => ':platform checked: everything works.',
        'check_failed' => 'The check didn’t complete. Please try again.',
        'wa_no_number' => 'Signup finished without a phone number. Please try again.',
        'wa_connected' => 'WhatsApp number connected.',
        'wa_failed' => 'Couldn’t connect WhatsApp.',
        'fb_load_failed' => 'Couldn’t load Facebook. Check your connection or ad blocker and try again.',
        'disconnected' => 'Disconnected. Reconnect at any time from this page.',
        'disconnect_failed' => 'Couldn’t disconnect. Please try again.',
    ],

    // Set by HubPresenter / ConnectionHubController.
    'provider_status' => [
        'x_rejected' => 'Rejected by X',
        'x_review' => 'Under review at X',
        'x_deleted' => 'Deleted in X',
    ],
    'duplicate_of_page' => 'the one linked to your Facebook Page',

    'openai_flash' => [
        'unreachable' => 'Could not reach OpenAI Ads. Please try again.',
        'rejected' => 'OpenAI did not accept this API key. Copy it again from ads.openai.com > Settings.',
        'unreadable' => 'Could not read the ChatGPT Ads account for this key.',
        'connected' => 'Connected ChatGPT Ads account ":name".',
    ],

    'platforms' => [
        'meta' => [
            'subtitle' => 'Facebook Pages · Instagram · Messenger · WhatsApp · Ads',
            'connect_label' => 'Connect with Facebook',
            'empty_title' => 'One consent for everything Meta',
            'empty_text' => 'Choose the Pages, Instagram accounts and ad accounts SocialEaz may use. You can change your choice at any time, here or in your Facebook settings.',
            'groups' => [
                'page' => 'Facebook Pages',
                'instagram' => 'Instagram accounts',
                'ad_account' => 'Ad accounts',
                'whatsapp' => 'WhatsApp numbers',
            ],
            'steps' => [
                'login' => [
                    'label' => 'Facebook & Instagram',
                    'description' => 'Pages, Instagram business accounts, Messenger and ad accounts in one consent.',
                    'description_whatsapp' => 'Pages, Instagram business accounts, Messenger, WhatsApp and ad accounts in one consent.',
                ],
                'whatsapp' => [
                    'label' => 'WhatsApp Business',
                    'description' => 'Embedded Signup: pick or create a WhatsApp Business number.',
                    'note' => 'Embedded Signup needs a configuration in the Meta app - until then, enter a number manually.',
                ],
                'instagram_login' => [
                    'label' => 'Instagram without a Facebook Page',
                    'description' => "Instagram Login for professional accounts that aren't linked to a Page.",
                ],
            ],
        ],
        'google' => [
            'subtitle' => 'YouTube · Google Ads · Analytics',
            'connect_label' => 'Connect with Google',
            'empty_title' => 'One Google consent for YouTube and Ads',
            'empty_text' => 'Connect the Google account that owns your YouTube channels and Google Ads accounts. You can remove access at any time, here or in your Google Account settings.',
            'groups' => [
                'youtube' => 'YouTube channels',
                'google_ads' => 'Google Ads accounts',
            ],
            'legacy' => [
                'label' => 'Google Ads (earlier connection)',
                'upgrade_note' => 'Connected through the old Ads-only Google app. It keeps working; reconnect once to move it to the single Google connection.',
            ],
            'steps' => [
                'oauth' => [
                    'label' => 'Google account',
                    'description' => 'YouTube channels, Google Ads accounts and Analytics in one consent.',
                ],
            ],
        ],
        'linkedin' => [
            'subtitle' => 'Company Pages · Campaign Manager Ads',
            'connect_label' => 'Connect with LinkedIn',
            'empty_title' => 'Connect your LinkedIn Pages and Ads',
            'empty_text' => 'Choose the Company Pages you post to and the Campaign Manager ad accounts you advertise with. To fully remove access later, also remove SocialEaz in your LinkedIn settings.',
            'groups' => [
                'linkedin_page' => 'Company Pages',
                'linkedin_ads' => 'Ad accounts',
            ],
            'steps' => [
                'pages' => [
                    'label' => 'LinkedIn Pages',
                    'description' => 'Post to the Company Pages you administer.',
                ],
                'ads' => [
                    'label' => 'LinkedIn Ads',
                    'description' => 'Campaign Manager ad accounts where you have a role.',
                    'note' => 'Separate LinkedIn app until Community Management is added to the Ads app.',
                ],
            ],
        ],
        'openai' => [
            'subtitle' => 'Ads in ChatGPT conversations · OpenAI Ads Manager',
            'connect_label' => 'Add API key',
            'empty_title' => 'Connect your ChatGPT Ads account',
            'empty_text' => 'Create an Ads API key in OpenAI Ads Manager (ads.openai.com > Settings) and paste it here. Each key connects one ad account. ChatGPT Ads is available in the US, UK, Mexico, Brazil, Japan, South Korea and Europe.',
            'groups' => [
                'openai_ads' => 'Ad accounts',
            ],
            'steps' => [
                'api_key' => [
                    'label' => 'ChatGPT Ads account',
                    'description' => 'Paste an Ads API key from ads.openai.com > Settings. Each key connects one ad account.',
                ],
            ],
        ],
        'pinterest' => [
            'subtitle' => 'Pins and boards',
            'connect_label' => 'Connect with Pinterest',
            'empty_title' => 'Connect your Pinterest account',
            'empty_text' => 'Publish Pins to your boards. To fully remove access later, also remove SocialEaz in your Pinterest settings.',
            'groups' => [
                'pinterest_account' => 'Pinterest accounts',
            ],
            'steps' => [
                'login' => [
                    'label' => 'Pinterest account',
                    'description' => 'Publish Pins to your boards.',
                ],
            ],
        ],
        'snapchat' => [
            'subtitle' => 'Snapchat Ads',
            'connect_label' => 'Connect with Snapchat',
            'empty_title' => 'Connect your Snapchat Ads accounts',
            'empty_text' => 'Sign in with the Snapchat account that has a role in your Snap Business Manager organization. To fully remove access later, also remove SocialEaz in Snap Business Manager.',
            'groups' => [
                'snapchat_ads' => 'Ad accounts',
            ],
            'steps' => [
                'marketing' => [
                    'label' => 'Snapchat Ads',
                    'description' => 'Ad accounts in your Snap Business Manager organizations.',
                ],
            ],
        ],
        'threads' => [
            'subtitle' => 'Posting',
            'connect_label' => 'Connect with Threads',
            'empty_title' => 'Connect your Threads profile',
            'empty_text' => 'Publish and schedule posts to Threads. To fully remove access later, also remove SocialEaz in your Threads settings.',
            'groups' => [
                'threads_profile' => 'Threads profiles',
            ],
            'steps' => [
                'login' => [
                    'label' => 'Threads profile',
                    'description' => 'Publish and schedule posts to Threads.',
                ],
            ],
        ],
        'tiktok' => [
            'subtitle' => 'Video posting · Analytics · Comments · TikTok Ads',
            'connect_label' => 'Connect with TikTok',
            'empty_title' => 'Connect TikTok for posting, comments and ads',
            'empty_text' => 'TikTok gives each product its own sign-in, so connect only what you use: your TikTok account for publishing and analytics, then comments and TikTok Ads if you need them.',
            'groups' => [
                'tiktok_account' => 'TikTok accounts',
                'tiktok_ads' => 'Advertiser accounts',
                'tiktok_comments' => 'Comments',
            ],
            'steps' => [
                'login_kit' => [
                    'label' => 'TikTok account',
                    'description' => 'Publish videos and see their views, likes and shares.',
                ],
                'business' => [
                    'label' => 'TikTok for Business',
                    'description' => 'Advertiser accounts for TikTok Ads.',
                    'note' => 'TikTok uses a separate app and sign-in for ads.',
                    'connected_action' => 'Add advertisers',
                ],
                'comments' => [
                    'label' => 'TikTok comments',
                    'description' => 'Read and reply to comments on your videos. Connect your TikTok account first so comments match your posts.',
                    'note' => 'Uses your TikTok for Business app - its comment permissions must be approved by TikTok.',
                ],
            ],
        ],
        'x' => [
            'subtitle' => 'Posts · Direct Messages · X Chat · Ads',
            'connect_label' => 'Connect with X',
            'empty_title' => 'Connect your X account once',
            'empty_text' => 'One sign-in covers publishing and your X inbox, including X Chat. You can remove access at any time, here or in your X settings.',
            'groups' => [
                'x_account' => 'X accounts',
                'x_ads' => 'X Ads accounts',
            ],
            'steps' => [
                'oauth2' => [
                    'label' => 'X account',
                    'description' => 'Posts, Direct Messages and X Chat in one consent.',
                ],
                'ads' => [
                    'label' => 'X Ads',
                    'description' => 'Promoted posts on your X Ads accounts.',
                    'note' => 'Separate X app until Ads API access is approved for the main one.',
                ],
            ],
        ],
    ],
];
