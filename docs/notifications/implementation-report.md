# Platform notifications: implementation report

Branch `email-notification`, built on `e05b195` against *Socialeaz — Email Notification Audit & Customer Communication Specification* (2026-11-23). Not deployed, not live-tested against a real mail provider.

## 1. Summary

One notification system now carries every platform notification. Modules emit events; a catalogue decides priority, recipients and default channels; each person's preferences, quiet hours and language decide what they get and how. Delivery is queued, deduplicated, retry-safe and logged.

What ships:

- Central pipeline: catalogue, outbox, recipient and preference resolvers, queued email, digests, suppression list, signed links.
- 59 catalogued events across 14 categories, each with English and Arabic wording, all wired to real triggers (see §4).
- One shared branded email layout (EN/AR, RTL, plain-text part, Outlook-safe button) for every platform email, including password reset and verification.
- A unified bell, a full notification center page, and a notification settings page.
- Fixes for audit defects A1–A5, A7–A10 (A6 turned out to be outdated; see §7).

## 2. Architecture

```
Module state change ──► Notify::event('publishing.post_failed', [...])        (any request, webhook, job, model hook)
                          │ one outbox row (notification_events, dedup_key UNIQUE) + job queued after commit
                          ▼
ProcessNotificationEvent  (queue: notifications | notifications-critical)
   catalogue → RecipientResolver (workspace + module permission, actor excluded)
             → PreferenceResolver (mandatory → workspace+event → event → workspace+category → category → default)
             → per person: bell item (user_notifications, grouped)
                           email: immediate | held (delay, quiet hours, group window) | digest | skipped
                          ▼
SendNotificationEmail     claims the delivery row ("sending") before calling the mailer, so a retry never sends twice;
                          re-checks access, relevance (EventGuards) and suppression at send time; groups siblings into one email
SendNotificationDigest    hourly dispatcher picks people whose local slot is now; "Needs you" first; never empty
```

Key decisions:

- **Evolve, don't rewrite.** `CrmNotifier` keeps its public API but emits through the new system. The derived bell items (unread comments, conversations, connection problems) stay and are merged with stored items into one bell.
- **Text is rendered at read time** from translation keys and stored parameters, in the reader's language and timezone. This fixes the CRM actor-locale bug.
- **Observers, not scattered calls.** Post statuses are written in roughly 20 places, so publishing, connection, billing, ticket and inbox events come from model observers on terminal state changes (`NotificationHooks`). The two bulk query updates in `PublishPosts` now report explicitly.
- **Secrets never stored.** One-time links (invitations) travel only inside the encrypted queue job (`transient`). Parameters are scrubbed against a deny-list (token, password, secret and similar).
- **Platform mail is separate** from customers' SendGrid marketing: it uses `NOTIFICATIONS_MAILER` (or the default mailer), never a customer subaccount.

## 3. Database changes

Migration `2026_11_23_100000_create_platform_notification_tables` (guarded, re-runnable, full rollback):

| Table | Purpose | Notable indexes |
|---|---|---|
| `notification_events` | Outbox, one row per occurrence | `dedup_key` UNIQUE; (`workspace_id`, `event`, `occurred_at`) |
| `user_notifications` | Bell / center items per person | (`user_id`, `archived_at`, `read_at`, `id`); (`user_id`, `group_key`, `read_at`) |
| `notification_deliveries` | Every email/digest/in-app attempt | UNIQUE (`event`, `recipient_key`, `channel`) for retry safety; `provider_message_id` |
| `notification_preferences` | Person × workspace (0 = all) × category/event | UNIQUE (`user_id`, `workspace_id`, `scope`, `key`) |
| `notification_digest_items` | Digest buffer | UNIQUE (`user_id`, `cadence`, `event`) |
| `email_suppressions` | Hard bounce / complaint / manual | PK `email` |
| `user_login_devices` | New-device detection (browser + OS + network prefix hash only) | UNIQUE (`user_id`, `fingerprint`) |
| `users` + columns | `locale`, `ui_locale`, `timezone`, `notification_settings` (json), `marketing_opt_in_at` | |

Migration `2026_11_23_100001_move_crm_notifications_into_platform_notifications` copies CRM alert choices from `crm_settings` JSON into `notification_preferences`, and copies unread CRM bell items from the last 30 days into `user_notifications`. Nothing is deleted. Rollback removes only the copied rows.

Verified locally on MySQL: migrate → rollback → migrate, all clean.

## 4. Event catalogue (implemented and wired)

Defaults live in `config/notifications.php`; wording in `lang/{en,ar}/notifications.php`. "M" = mandatory (cannot be turned off). Email "imm" = immediate.

| Event | Trigger | Recipients | Pri | Email default | Dedup / grouping |
|---|---|---|---|---|---|
| security.welcome | email verified | the person | P2 | M | once |
| security.password_changed | any password change (observer) | the person | P0 | M | per change |
| security.new_login | login from an unseen browser/OS/network | the person | P0 | M | per device |
| security.api_token_created / _revoked | Sanctum/MCP token created / deleted | the person (+owner) | P0 / P2 | M / imm | per token |
| security.account_suspended / _reactivated | `is_active` flips | the person (even inactive) | P0 / P1 | M | per change |
| billing.payment_succeeded | `fulfilled_at` set (server-verified) | paying owner | P0 | M | per transaction |
| billing.payment_failed | transaction → rejected | paying owner | P0 | M, 10-min delay, cancelled if a later payment succeeds | per transaction |
| billing.payment_pending | BNPL pending > 15 min (scan) | paying owner | P2 | imm | per transaction |
| billing.trial_ending / trial_expired | scan: 7/3/1 days; day after end | paying owner | P1 / P0 | M | per threshold |
| billing.subscription_ending / _expired | scan: 14/7/3/1 days; +1/+4/+8 days | paying owner | P0 | M | per threshold |
| usage.threshold_reached / limit_reached | allowance crosses 80% / 100% | owner | P1 / P0 | imm / M | per period |
| usage.plan_limit_hit | a capacity check refuses an action | owner | P2 | imm | per feature per week |
| team.invitation | invite created (link emailed; no more copying by hand) | invitee address | P1 | M | per token |
| team.invitation_expiring / _expired | scan | invitee / admins | P3 | imm / bell only | per invitation |
| team.invitation_accepted | accept | owner + admins | P2 | imm | per workspace |
| team.member_added / _removed / access_changed | team service | the member | P2 / P1 / P2 | M / M / imm (5-min group) | per change |
| team.workspace_created | workspace created | owner | P3 | bell only | once |
| connections.expiring | status → expiring; reminder 1 day before | owner + admins | P1 | imm (failure-safe) | per expiry date |
| connections.reconnect_required | → needs_reauth / expired / revoked; reminder after 48 h | owner + admins | P0 | M | per status per day |
| connections.check_error / disconnected | → error / teammate disconnect | admins / owner | P2 | bell only / imm | per day |
| publishing.post_failed | post → failed | owner + members with publishing | P1 | imm, 5-min group (failure-safe) | per failure; grouped "3 posts didn't publish" |
| publishing.post_failed_reconnect | failure caused by lost access | same | P0 | M | grouped |
| publishing.post_interrupted | stuck claim > 30 min | same | P1 | imm | "check the platform before re-posting" |
| publishing.partially_published | multi-platform group finished, some failed | same | P1 | imm | one per group, never per platform |
| publishing.post_published | post/group live | same | P3 | daily digest | one per group |
| inbox.unanswered | scan: customer spoke last, no reply for 60 min | assignee, else inbox members | P1 | imm, 30-min group | per waiting episode |
| inbox.buying_intent | inbound message matches `BuyingIntent` | assignee, else inbox members | P1 | imm, 10-min group | per conversation per day |
| inbox.conversation_assigned | `assigned_user_id` changed | new assignee | P2 | imm | per assignment |
| inbox.ai_reply_failed | AI (bot) outbound → failed | owner + admins | P1 | imm, 60-min group | per message |
| comments.buying_intent | comment matches `BuyingIntent` | comments members | P1 | imm, 60-min group | per comment |
| crm.lead_assigned / lead_unassigned / deal_assigned / deal_won / deal_lost / task_reminder | existing CRM hooks via `CrmNotifier` | assignee / CRM managers | P1–P3 | imm (deal_lost: daily) | as before; leads grouped |
| crm.automation_notify | automation "notify" action | chosen member | P2 | imm (respects preferences) | per run |
| crm.automation_failed | automation run failed | owner + admins | P2 | imm, 60-min group | per run |
| ads.build_failed | `AdBuilderRun` → failed | actor + admins | P1 | imm | per run |
| ads.report_failed | report schedule `last_error` set | admins | P2 | bell only | per day |
| email_marketing.campaign_failed | campaign → failed, or returned to draft by the scheduler | email-marketing members | P1 | imm | per change |
| google.sync_failing | scan: a service failing > 24 h with no success | owner + admins | P2 | imm | every 3 days |
| support.ticket_received / reply_received / ticket_resolved / ticket_closed | ticket created, staff reply, status change | requester account owner | P1–P3 | M (closed: imm) | per message/status; billing/account replies omit the text |
| support.ticket_new / ticket_assigned / customer_replied / ticket_escalated | ticket lifecycle | support staff / assignee | P1 | imm | per change |

Every event is in-app and email capable, localized EN/AR, and has a deep link opened through a signed `/n/{event}/{user}` route that re-checks access and switches to the right workspace.

## 5. UI changes

- **Bell** (`NotificationCenter.vue`): one feed merging stored notifications with the live derived items. It shows tone icons, a critical marker, grouped counts, the workspace name, "Mark all as read", a settings shortcut and "View all". It is translated and mobile-friendly. Teammates now see only derived items for modules they can use. Before this change the endpoint returned 403 inside a workspace and the bell stayed silently empty.
- **Notification center** (`/notifications/center`): All/Unread tabs, category filters, mark read, dismiss, mark all read, clear read, load more.
- **Notification settings** (`/notifications/settings`, user menu → "Notification settings"):
  - global email switch, email language, timezone, quiet hours, daily digest hour and weekly digest day;
  - per-event in-app toggle and email cadence (Immediately / Daily / Weekly / Off), with only allowed options shown;
  - required alerts shown locked, and failure-safe alerts keep the bell on;
  - per-workspace overrides;
  - separate marketing consent.
- **CRM settings → Notifications** tab keeps working and now saves into the same preferences.
- **Emails**: shared layout at `resources/views/emails/notifications/layout.blade.php`, plus a text part.
- **Verification banner** for accounts created after `NOTIFICATIONS_VERIFICATION_SINCE` (existing users are not nagged; nothing is blocked).
- **Profile page**: "Current password" field.

Review the rendered emails with `php artisan notifications:preview [event] --locale=ar --count=3`, which writes HTML files to `storage/app/notification-previews`.

## 6. Files changed

**New**
- Config and lang: `config/notifications.php`, `lang/{en,ar}/notifications.php`
- Services (`app/Services/Notifications/`): `Notify`, `NotificationCatalogue`, `NotificationService`, `RecipientResolver`, `PreferenceResolver`, `WorkspaceAccess`, `RecipientContext`, `NotificationText`, `NotificationPresenter`, `EventGuards`, `Links`, `NotificationHooks`, `NotificationScanner`
- Emitters (`app/Services/Notifications/Emitters/`): `Security`, `Connection`, `Publishing`, `Billing`, `Usage`, `Team`, `Module`, `Support`
- Jobs: `ProcessNotificationEvent`, `SendNotificationEmail`, `SendNotificationDigest`
- Mail: `PlatformNotificationMail`, `BrandedPlatformMail`; `app/Notifications/Auth/BrandedVerifyEmail`, `BrandedResetPassword`
- Models (`app/Models/Notifications/`): six models for the new tables
- Controllers: `Admin/NotificationCenterController`, `NotificationLinkController`
- Middleware: `RememberUiLocale`
- Commands: `notifications:scan`, `notifications:digests`, `notifications:prune`, `notifications:preview`
- Views: `admin/notifications/{index,settings}`, `notifications/{message,unsubscribe}`, `emails/notifications/{layout,layout-text}`
- CSS: `public/assets/css/notifications.css`
- Migrations: the two above
- Tests: `tests/Feature/Notifications/{PlatformNotificationsTest,NotificationEmittersTest,SupportNotificationsTest}.php` and `Concerns/CreatesNotificationTables.php`

**Modified**
- Models: `User` (MustVerifyEmail, locale preference, branded reset/verify, dead `notifications()` relation removed), `SellerTeamMember` (notification routes allowed for teammates)
- Services: `CrmNotifier` (now an adapter), `AutomationEngine` (notify action through the system), `PackageUsageService` (thresholds), `SellerTeamService` (team events), `WorkspaceService`
- Controllers: `NotificationController` (unified bell), CRM overview and settings controllers, `ProfileController` + `ProfileUpdateRequest` (A1), `RegisteredUserController` (Registered event)
- Commands: `PublishPosts`
- Routing and bootstrap: `UseSellerWorkspace`, `AppServiceProvider`, `bootstrap/app.php` (schedule, middleware, CSRF exemption for one-click unsubscribe), `routes/web.php`
- Views and assets: navbar, `layouts/app`, profile view, `profile` lang files, `NotificationCenter.vue`, `public/build`
- Tests: `Crm/CrmNotificationsAiTest` + `CrmFixtures` (assert the new storage), `NotificationIndexTest`

**Deleted** (unreferenced): `app/Providers/EventServiceProvider.php` (unregistered; imported ~13 classes that don't exist), `app/Models/SupportTicket.php`, `app/Models/SupportTicketMessage.php`. The `support_tickets` table is untouched.

## 7. Security

- **A1 fixed:** the profile path now requires `current_password`, uses `Password::defaults()` (minimum 8 instead of 6), signs out other sessions and rotates the remember token, and sends an alert.
- **A2 fixed:** email verification on email/password sign-up (branded, queued); welcome email on verification.
- New-sign-in, API-token and suspension alerts. The device fingerprint stores no raw IP or user agent.
- Workspace isolation: recipients must be the owner or an accepted teammate with the module. Every endpoint is scoped to the real signed-in person, with no cross-user read/dismiss (tested). Email links are signed, bound to their recipient, re-check access and switch workspace at click time.
- No credentials in stored payloads (deny-list, tested). Invitation links are never written to the database (tested). Billing/account ticket replies are not quoted in email (tested).
- Loop guard: nothing inside a notification job can emit another notification.
- Notification routes don't require an active plan, so lapsed owners still receive billing notices.

**Audit findings that were outdated:**
- the bell was already a Vue component merging CRM alerts;
- tickets were already consolidated onto one model;
- there is no social login (SEC-06 is n/a);
- A6 does not reproduce: hourly automation runs already sign in as the workspace account.

## 8. Testing

- New: 24 tests across the three new files. Updated: CRM notifications and bell tests. 31/31 pass (446 assertions). Coverage:
  - EN/AR completeness for every event;
  - recipients and permissions, actor exclusion, dedup, retry safety, grouping;
  - preferences, mandatory and failure-safe rules, quiet hours (P0/P1/P2), suppression;
  - reader-locale rendering and RTL, credential scrubbing, loop guard, one-click unsubscribe;
  - multi-platform consolidation, interrupted vs failed;
  - receipts only after fulfilment, renewal reminders once, invitation link not stored, usage thresholds;
  - password change rules, center isolation, settings validation, signed-link ownership;
  - the full ticket lifecycle, and pages rendering in both languages.
- Full suite: 61 failed / 1010 passed on this branch versus 66 failed / 982 passed on untouched `HEAD`. Every remaining failure is pre-existing (identical list); 5 previously failing tests now pass; there are no new failures.
- Local MySQL smoke test with the database queue: event → bell item → grouped delay → email (log mailer) → delivery `sent` with a message ID. Test data removed afterwards.
- **Not tested:** a real mail provider, real email clients (Gmail, Outlook, Apple Mail, dark mode), a browser walkthrough of the new pages, load.

## 9. Remaining work

**Not implemented** (needs new detection, provider APIs or business input):
- Ads monitoring by polling (ADS-03/04/05/06/08)
- Google reviews, traffic and search changes (GGL-03..06)
- Email-marketing deliverability and reports (EML-03..08)
- AI knowledge-gap and weekly summaries (AI-02/04), AI auto-pause notice (AI-03)
- Sentiment (INB-07/CMT-06), comment spikes (CMT-05), "every new conversation" opt-in (INB-02)
- Ticket waiting reminder and SLA timers (SUP-06/09)
- Approval workflow events (TEAM-10/PUB-14; the feature doesn't exist)
- Lifecycle/onboarding emails (ONB-01..06) and marketing sends (ONB-07; consent is captured only)
- CRM extras (CRM-04/11/12/14/15/17/19/20)
- Per-workspace owner defaults
- Real-time push for the bell (it polls every 25 s)

**Requires infrastructure or provider configuration:**
- Choose the transactional provider and set `NOTIFICATIONS_MAILER`.
- Authenticate the sending domain (SPF, DKIM, DMARC). These are **unverified**; I had no access.
- Connect the provider's bounce/complaint webhook to `email_suppressions`. The table and send-time check exist; webhook ingestion does not, because it depends on the provider.
- Run workers for the three new queues.
- Consider moving queues from the database driver to Redis before high volume.

**Requires business/legal validation:**
- Receipt emails show only recorded amounts. No VAT lines and no PDF/ZATCA e-invoice were invented; confirm the requirement with your accountant.
- Set the legal footer line (`NOTIFICATIONS_FOOTER_ADDRESS`).

**Behaviour changes to announce:**
- Invitations are now emailed automatically.
- Sign-ups receive a verification email.
- Changing the password on the profile page needs the current password (minimum 8).
- CRM "unassigned lead" and "deal assigned" emails now default to on (per the audit); anyone who had chosen otherwise keeps their choice.

## 10. Deployment

1. Deploy the code (`public/build` is committed).
2. `php artisan migrate --force`. Both migrations are additive; review the unrelated pending `2026_11_16_000001_add_mobile_to_users_table` separately.
3. Environment:
   - `NOTIFICATIONS_MAILER` (transactional mailer name)
   - `NOTIFICATIONS_FROM_ADDRESS`, `NOTIFICATIONS_FROM_NAME`, `NOTIFICATIONS_REPLY_TO`
   - `NOTIFICATIONS_FOOTER_ADDRESS`
   - `NOTIFICATIONS_VERIFICATION_SINCE` (deploy date)
4. Workers, in priority order:
   ```
   php artisan queue:work --queue=notifications-critical,notifications,digests,default --tries=5
   ```
   Or run dedicated workers per queue. The jobs carry their own retries and backoff.
5. Scheduler already registered:
   - `notifications:scan` every 10 minutes
   - `notifications:digests` hourly at :02
   - `notifications:prune` daily at 03:40
6. `php artisan config:cache && php artisan view:clear`. Note: `route:cache` already fails in this repo for an unrelated localization reason.
7. Monitoring:
   - `notification_deliveries.status` (`failed`, `suppressed`, stuck `sending`)
   - `failed_jobs`
   - `Notification hook skipped` / `Notification not emitted` warnings in the log
8. Rollback: revert the code, then roll back the two migrations by path (newest first). CRM data was copied, never moved, so the old CRM storage is intact.

## Update 2026-11-24: emails sent immediately (no queue worker)

At the product owner's request, notification emails no longer go through the queue.
- **Due now:** sent in the same request right after the database commit, as soon as the action happens. This covers signup verification, password reset, and every notification event that has no delay.
- **Held back:** quiet hours, the grouping window (e.g. `publishing.post_failed` 5 min) and event delays (e.g. `billing.payment_failed` 10 min). These are sent by `notifications:send-due`, which the scheduler runs every minute. It also retries failed sends, up to 5 attempts, after which the email is marked failed.
- **Digests:** sent directly by `notifications:digests`.
- **Errors never break the action.** A mail-server error is logged, and the delivery stays retryable.
- **Requirement:** the scheduler (`php artisan schedule:work` locally, cron in production) must run for held-back emails. A queue worker is no longer needed for notifications.
- **Trade-off:** the request that triggers an email now waits for the SMTP call, about 1–2 s per email.
- **Links use `APP_URL`.** Laravel builds links from the current request's host, so emails sent during a request linked to whatever address the person used (locally `syncora.test`, which Hostinger/MailChannels blocks as `[CS] Message blocked`). `Links::withPublicRoot()` now builds every email (notifications, verification, password reset, admin test send) on `APP_URL`, then restores the site's own links.
