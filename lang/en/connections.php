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
            'linkedin' => 'Read Page statistics for reports',
            'tiktok' => 'Read profile and video stats for reports',
            'pinterest' => 'Read Pin and account analytics for reports',
            'meta' => 'Read Page and Instagram insights for reports',
            'google' => 'Read Analytics data for reports',
            'default' => 'Read insights for reports',
        ],
        'engagement' => [
            'label' => 'Comments',
            'meta' => 'Read and reply to comments on Page and Instagram posts',
            'google' => 'Reply to comments on your YouTube videos',
            'tiktok' => 'Read and reply to comments on your TikTok videos',
            'default' => 'Read and reply to comments',
        ],
    ],

    // How each kind of account appears in the overview.
    'views' => [
        'page' => ['title' => 'Facebook', 'subtitle' => 'Business Page', 'id' => 'Page ID'],
        'instagram' => ['title' => 'Instagram', 'subtitle' => 'Business Account', 'id' => 'Account ID'],
        'youtube' => ['title' => 'YouTube', 'subtitle' => 'Channel', 'id' => 'Channel ID'],
        'business_profile' => ['title' => 'Business Profile', 'subtitle' => 'Google location', 'id' => 'Location ID'],
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

    // "Choose what to connect" on a platform panel (docs/connection-capabilities.md).
    'choose' => [
        'title' => 'Choose what to connect',
        'subtitle' => 'Pick the features you need. SocialEaz asks :platform only for the permissions those features use.',
        'button' => '{0} Select a feature|{1} Connect 1 feature|[2,*] Connect :count features',
        'all_connected' => 'Everything available here is connected.',
        'plan_one' => 'One :platform sign-in covers your selection.',
        'plan_many' => 'Your selection needs :count sign-ins. We will guide you through them one at a time.',
        'plan_step' => 'Sign-in :n: :label',
        'plan_reconnect' => 'Adds the missing permissions to your existing connection.',
        'untick_note' => 'You can untick permissions on the :platform screen. SocialEaz only turns on what :platform confirms.',
        'needs' => 'Needs:',
        'loading' => 'Working out the sign-ins...',
        'plan_failed' => 'Could not work out the sign-ins. You can still continue.',
    ],

    'catalog_status' => [
        'available' => 'Requires authorization',
        'connected' => 'Connected',
        'not_granted' => 'Permission not granted',
        'reconnect' => 'Needs reconnect',
        'approval_required' => 'Additional approval required',
        'unavailable' => 'Not available',
        'partial' => 'Partially supported',
    ],

    // The guided run after "Connect selected".
    'flow' => [
        'title' => 'Connecting :platform',
        'progress' => 'Step :n of :total',
        'next' => 'Next: :label',
        'opens' => 'Opens :platform in this tab. You will come straight back here afterwards.',
        'grants' => 'Turns on:',
        'failed' => 'This sign-in did not finish. It was cancelled, or :platform returned an error.',
        'continue' => 'Continue to :platform',
        'retry' => 'Try again',
        'skip' => 'Skip this step',
        'cancel' => 'Cancel',
        'state' => [
            'done' => 'Done',
            'failed' => 'Not finished',
            'skipped' => 'Skipped',
            'pending' => 'Waiting',
        ],
        'done_title' => 'Your :platform account is connected.',
        'partial_title' => ':platform is partly connected.',
        'none_title' => 'Nothing was connected on :platform.',
        'done_body' => 'Each feature shows what :platform actually granted.',
        'complete_missing' => 'Complete missing permissions',
        'add_more' => 'Add more features',
        'manage' => 'Manage accounts',
        'close' => 'Done',
    ],

    // Per platform and capability: the account it needs and known limits.
    'catalog' => [
        'meta' => [
            'posting' => ['account' => 'A Facebook Page. Instagram must be a professional account linked to it.'],
            'ads' => ['account' => 'A Meta ad account you can access.'],
            'messaging' => ['account' => 'A Facebook Page or a linked Instagram professional account.', 'note' => 'Messenger and Instagram messages. WhatsApp connects through its own sign-in.'],
            'engagement' => ['account' => 'A Facebook Page or a linked Instagram professional account.'],
            'insights' => ['account' => 'A Facebook Page or a linked Instagram professional account.'],
        ],
        'google' => [
            'posting' => ['account' => 'A YouTube channel or a Google Business Profile location.'],
            'ads' => ['account' => 'A Google Ads account you can access.'],
            'insights' => ['account' => 'A Google account with Analytics access, or a Business Profile you own or manage.', 'note' => 'GA4 and Search Console reports use the separate Analytics sign-in. Business Profile performance and reviews use this one.'],
            'engagement' => ['note' => 'Replying to YouTube comments needs a Google permission that is not enabled yet.'],
        ],
        'x' => [
            'posting' => ['account' => 'An X account.'],
            'messaging' => ['account' => 'An X account.'],
            'ads' => ['account' => 'An X Ads account.', 'note' => 'Uses a separate X sign-in. X must approve Ads API access first.'],
        ],
        'linkedin' => [
            'posting' => ['account' => 'A LinkedIn Page where you are an admin.'],
            'insights' => ['account' => 'A LinkedIn Page where you are an admin.', 'note' => 'Page statistics only. Ad reporting comes with Ads.'],
            'ads' => ['account' => 'A LinkedIn ad account.', 'note' => 'Uses a separate LinkedIn sign-in.'],
            'messaging' => ['note' => 'LinkedIn does not offer a messaging API for Pages.'],
        ],
        'tiktok' => [
            'posting' => ['account' => 'A TikTok account.'],
            'insights' => ['account' => 'A TikTok account.'],
            'engagement' => ['account' => 'A TikTok account.', 'note' => 'Uses a separate TikTok sign-in.'],
            'ads' => ['account' => 'A TikTok for Business advertiser account.', 'note' => 'Uses a separate TikTok for Business sign-in.'],
            'messaging' => ['note' => 'Waiting for TikTok to approve Business Messaging for SocialEaz.'],
        ],
        'snapchat' => [
            'ads' => ['account' => 'A Snapchat Business ad account.'],
            'posting' => ['note' => 'Snapchat Public Profile publishing is invite-only and not yet approved for SocialEaz.'],
        ],
        'threads' => [
            'posting' => ['account' => 'A Threads profile.'],
        ],
        'pinterest' => [
            'posting' => ['account' => 'A Pinterest account.'],
            'insights' => ['account' => 'A Pinterest account.'],
        ],
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
            'subtitle_gbp' => 'YouTube · Business Profile · Google Ads · Analytics',
            'connect_label' => 'Connect with Google',
            'empty_title' => 'One Google consent for YouTube and Ads',
            'empty_title_gbp' => 'One Google consent for YouTube, Business Profile and Ads',
            'empty_text' => 'Connect the Google account that owns your YouTube channels and Google Ads accounts. You can remove access at any time, here or in your Google Account settings.',
            'empty_text_gbp' => 'Connect the Google account that owns your YouTube channels, manages your Business Profile and has your Google Ads accounts. Your Business Profile locations are imported automatically. You can remove access at any time, here or in your Google Account settings.',
            'groups' => [
                'youtube' => 'YouTube channels',
                'google_ads' => 'Google Ads accounts',
                'business_profile' => 'Business Profile locations',
            ],
            'legacy' => [
                'label' => 'Google Ads (earlier connection)',
                'upgrade_note' => 'Connected through the old Ads-only Google app. It keeps working; reconnect once to move it to the single Google connection.',
            ],
            'steps' => [
                'oauth' => [
                    'label' => 'Google account',
                    'description' => 'YouTube channels, Google Ads accounts and Analytics in one consent.',
                    'description_gbp' => 'YouTube channels, Business Profile locations, Google Ads accounts and Analytics in one consent.',
                ],
                'analytics' => [
                    'label' => 'Analytics & Search Console',
                    'description' => 'Read-only GA4 and Search Console reports for the Google Suite dashboards.',
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

    'google_services' => [
        'title' => 'Google services',
        'subtitle' => 'Connect only what you use. Each service asks Google for its own permissions, and you can add more later without reconnecting the rest.',
        'groups' => [
            'marketing' => 'Content & advertising',
            'website' => 'Website insights',
            'later' => 'Coming later',
        ],
        'services' => [
            'youtube' => [
                'name' => 'YouTube',
                'text' => 'Publish videos and manage your channels.',
            ],
            'google_ads' => [
                'name' => 'Google Ads',
                'text' => 'Create campaigns and see spend and results.',
            ],
            'business_profile' => [
                'name' => 'Business Profile',
                'text' => 'Posts, reviews and performance for your Google Maps listing.',
            ],
            'analytics' => [
                'name' => 'Google Analytics 4',
                'text' => 'Website traffic, sources and conversions (read-only).',
            ],
            'search_console' => [
                'name' => 'Search Console',
                'text' => 'How your site performs in Google Search (read-only).',
            ],
            'pagespeed' => [
                'name' => 'PageSpeed Insights',
                'text' => 'Test how fast your pages load. No Google sign-in needed.',
            ],
            'tag_manager' => [
                'name' => 'Tag Manager',
                'text' => 'Website tags and tracking containers.',
            ],
            'maps' => [
                'name' => 'Google Maps',
                'text' => 'Maps and location features.',
            ],
        ],
        'status' => [
            'connected' => 'Connected',
            'not_connected' => 'Not connected',
            'expired' => 'Expired',
            'permission_required' => 'Permission required',
            'error' => 'Connection error',
            'ready' => 'Ready',
            'unavailable_planned' => 'Not available yet',
            'unavailable_approval' => 'Awaiting approval',
        ],
        'reasons' => [
            'planned' => 'Not part of SocialEaz yet.',
            'planned_maps' => 'Not part of SocialEaz yet. Your business location and map link come from Business Profile.',
            'approval' => 'Google must approve SocialEaz for this API before it can be connected.',
            'not_configured' => 'Google sign-in isn’t set up on SocialEaz yet. Our team has been notified.',
            'own_key' => 'Running on your own API key.',
            'platform_key' => 'Running on SocialEaz’s key. Add your own key for your own quota.',
            'needs_key' => 'Add a free PageSpeed Insights API key to run tests.',
        ],
        'notes' => [
            'no_assets' => [
                'youtube' => 'No YouTube channel found on this Google account.',
                'google_ads' => 'No Google Ads account found on this Google account.',
                'business_profile' => 'No Business Profile locations found. Connect the account that owns or manages your profile.',
            ],
            'no_property' => [
                'analytics' => 'Choose which GA4 property to report on.',
                'search_console' => 'Choose which Search Console site to report on.',
            ],
        ],
        'fix' => [
            'expired' => 'The connection expired. Reconnect to continue.',
            'permission_required' => 'Google needs you to approve access again. Reconnect and keep the permission ticked.',
            'error' => 'Google returned an error. Try reconnecting; if it continues, contact support.',
        ],
        'assets' => [
            'youtube' => ':n channel(s)',
            'google_ads' => ':n ad account(s)',
            'business_profile' => ':n location(s)',
        ],
        'connect' => 'Connect',
        'reconnect' => 'Reconnect',
        'open' => 'Open',
        'add_key' => 'Add API key',
        'learn_more' => 'Learn more',
        'add_account' => 'Add account',
        'add_account_hint' => 'Connect another Google account for this service',
        'disconnect' => 'Disconnect',
        'account_unknown' => 'Google account',
        'confirm' => 'Disconnect :account from SocialEaz?',
        'confirm_shared' => 'Disconnecting :account also stops :services on this account.',
        'owner_connects' => 'The workspace owner connects Google services.',
        'accounts_title' => 'Google accounts',
        'privacy' => 'SocialEaz only asks Google for the permissions a service needs. You can remove access any time here or in your Google Account.',
    ],
];
