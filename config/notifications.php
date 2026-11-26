<?php

/*
|--------------------------------------------------------------------------
| Platform notification catalogue (single source of truth)
|--------------------------------------------------------------------------
| Every platform notification is declared here. Modules only emit events
| (App\Services\Notifications\Notify::event()); who hears about it, on which
| channel and when is decided from this file plus each person's preferences.
|
| Keys per event (defaults in App\Services\Notifications\NotificationCatalogue):
|   category     preference / bell grouping (see 'categories')
|   priority     0 critical · 1 high · 2 normal · 3 low (audit Appendix C)
|   mandatory    cannot be turned off (security, money, service already interrupted)
|   in_app       default for the bell
|   email        default email cadence: immediate | daily | weekly | off
|   allowed      email cadences a person may choose
|   recipients   resolver strategies: users, owner, admins, members, staff, emails
|   fallback     use only the first strategy that finds anyone (assignee, else the team)
|   module       team permission module a member needs to hear about it
|   include_actor  the person who caused it is told too (security, receipts)
|   failure_safe   at least one channel always stays on
|   group        minutes: repeated events for one person become one email
|   delay        minutes before the email goes out (and a chance to cancel it)
|   tone         email status chip: success | info | warning | action | critical | report
|   section      digest section: attention | highlights
|
| Wording lives in lang/{en,ar}/notifications.php under events.<event>.
*/

return [

    'verification_since' => env('NOTIFICATIONS_VERIFICATION_SINCE', '2026-11-23'),

    'from' => [
        'address' => env('NOTIFICATIONS_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'notifications@socialeaz.com')),
        'name' => env('NOTIFICATIONS_FROM_NAME', 'Socialeaz'),
        'reply_to' => env('NOTIFICATIONS_REPLY_TO', 'support@socialeaz.com'),
    ],

    // Internal alerts for the Socialeaz team (comma-separated addresses; empty = off).
    'staff_alerts' => [
        'signup' => env('NOTIFICATIONS_SIGNUP_ALERT_TO', 'support@socialeaz.com'),
        // Sender for team alerts. Must differ from the recipient: mail "from yourself" sent by an outside
        // service (Resend / SES) is a spoofing pattern, and Hostinger's filter puts it in Junk.
        'from' => env('NOTIFICATIONS_ALERTS_FROM', 'alerts@socialeaz.com'),
        'from_name' => env('NOTIFICATIONS_ALERTS_FROM_NAME', 'Socialeaz Alerts'),
    ],

    // Legal footer line (company address / CR number) - set per environment.
    'footer_address' => env('NOTIFICATIONS_FOOTER_ADDRESS', 'socialeaz.com'),

    // Platform mail uses its own mailer (never a customer's SendGrid subaccount).
    'mailer' => env('NOTIFICATIONS_MAILER'),

    'queues' => [
        'critical' => env('NOTIFICATIONS_CRITICAL_QUEUE', 'notifications-critical'),
        'default' => env('NOTIFICATIONS_QUEUE', 'notifications'),
        'digests' => env('NOTIFICATIONS_DIGEST_QUEUE', 'digests'),
    ],

    'defaults' => [
        'timezone' => 'Asia/Riyadh',
        'daily_digest_hour' => 8,
        'weekly_digest_day' => 0, // Sunday (KSA work week)
        'weekly_digest_hour' => 9,
        'inbox_sla_minutes' => 60,
        'p2_emails_per_hour' => 6,
        'retention_days' => 180,
    ],

    'categories' => [
        'security' => ['icon' => 'bx-shield-quarter', 'locked' => true],
        'billing' => ['icon' => 'bx-credit-card'],
        'usage' => ['icon' => 'bx-tachometer'],
        'publishing' => ['icon' => 'bx-send', 'module' => 'publishing'],
        'connections' => ['icon' => 'bx-plug'],
        'inbox' => ['icon' => 'bx-message-rounded-dots', 'module' => 'inbox'],
        'comments' => ['icon' => 'bx-comment-detail', 'module' => 'comments'],
        'crm' => ['icon' => 'bx-id-card', 'module' => 'crm'],
        'ads' => ['icon' => 'bx-bullseye', 'module' => 'advertising'],
        'email_marketing' => ['icon' => 'bx-envelope', 'module' => 'email_marketing'],
        'google' => ['icon' => 'bxl-google', 'module' => 'google_analytics'],
        'ai' => ['icon' => 'bx-bot'],
        'team' => ['icon' => 'bx-group'],
        'support' => ['icon' => 'bx-support'],
        'product' => ['icon' => 'bx-rocket', 'marketing' => true],
    ],

    'events' => [

        // ── Security & account ─────────────────────────────────────────────
        'security.welcome' => ['category' => 'security', 'priority' => 2, 'mandatory' => true, 'in_app' => false, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'success'],
        'security.password_changed' => ['category' => 'security', 'priority' => 0, 'mandatory' => true, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'critical'],
        'security.new_login' => ['category' => 'security', 'priority' => 0, 'mandatory' => true, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'critical'],
        'security.api_token_created' => ['category' => 'security', 'priority' => 0, 'mandatory' => true, 'recipients' => ['users', 'owner'], 'include_actor' => true, 'tone' => 'critical'],
        'security.api_token_revoked' => ['category' => 'security', 'priority' => 2, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'info'],
        'security.account_suspended' => ['category' => 'security', 'priority' => 0, 'mandatory' => true, 'in_app' => false, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'critical'],
        'security.account_reactivated' => ['category' => 'security', 'priority' => 1, 'mandatory' => true, 'in_app' => false, 'recipients' => ['users'], 'include_actor' => true, 'tone' => 'success'],

        // ── Billing (manual renewal: nothing renews by itself) ─────────────
        'billing.payment_succeeded' => ['category' => 'billing', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'include_actor' => true, 'tone' => 'success'],
        'billing.payment_failed' => ['category' => 'billing', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'include_actor' => true, 'tone' => 'critical', 'delay' => 10],
        'billing.payment_pending' => ['category' => 'billing', 'priority' => 2, 'recipients' => ['owner'], 'include_actor' => true, 'tone' => 'info'],
        'billing.trial_ending' => ['category' => 'billing', 'priority' => 1, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'warning'],
        'billing.trial_expired' => ['category' => 'billing', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'critical'],
        'billing.subscription_ending' => ['category' => 'billing', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'warning'],
        'billing.subscription_expired' => ['category' => 'billing', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'critical'],

        // ── Usage & limits ─────────────────────────────────────────────────
        'usage.threshold_reached' => ['category' => 'usage', 'priority' => 1, 'recipients' => ['owner'], 'tone' => 'warning'],
        'usage.limit_reached' => ['category' => 'usage', 'priority' => 0, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'critical'],
        'usage.plan_limit_hit' => ['category' => 'usage', 'priority' => 2, 'recipients' => ['owner'], 'tone' => 'warning'],

        // ── Team & workspace ───────────────────────────────────────────────
        'team.invitation' => ['category' => 'team', 'priority' => 1, 'mandatory' => true, 'in_app' => false, 'recipients' => ['emails'], 'tone' => 'info'],
        'team.invitation_expiring' => ['category' => 'team', 'priority' => 3, 'in_app' => false, 'recipients' => ['emails'], 'tone' => 'warning'],
        'team.invitation_accepted' => ['category' => 'team', 'priority' => 2, 'recipients' => ['admins'], 'tone' => 'success'],
        'team.invitation_expired' => ['category' => 'team', 'priority' => 3, 'email' => 'off', 'recipients' => ['admins'], 'tone' => 'info'],
        'team.member_added' => ['category' => 'team', 'priority' => 2, 'mandatory' => true, 'recipients' => ['users'], 'tone' => 'info'],
        'team.member_removed' => ['category' => 'team', 'priority' => 1, 'mandatory' => true, 'in_app' => false, 'recipients' => ['users'], 'tone' => 'info'],
        'team.access_changed' => ['category' => 'team', 'priority' => 2, 'recipients' => ['users'], 'tone' => 'info', 'group' => 5],
        'team.workspace_created' => ['category' => 'team', 'priority' => 3, 'email' => 'off', 'recipients' => ['owner'], 'include_actor' => true, 'tone' => 'success'],

        // ── Connections (root cause of most other failures) ────────────────
        'connections.expiring' => ['category' => 'connections', 'priority' => 1, 'recipients' => ['admins'], 'tone' => 'warning', 'failure_safe' => true],
        'connections.reconnect_required' => ['category' => 'connections', 'priority' => 0, 'mandatory' => true, 'recipients' => ['admins'], 'include_actor' => true, 'tone' => 'action'],
        'connections.check_error' => ['category' => 'connections', 'priority' => 2, 'email' => 'off', 'recipients' => ['admins'], 'tone' => 'warning'],
        'connections.disconnected' => ['category' => 'connections', 'priority' => 2, 'recipients' => ['owner'], 'tone' => 'info'],

        // ── Publishing ─────────────────────────────────────────────────────
        'publishing.post_failed' => ['category' => 'publishing', 'priority' => 1, 'module' => 'publishing', 'recipients' => ['members'], 'tone' => 'action', 'failure_safe' => true, 'group' => 5, 'allowed' => ['immediate', 'daily', 'off']],
        'publishing.post_failed_reconnect' => ['category' => 'publishing', 'priority' => 0, 'mandatory' => true, 'module' => 'publishing', 'recipients' => ['members'], 'tone' => 'action', 'group' => 5],
        'publishing.post_interrupted' => ['category' => 'publishing', 'priority' => 1, 'module' => 'publishing', 'recipients' => ['members'], 'tone' => 'warning', 'failure_safe' => true, 'group' => 5],
        'publishing.partially_published' => ['category' => 'publishing', 'priority' => 1, 'module' => 'publishing', 'recipients' => ['members'], 'tone' => 'action', 'failure_safe' => true],
        'publishing.post_published' => ['category' => 'publishing', 'priority' => 3, 'module' => 'publishing', 'recipients' => ['members'], 'email' => 'daily', 'tone' => 'success', 'section' => 'highlights'],

        // ── Inbox & comments (email is a safety net, never a mirror) ───────
        'inbox.unanswered' => ['category' => 'inbox', 'priority' => 1, 'module' => 'inbox', 'recipients' => ['users', 'members'], 'fallback' => true, 'tone' => 'action', 'group' => 30],
        'inbox.buying_intent' => ['category' => 'inbox', 'priority' => 1, 'module' => 'inbox', 'recipients' => ['users', 'members'], 'fallback' => true, 'tone' => 'action', 'group' => 10],
        'inbox.conversation_assigned' => ['category' => 'inbox', 'priority' => 2, 'module' => 'inbox', 'recipients' => ['users'], 'tone' => 'info'],
        'inbox.ai_reply_failed' => ['category' => 'inbox', 'priority' => 1, 'module' => 'inbox', 'recipients' => ['admins'], 'tone' => 'action', 'group' => 60],
        'comments.buying_intent' => ['category' => 'comments', 'priority' => 1, 'module' => 'comments', 'recipients' => ['members'], 'tone' => 'action', 'group' => 60],

        // ── CRM (aliases of the former CrmNotifier events) ─────────────────
        'crm.lead_assigned' => ['category' => 'crm', 'priority' => 1, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'info', 'group' => 2],
        'crm.lead_unassigned' => ['category' => 'crm', 'priority' => 1, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'action', 'group' => 10],
        'crm.deal_assigned' => ['category' => 'crm', 'priority' => 2, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'info'],
        'crm.deal_won' => ['category' => 'crm', 'priority' => 2, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'success', 'section' => 'highlights'],
        'crm.deal_lost' => ['category' => 'crm', 'priority' => 3, 'module' => 'crm', 'recipients' => ['users'], 'email' => 'daily', 'tone' => 'info', 'section' => 'highlights'],
        'crm.task_reminder' => ['category' => 'crm', 'priority' => 1, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'action'],
        'crm.automation_notify' => ['category' => 'crm', 'priority' => 2, 'module' => 'crm', 'recipients' => ['users'], 'tone' => 'info'],
        'crm.automation_failed' => ['category' => 'ai', 'priority' => 2, 'module' => 'crm', 'recipients' => ['admins'], 'tone' => 'warning', 'group' => 60],

        // ── Ads ────────────────────────────────────────────────────────────
        'ads.build_failed' => ['category' => 'ads', 'priority' => 1, 'module' => 'advertising', 'recipients' => ['users', 'admins'], 'include_actor' => true, 'tone' => 'action'],
        'ads.report_failed' => ['category' => 'ads', 'priority' => 2, 'module' => 'advertising', 'email' => 'off', 'recipients' => ['admins'], 'tone' => 'warning'],

        // ── Email marketing (the customer's own campaigns) ─────────────────
        'email_marketing.campaign_failed' => ['category' => 'email_marketing', 'priority' => 1, 'module' => 'email_marketing', 'recipients' => ['members'], 'tone' => 'action'],

        // ── Google ─────────────────────────────────────────────────────────
        'google.sync_failing' => ['category' => 'google', 'priority' => 2, 'module' => 'google_analytics', 'recipients' => ['admins'], 'tone' => 'warning'],

        // ── Support ────────────────────────────────────────────────────────
        'support.ticket_received' => ['category' => 'support', 'priority' => 1, 'mandatory' => true, 'recipients' => ['owner'], 'include_actor' => true, 'tone' => 'info'],
        'support.reply_received' => ['category' => 'support', 'priority' => 1, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'action', 'group' => 5],
        'support.ticket_resolved' => ['category' => 'support', 'priority' => 2, 'mandatory' => true, 'recipients' => ['owner'], 'tone' => 'success'],
        'support.ticket_closed' => ['category' => 'support', 'priority' => 3, 'recipients' => ['owner'], 'tone' => 'info'],
        'support.new_signup' => ['category' => 'support', 'priority' => 1, 'in_app' => false, 'recipients' => ['emails'], 'tone' => 'success', 'staff' => true, 'sender' => 'alerts'],
        'support.ticket_new' => ['category' => 'support', 'priority' => 1, 'recipients' => ['staff'], 'tone' => 'info', 'staff' => true],
        'support.ticket_assigned' => ['category' => 'support', 'priority' => 1, 'recipients' => ['users'], 'tone' => 'info', 'staff' => true],
        'support.customer_replied' => ['category' => 'support', 'priority' => 1, 'recipients' => ['users', 'staff'], 'fallback' => true, 'tone' => 'action', 'staff' => true, 'group' => 5],
        'support.ticket_escalated' => ['category' => 'support', 'priority' => 1, 'recipients' => ['users', 'staff'], 'tone' => 'critical', 'staff' => true],
    ],
];
