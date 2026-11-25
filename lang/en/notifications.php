<?php

// Platform notifications (config/notifications.php). Placeholders come from each event's
// params; :count is the number of grouped events. Sentence case, outcome first,
// "Action required:" only when a click is genuinely needed (audit Appendix B).

return [

    'tones' => [
        'success' => 'Done', 'info' => 'Update', 'warning' => 'Heads-up', 'action' => 'Action required',
        'critical' => 'Urgent', 'report' => 'Summary',
    ],

    'categories' => [
        'security' => 'Security & account', 'billing' => 'Billing & subscription', 'usage' => 'Usage & limits',
        'publishing' => 'Publishing', 'connections' => 'Social connections', 'inbox' => 'Inbox', 'comments' => 'Comments',
        'crm' => 'CRM', 'ads' => 'Advertising', 'email_marketing' => 'Email marketing', 'google' => 'Google',
        'ai' => 'AI & automations', 'team' => 'Team & workspace', 'support' => 'Support', 'product' => 'Product updates',
    ],

    'category_hints' => [
        'security' => 'Sign-in, password and access alerts. Always on.',
        'billing' => 'Receipts, renewals and payment problems. Always on.',
        'usage' => 'When you get close to or reach a plan limit.',
        'publishing' => 'Failed, interrupted and partly published posts, and what went live.',
        'connections' => 'Accounts that expire or need reconnecting.',
        'inbox' => 'Customers waiting for a reply, buying signals and assignments.',
        'comments' => 'Comments that show buying intent.',
        'crm' => 'Leads, deals, tasks and automation messages.',
        'ads' => 'Campaigns that could not be created and report problems.',
        'email_marketing' => 'Campaigns that could not be sent.',
        'google' => 'Google syncs that keep failing.',
        'ai' => 'Automations that fail.',
        'team' => 'Invitations, new teammates and access changes.',
        'support' => 'Replies and updates on your support requests.',
        'product' => 'News, tips and offers from Socialeaz.',
    ],

    'cadences' => ['immediate' => 'Immediately', 'daily' => 'Daily digest', 'weekly' => 'Weekly digest', 'off' => 'Off'],

    'details' => [
        'platform' => 'Platform', 'account' => 'Account', 'reason' => 'Reason', 'plan' => 'Plan', 'amount' => 'Amount',
        'ticket' => 'Ticket', 'campaign' => 'Campaign', 'source' => 'Source', 'channel' => 'Channel',
    ],

    'reasons' => [
        'package_ended' => 'your plan no longer includes email marketing',
        'provider_error' => 'the email provider reported an error',
        'unknown' => 'no reason was given',
    ],

    'roles' => ['admin' => 'Admin', 'member' => 'Member'],

    'ui' => [
        'title' => 'Notifications',
        'open' => 'Open Socialeaz',
        'workspace' => 'Workspace',
        'need_help' => 'Need help? Write to',
        'why' => 'You are receiving this because of your :category notifications.',
        'why_workspace' => 'You are receiving this because of your :category notifications for :workspace.',
        'manage' => 'Manage notifications',
        'unsubscribe' => 'Unsubscribe from this type of email',
        'and_more' => 'and :count more in Socialeaz.',
        'all' => 'All', 'unread' => 'Unread', 'mark_all_read' => 'Mark all as read', 'mark_read' => 'Mark as read',
        'dismiss' => 'Dismiss', 'clear_read' => 'Clear read notifications', 'view_all' => 'View all notifications',
        'settings' => 'Notification settings', 'empty' => "You're all caught up.", 'empty_filtered' => 'Nothing here yet.',
        'load_more' => 'Load more', 'loading' => 'Loading…', 'needs_attention' => 'Needs attention',
        'just_now' => 'just now', 'minutes_ago' => ':count m ago', 'hours_ago' => ':count h ago', 'days_ago' => ':count d ago',
        'times' => '×:count',
        'no_access_title' => "You no longer have access to this",
        'no_access_body' => 'The workspace or item this notification points to is no longer available to your account.',
        'link_expired_title' => 'This link has expired',
        'link_expired_body' => 'Notification links stay valid for a limited time. Open your notifications in Socialeaz instead.',
        'go_to_notifications' => 'Go to notifications',
        'unsubscribe_title' => 'Stop these emails?',
        'unsubscribe_body' => 'You will no longer get ":event" emails. You will still see them in your notification center.',
        'unsubscribe_confirm' => 'Unsubscribe',
        'unsubscribed' => 'Done. You will not get these emails any more. You can change this any time in your notification settings.',
        'verify_banner' => 'Please confirm your email address so we can reach you about your account.',
        'verify_resend' => 'Resend email',
        'verify_sent' => 'We sent a new confirmation link to your email.',
    ],

    'preferences' => [
        'title' => 'Notification settings',
        'intro' => 'Choose how Socialeaz keeps you informed. Security and billing alerts are always sent.',
        'general' => 'General',
        'email_enabled' => 'Email notifications',
        'email_enabled_hint' => 'Turning this off stops optional emails. Required security, billing and service alerts are still sent.',
        'language' => 'Email language',
        'language_auto' => 'Same as the app',
        'timezone' => 'Time zone',
        'timezone_auto' => 'Workspace time zone',
        'quiet_hours' => 'Quiet hours',
        'quiet_hours_hint' => 'Hold non-urgent emails during these hours. Critical alerts are always delivered.',
        'from' => 'From', 'to' => 'To',
        'digests' => 'Digests',
        'daily_hour' => 'Daily digest time',
        'weekly_day' => 'Weekly digest day',
        'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'workspace_scope' => 'Applies to',
        'all_workspaces' => 'All workspaces',
        'in_app' => 'In app',
        'email' => 'Email',
        'always' => 'Always',
        'locked' => 'Required',
        'locked_hint' => 'This alert cannot be turned off.',
        'failure_safe_hint' => 'Stays in your notification center so a failure is never missed.',
        'marketing' => 'Product news and offers',
        'marketing_hint' => 'Occasional updates about new features. Separate consent; off unless you turn it on.',
        'save' => 'Save changes',
        'saved' => 'Your notification settings were saved.',
        'event_labels' => [
            'security.welcome' => 'Welcome email', 'security.password_changed' => 'Password changed', 'security.new_login' => 'New sign-in',
            'security.api_token_created' => 'API access added', 'security.api_token_revoked' => 'API access removed',
            'security.account_suspended' => 'Account suspended', 'security.account_reactivated' => 'Account reactivated',
            'billing.payment_succeeded' => 'Payment receipts', 'billing.payment_failed' => 'Payment problems', 'billing.payment_pending' => 'Payment pending',
            'billing.trial_ending' => 'Trial ending', 'billing.trial_expired' => 'Trial ended', 'billing.subscription_ending' => 'Plan ending',
            'billing.subscription_expired' => 'Plan ended',
            'usage.threshold_reached' => '80% of a limit used', 'usage.limit_reached' => 'Limit reached', 'usage.plan_limit_hit' => 'Plan limit blocked an action',
            'team.invitation' => 'Invitations', 'team.invitation_expiring' => 'Invitation expiring', 'team.invitation_accepted' => 'Invitation accepted',
            'team.invitation_expired' => 'Invitation expired', 'team.member_added' => 'Added to a workspace', 'team.member_removed' => 'Removed from a workspace',
            'team.access_changed' => 'Access changed', 'team.workspace_created' => 'Workspace created',
            'connections.expiring' => 'Connection expiring', 'connections.reconnect_required' => 'Reconnect needed',
            'connections.check_error' => 'Connection check failed', 'connections.disconnected' => 'Disconnected by a teammate',
            'publishing.post_failed' => 'Post failed', 'publishing.post_failed_reconnect' => 'Post failed: reconnect needed',
            'publishing.post_interrupted' => 'Publishing interrupted', 'publishing.partially_published' => 'Partly published',
            'publishing.post_published' => 'Post published',
            'inbox.unanswered' => 'Customer waiting for a reply', 'inbox.buying_intent' => 'Buying intent in a message',
            'inbox.conversation_assigned' => 'Conversation assigned to me', 'inbox.ai_reply_failed' => 'AI reply not sent',
            'comments.buying_intent' => 'Buying intent in a comment',
            'crm.lead_assigned' => 'Lead assigned to me', 'crm.lead_unassigned' => 'New lead without an owner', 'crm.deal_assigned' => 'Deal assigned to me',
            'crm.deal_won' => 'Deal won', 'crm.deal_lost' => 'Deal lost', 'crm.task_reminder' => 'Task reminders',
            'crm.automation_notify' => 'Automation messages', 'crm.automation_failed' => 'Automation failed',
            'ads.build_failed' => 'Campaign could not be created', 'ads.report_failed' => 'Scheduled report failed',
            'email_marketing.campaign_failed' => 'Campaign not sent', 'google.sync_failing' => 'Google sync failing',
            'support.ticket_received' => 'Request received', 'support.reply_received' => 'Reply from support',
            'support.ticket_resolved' => 'Request resolved', 'support.ticket_closed' => 'Request closed',
            'support.ticket_new' => 'New ticket (staff)', 'support.ticket_assigned' => 'Ticket assigned (staff)',
            'support.customer_replied' => 'Customer replied (staff)', 'support.ticket_escalated' => 'Ticket escalated (staff)',
        ],
    ],

    'digest' => [
        'daily_subject' => '{1} Your Socialeaz day: 1 update|[2,*] Your Socialeaz day: :count updates',
        'weekly_subject' => '{1} Your week on Socialeaz: 1 update|[2,*] Your week on Socialeaz: :count updates',
        'daily_headline' => 'Your daily summary',
        'weekly_headline' => 'Your weekly summary',
        'preheader' => 'What needs you first, then the highlights.',
        'attention' => 'Needs you',
        'highlights' => 'Highlights',
        'cta' => 'Open notifications',
    ],

    'auth' => [
        'verify_subject' => 'Verify Your Email Address – Welcome to Socialeaz',
        'verify_preheader' => 'Your verification code and link are inside.',
        'verify_headline' => 'Welcome to Socialeaz!',
        'verify_body' => 'Hi :name, thank you for creating your Socialeaz account. To activate your account and access your business growth workspace, please verify your email address.',
        'verify_cta' => 'Verify My Email',
        'verify_note' => 'For security reasons, never share this verification code with anyone. If you did not create this account, you can safely ignore this email.',
        'verify_option_link' => 'Option 1: Verify instantly',
        'verify_option_link_hint' => 'Click the button above to securely verify your email address.',
        'verify_option_code' => 'Option 2: Verify using a code',
        'verify_code_label' => 'Your verification code:',
        'verify_code_hint' => 'Enter this six-digit code on the Socialeaz verification screen. This code expires after :minutes minutes.',
        'reset_subject' => 'Reset your Socialeaz password',
        'reset_preheader' => 'Use this link to choose a new password.',
        'reset_headline' => 'Reset your password',
        'reset_body' => 'We received a request to reset the password for :email.',
        'reset_cta' => 'Reset password',
        'reset_note' => 'This link expires in :minutes minutes. If you did not ask for this, you can ignore this email and your password stays the same.',
    ],

    'events' => [

        // Security
        'security.welcome' => [
            'title' => 'Welcome to Socialeaz',
            'subject' => "Welcome to Socialeaz — let's get you set up",
            'preheader' => 'Connect your first social account to start publishing.',
            'headline' => 'Welcome, :name',
            'body' => 'Your account is ready. Connect your first social account to schedule posts, answer messages in one inbox and track your results.',
            'cta' => 'Connect an account',
        ],
        'security.password_changed' => [
            'title' => 'Your password was changed',
            'subject' => 'Your Socialeaz password was changed',
            'preheader' => 'If this was you, no action is needed.',
            'body' => 'The password for your account was changed on :when from :device. If this was you, there is nothing else to do. If it was not you, reset your password now and contact support.',
            'cta' => 'Secure my account',
        ],
        'security.new_login' => [
            'title' => 'New sign-in to your account',
            'subject' => 'New sign-in to your Socialeaz account',
            'preheader' => 'From :device. If this was you, no action is needed.',
            'body' => 'Your account was signed in to on :when from :device (network :network). If this was you, there is nothing else to do. If not, change your password now.',
            'cta' => 'Review my account',
        ],
        'security.api_token_created' => [
            'title' => 'New API access was added',
            'subject' => 'A new API connection was added to your account',
            'preheader' => '":name" can now act on your account.',
            'body' => 'An API connection named ":name" was created on :when. It can read and publish on your behalf. If you did not set this up, remove it and change your password.',
            'cta' => 'Manage API access',
        ],
        'security.api_token_revoked' => [
            'title' => 'API access was removed',
            'body' => 'The API connection ":name" was removed and can no longer act on your account.',
            'cta' => 'Manage API access',
        ],
        'security.account_suspended' => [
            'title' => 'Your account has been suspended',
            'subject' => 'Your Socialeaz account has been suspended',
            'preheader' => 'Contact support to restore access.',
            'body' => 'Your Socialeaz account has been suspended, so you cannot sign in and scheduled work is paused. If you think this is a mistake, contact our support team.',
            'cta' => 'Contact support',
        ],
        'security.account_reactivated' => [
            'title' => 'Your account is active again',
            'subject' => 'Your Socialeaz account is active again',
            'body' => 'Access to your Socialeaz account has been restored. You can sign in again.',
            'cta' => 'Sign in',
        ],

        // Billing
        'billing.payment_succeeded' => [
            'title' => 'Payment received — your :plan plan is active',
            'preheader' => 'Receipt :receipt · :amount',
            'headline' => 'Thank you, your payment was successful',
            'body' => 'We received :amount for the :plan plan. It is active until :period_end. Keep this email for your records.',
            'cta' => 'View billing',
        ],
        'billing.payment_failed' => [
            'title' => "Action required: your payment didn't go through",
            'preheader' => 'Your :plan plan was not renewed.',
            'headline' => 'Your payment did not go through',
            'body' => 'We could not complete your payment of :amount for the :plan plan. Nothing was charged. Please try again or use another payment method.',
            'cta' => 'Try again',
        ],
        'billing.payment_pending' => [
            'title' => 'Your :method payment is being confirmed',
            'body' => 'Your payment of :amount with :method is waiting for confirmation from the provider. We will activate your plan as soon as it is confirmed.',
            'cta' => 'View billing',
        ],
        'billing.trial_ending' => [
            'title' => '{1} Your free trial ends tomorrow|[2,*] Your free trial ends in :days days',
            'subject' => '{1} Your free trial ends tomorrow|[2,*] Your free trial ends in :days days',
            'preheader' => 'Choose a plan to keep publishing, inbox and AI running.',
            'body' => 'Your free trial ends on :end. Choose a plan before then to keep your scheduled posts, inbox and AI replies working.',
            'cta' => 'Choose a plan',
        ],
        'billing.trial_expired' => [
            'title' => 'Your free trial has ended',
            'subject' => 'Your Socialeaz free trial has ended',
            'body' => 'Your free trial has ended, so scheduled posts, AI replies and team access are paused. Your data is safe. Choose a plan to continue.',
            'cta' => 'Choose a plan',
        ],
        'billing.subscription_ending' => [
            'title' => 'Your :plan plan ends on :end — renew to stay active',
            'preheader' => 'Renew in two minutes to keep publishing, inbox and AI running.',
            'headline' => '{1} Your plan ends tomorrow|[2,*] Your plan ends in :days days',
            'body' => "Your :plan plan ends on :end. Plans on Socialeaz don't renew automatically, so please renew before then. After your plan ends, scheduled posts stop publishing, AI replies pause and team members lose access. Your data is kept safe.",
            'cta' => 'Renew now',
        ],
        'billing.subscription_expired' => [
            'title' => 'Your Socialeaz plan has expired',
            'body' => 'Your :plan plan has ended. Scheduled posts, AI replies and team access are paused until you renew. Your data is safe.',
            'cta' => 'Renew',
        ],

        // Usage
        'usage.threshold_reached' => [
            'title' => "You've used :percent% of your :feature",
            'body' => 'You have used :used of :limit :feature this period. When it runs out, this feature stops until the next period or an upgrade.',
            'cta' => 'View plans',
        ],
        'usage.limit_reached' => [
            'title' => 'Your :feature allowance has run out',
            'subject' => 'Your :feature allowance has run out — some features are paused',
            'body' => 'You have used all of your :feature for this period, so this feature is paused (including automatic AI replies where they use it). Upgrade or wait for the next period to continue.',
            'cta' => 'Upgrade',
        ],
        'usage.plan_limit_hit' => [
            'title' => 'Your plan limit for :feature was reached',
            'body' => 'An action was blocked because your plan allows no more :feature. Upgrade to add more.',
            'cta' => 'View plans',
        ],

        // Team
        'team.invitation' => [
            'title' => ':inviter invited you to :workspace on Socialeaz',
            'preheader' => 'Join the team to manage posts, inbox and leads together.',
            'headline' => "You're invited to join :workspace",
            'body' => ':inviter has invited you to collaborate in :workspace as :role.',
            'cta' => 'Accept invitation',
            'note' => "This invitation expires on :expires. If you weren't expecting it, you can ignore this email.",
        ],
        'team.invitation_expiring' => [
            'title' => 'Your invitation to :workspace expires tomorrow',
            'body' => 'You still have a pending invitation to :workspace on Socialeaz. Accept it before it expires.',
            'cta' => 'Accept invitation',
        ],
        'team.invitation_accepted' => [
            'title' => ':name joined :workspace',
            'body' => ':name accepted your invitation and can now work in :workspace.',
            'cta' => 'View team',
        ],
        'team.invitation_expired' => [
            'title' => 'The invitation for :email expired',
            'body' => 'The invitation sent to :email was not accepted in time. You can send a new one from your team page.',
            'cta' => 'View team',
        ],
        'team.member_added' => [
            'title' => 'You now have access to :workspace',
            'body' => 'You were added to :workspace on Socialeaz as :role.',
            'cta' => 'Open workspace',
        ],
        'team.member_removed' => [
            'title' => 'Your access to :workspace was removed',
            'body' => 'You no longer have access to :workspace on Socialeaz. If you think this is a mistake, contact the workspace owner.',
            'cta' => 'Open Socialeaz',
        ],
        'team.access_changed' => [
            'title' => 'Your access to :workspace has changed',
            'body' => 'Your role in :workspace is now :role, and your module permissions were updated.',
            'cta' => 'Open workspace',
        ],
        'team.workspace_created' => [
            'title' => 'Workspace :workspace was created',
            'body' => 'Connect its social accounts to start publishing.',
            'cta' => 'Open workspace',
        ],

        // Connections
        'connections.expiring' => [
            'title' => '{1} Your :platform connection expires tomorrow|[2,*] Your :platform connection expires in :days days',
            'subject' => '{1} Your :platform connection expires tomorrow|[2,*] Your :platform connection expires in :days days',
            'body' => 'Reconnect :account now so scheduled posts, the inbox and ads keep working without interruption.',
            'cta' => 'Reconnect now',
        ],
        'connections.reconnect_required' => [
            'title' => 'Action required: reconnect :platform to keep publishing',
            'preheader' => ':platform stopped accepting our access.',
            'headline' => ':platform needs to be reconnected',
            'body' => ':platform stopped accepting Socialeaz access for :account. This usually happens after a password change or when permissions are removed. Publishing, messages, comments and ads that use this account are paused (scheduled posts waiting: :scheduled). Reconnecting takes about a minute.',
            'cta' => 'Reconnect :platform',
        ],
        'connections.check_error' => [
            'title' => 'We could not check your :platform connection',
            'body' => 'We will try again automatically. If it keeps failing, reconnect the account.',
            'cta' => 'Open connections',
        ],
        'connections.disconnected' => [
            'title' => ':platform was disconnected by a teammate',
            'body' => ':actor disconnected :platform. Posts, messages and ads that use it are paused until it is connected again.',
            'cta' => 'Open connections',
        ],

        // Publishing
        'publishing.post_failed' => [
            'title' => 'Your :platform post didn\'t publish',
            'subject' => "Action required: your scheduled post didn't publish",
            'preheader' => ':platform rejected the post — here is why.',
            'headline' => 'Your :platform post didn\'t go out',
            'body' => '":excerpt" was not published. Reason: :reason. Nothing was posted, so you can safely fix it and schedule it again.',
            'cta' => 'Review and reschedule',
            'group_title' => ':count posts didn\'t publish',
            'group_subject' => "Action required: :count posts didn't publish",
            'group_headline' => ':count posts didn\'t go out',
            'group_body' => 'These posts were not published. Nothing was posted, so you can fix and reschedule each one.',
            'group_cta' => 'Review posts',
        ],
        'publishing.post_failed_reconnect' => [
            'title' => 'Your :platform post didn\'t publish: reconnect needed',
            'subject' => 'Action required: reconnect :platform — a post did not publish',
            'body' => '":excerpt" was not published because :platform needs to be reconnected. Reconnect the account, then reschedule the post.',
            'cta' => 'Reconnect :platform',
            'group_title' => ':count posts didn\'t publish: reconnect needed',
            'group_subject' => 'Action required: reconnect to publish :count posts',
        ],
        'publishing.post_interrupted' => [
            'title' => 'Please check :platform: we could not confirm your post',
            'body' => 'Publishing ":excerpt" was interrupted, so we cannot be sure whether it reached :platform. Please check your :platform page before posting again to avoid a duplicate.',
            'cta' => 'Review the post',
            'group_title' => 'Please check :count posts we could not confirm',
        ],
        'publishing.partially_published' => [
            'title' => 'Your post went live on :succeeded of :total channels',
            'body' => '":excerpt" was published on :succeeded of :total channels. It did not publish on :failed. Fix those channels and try them again.',
            'cta' => 'Fix the rest',
        ],
        'publishing.post_published' => [
            'title' => 'Your :platform post is live',
            'body' => '":excerpt"',
            'cta' => 'View post',
            'group_title' => ':count posts are live',
        ],

        // Inbox & comments
        'inbox.unanswered' => [
            'title' => ':customer is waiting for a reply on :channel',
            'subject' => 'A customer is waiting for a reply',
            'body' => 'No reply has been sent for :minutes minutes.',
            'cta' => 'Reply now',
            'group_title' => ':count customers are waiting for a reply',
            'group_subject' => ':count customers are waiting for a reply',
            'group_body' => 'These conversations have no reply yet.',
            'group_cta' => 'Open inbox',
        ],
        'inbox.buying_intent' => [
            'title' => 'A customer on :channel is asking to buy',
            'body' => ':customer: ":excerpt"',
            'cta' => 'Reply',
            'group_title' => ':count customers are asking to buy',
        ],
        'inbox.conversation_assigned' => [
            'title' => 'A :channel conversation was assigned to you',
            'body' => 'Conversation with :customer.',
            'cta' => 'Open conversation',
        ],
        'inbox.ai_reply_failed' => [
            'title' => "AI replies couldn't be sent on :channel",
            'body' => 'An automatic reply to :customer could not be delivered. Please reply manually.',
            'cta' => 'Reply manually',
            'group_title' => ':count AI replies could not be sent',
        ],
        'comments.buying_intent' => [
            'title' => 'A comment on :platform shows buying intent',
            'body' => ':author: ":excerpt"',
            'cta' => 'Reply',
            'group_title' => ':count comments show buying intent',
        ],

        // CRM
        'crm.lead_assigned' => [
            'title' => 'New lead assigned to you: :name',
            'preheader' => 'From :source',
            'headline' => 'You have a new lead',
            'body' => ':name (:source). Leads contacted within minutes are far more likely to convert.',
            'cta' => 'Open lead',
            'group_title' => ':count leads were assigned to you',
            'group_cta' => 'Open leads',
        ],
        'crm.lead_unassigned' => [
            'title' => 'New lead without an owner: :name',
            'body' => 'A new lead from :source needs someone to follow up.',
            'cta' => 'Assign lead',
            'group_title' => ':count new leads need an owner',
            'group_cta' => 'Assign leads',
        ],
        'crm.deal_assigned' => ['title' => 'Deal assigned to you: :name', 'cta' => 'Open deal'],
        'crm.deal_won' => ['title' => 'Deal won: :name (:value)', 'cta' => 'View deal'],
        'crm.deal_lost' => ['title' => 'Deal lost: :name (:value)', 'body' => ':reason', 'cta' => 'View deal'],
        'crm.task_reminder' => ['title' => 'Reminder: :subject', 'body' => 'Due :due', 'cta' => 'Open task'],
        'crm.automation_notify' => ['title' => ':automation', 'body' => ':message', 'cta' => 'Open record'],
        'crm.automation_failed' => [
            'title' => 'Automation ":automation" failed',
            'body' => 'An action in this automation could not run. Check its settings.',
            'cta' => 'Fix automation',
            'group_title' => ':count automation runs failed',
        ],

        // Ads
        'ads.build_failed' => [
            'title' => 'Your :platform campaign could not be created',
            'subject' => 'Action required: your :platform campaign was not created',
            'body' => 'Creating the campaign stopped at ":step". Some parts may already exist in your :platform ad account. Review it before trying again.',
            'cta' => 'Review campaign',
        ],
        'ads.report_failed' => [
            'title' => 'Scheduled report ":name" failed to send',
            'body' => 'Check the report schedule and the connected ad account.',
            'cta' => 'Open reports',
        ],

        // Email marketing
        'email_marketing.campaign_failed' => [
            'title' => 'Action required: ":campaign" wasn\'t sent',
            'body' => 'The campaign was returned to draft. Reason: :reason. Fix the problem and schedule it again.',
            'cta' => 'Fix and reschedule',
        ],

        // Google
        'google.sync_failing' => [
            'title' => 'Google :service sync has been failing for a day',
            'body' => 'Reports may be out of date. Reconnect Google or check the selected property.',
            'cta' => 'Open Google settings',
        ],

        // Support
        'support.ticket_received' => [
            'title' => "[#:ticket] We've received your request",
            'body' => 'Thanks for contacting Socialeaz support about ":subject". We will reply as soon as possible.',
            'cta' => 'View ticket',
        ],
        'support.reply_received' => [
            'title' => '[#:ticket] New reply from Socialeaz Support',
            'body' => ':excerpt',
            'cta' => 'View and reply',
            'group_title' => '[#:ticket] :count new replies from Socialeaz Support',
        ],
        'support.ticket_resolved' => [
            'title' => '[#:ticket] Your request has been resolved',
            'body' => 'We marked ":subject" as resolved. If anything is still wrong, reply to reopen it.',
            'cta' => 'Reopen or reply',
        ],
        'support.ticket_closed' => [
            'title' => '[#:ticket] Your request was closed',
            'body' => '":subject" was closed. You can open a new request at any time.',
            'cta' => 'View ticket',
        ],
        'support.ticket_new' => ['title' => '[#:ticket] New ticket — :priority', 'body' => ':subject', 'cta' => 'Open ticket'],
        'support.ticket_assigned' => ['title' => '[#:ticket] Assigned to you — :priority', 'body' => ':subject', 'cta' => 'Open ticket'],
        'support.customer_replied' => [
            'title' => '[#:ticket] The customer replied',
            'body' => ':excerpt',
            'cta' => 'Open ticket',
            'group_title' => '[#:ticket] :count new customer replies',
        ],
        'support.ticket_escalated' => ['title' => '[#:ticket] Escalated to urgent', 'body' => ':subject', 'cta' => 'Open ticket'],
    ],
];
