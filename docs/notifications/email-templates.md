# Administrator email templates

Branch `email-notification`, 2026-11-24. Builds on the platform notification system (see [implementation-report.md](implementation-report.md)). Not deployed; not tested with a real mail provider. The admin screens were exercised in headless Chrome against the built assets (see §7), but not yet by a person with a real admin login.

## 1. Audit summary

**Already existed (reused):**
- The platform notification pipeline: 59 catalogue events, queued sends, a delivery log, EN/AR rendering and one shared branded layout.
- `mews/purifier`, which already has an email-safe `email` profile.
- Spatie activitylog for the audit trail.
- `App\Support\Settings` (`admin_settings`) for global settings.
- The `role:admin` route group (admin = super administrator).

**Not reused:**
- The customers' own drag-and-drop marketing designer (`components/email`, `Admin\EmailTemplateController`, tables `email_templates` / `email_template_versions`). It produces whole marketing documents; platform emails need the shared branded layout, RTL, signed per-recipient links and the "why you got this" footer.
- The new feature is kept completely separate: `platform_*` tables, `PlatformEmails` namespaces, `admin.platform-emails.*` routes. The marketing feature is untouched (verified: its tables and controller are unchanged).

**Roles:** only `admin` reaches the screens and actions. `customer_support`, sellers, workspace owners and teammates get 403 (tested). There's no tenant-level template configuration; nothing in the current architecture supports one.

**Architectural risks found:**
- *Name collisions.* The marketing feature already owns `email_templates`, `email_template_versions` and a controller of the same name. I accidentally overwrote that controller during development; it was restored from `HEAD` (it had no local changes) and is unchanged.
- *MySQL identifier limit.* MySQL caps identifiers at 64 characters; the migration now uses short explicit foreign-key names.

## 2. How it works

```
Business event → Notify::event() → NotificationService
   → template switch (Administrator on/off) ── off → no email, in-app notification still created
   → queued SendNotificationEmail
       → PlatformNotificationMail
           → published template for (event, reader's language)?  yes → TemplateRenderer (variables)
                                                                  no  → built-in EN/AR wording
           → shared branded layout (EmailBranding)
   → mail provider → delivery log (status, subject, template version)
```

- **Per language, never mixed.** A template exists per event × language. A reader whose language has no published template gets that language's built-in wording, never another language's custom text.
- **Draft and publish.** Saving a draft never affects customers. Publishing creates a new version. "Reset to default" returns to the built-in wording, and history is kept. Any version can be restored.
- **Grouped emails** (e.g. "3 posts didn't publish") keep the built-in group wording; the editor says so.
- **Account emails.** Password reset and email verification are templates too, but can't be switched off.

## 3. Database

Migration `2026_11_24_100000_create_platform_email_template_tables`. It is guarded, has a full rollback, and was verified on MySQL with migrate → rollback → migrate.

| Table | Purpose |
|---|---|
| `platform_email_templates` | (`key`, `locale`) unique. `content` = published JSON (NULL = built-in), `draft`, `version`, `published_at`, `created_by`, `updated_by` |
| `platform_email_template_versions` | Every publish / reset / restore with content and author |
| `platform_email_template_settings` | Per-event on/off (no row = on) |
| `notification_deliveries` + `subject`, `template_version` | The email log shows what was actually sent and with which version |

Branding is one `email_branding` entry in `admin_settings`; unset fields fall back to config. No data migration was needed: every email starts on its built-in wording.

## 4. Templates and variables

- **61 templates**: the 59 catalogue events plus `auth.verify_email` and `auth.password_reset`. Each has English and Arabic built-in defaults, and all 122 pass the same validator admins use (tested).
- **Organised by module** (the notification categories): security, billing, usage, publishing, connections, inbox, comments, CRM, ads, email marketing, Google, AI, team, support.
- **New events appear automatically** when added to `config/notifications.php`.
- **Variables per event** are derived from the data the event really carries (the placeholders in its built-in wording), so the panel never offers a variable the event can't fill:
  - General variables (every template): `customer_name`, `customer_email`, `workspace_name`, `application_name`, `application_url`, `support_email`, `current_date`, `current_year`, `action_url`.
  - Example for `publishing.post_failed`: `platform`, `excerpt`, `reason`.
- **Resolution:** values are formatted for the reader (dates in their timezone, plan feature names in their language). Unknown variables are rejected when saving. A value that is missing at send time renders as empty text.

The suggested prompt events that Socialeaz doesn't actually emit were not invented, for example: campaign approved, invoice generated, cancellation, FAQ updated, AI content generated. See the notification report's §9 for which ones need new triggers first.

## 5. Administrator UI

Under the admin sidebar, **Email templates** (`/platform-emails`):

- **Dashboard:**
  - search, plus module, status (active, disabled, customised, built-in, has draft) and language filters;
  - pagination and summary counts;
  - each row shows template, module, subject, language, status badges, last updated and the editor's name.
- **Editor** (`platform-email-editor` Vue component):
  - fields: subject, preheader and heading;
  - block body: text with `**bold**`, `*italic*` and `[link](url)`, plus heading, button, information card, event details, image, divider and custom HTML;
  - main button, secondary link and footer note;
  - variable panel (click to insert at the cursor) showing description and example;
  - live desktop/mobile preview in a sandboxed iframe, with sender name, address, subject and preheader;
  - test send;
  - save draft, publish, reset to default, copy to the other language;
  - on/off switch and version history with restore;
  - confirmations before publish, reset and disable, and an unsaved-changes guard.
- **Branding:** app name, logo, website, sender name and email, reply-to, support email, primary and secondary colours, footer line, copyright (`{{ current_year }}`), and social links.
- **Email log:** every attempt with recipient, event, subject, status (queued / sending / sent / sent in a group / failed / suppressed / not sent / in digest), reason, attempts, template version and times. "Sent" means the provider accepted the email, not that it was delivered.

The UI is available in English and Arabic (`lang/{en,ar}/platform_emails.php`, identical key sets).

## 6. Security

- **Access:** `role:admin` on every route, including preview and test (tested for seller and customer_support). CSRF applies to all writes, and test sends are throttled to 10 per minute.
- **Templates are data, never code.** `{{ name }}` is plain string substitution: no Blade, nothing executed (tested with Blade syntax). Values are HTML-escaped and can never become formatting or markup.
- **Links:** only `http(s)`, `mailto` or a variable are accepted when saving, and the result is checked again after substitution, so `javascript:` and `data:` never pass.
- **Custom HTML** goes through HTMLPurifier's `email` profile, which removes scripts, event handlers, forms and styles.
- **Social links** are restricted to http(s).
- **Previews and tests** use sample data only, never stored customer data. They create no notification and change no record (tested).
- **Audit trail:** every save, publish, reset, restore, toggle, test send and branding change goes to `activity_log` (`log_name = platform_emails`).

## 7. Testing

- `tests/Feature/PlatformEmails/PlatformEmailTemplatesTest.php`, 10 tests:
  - access control;
  - validity of all 122 defaults;
  - escaping, unsafe-link and no-code rendering;
  - rejecting unknown variables, unsafe links and scripts;
  - published templates reaching real sends in the right language only (a draft never sends);
  - the off switch stopping only the email, and account emails being un-switchable;
  - reset and version restore;
  - preview and test send having no side effects;
  - copying to another language creating a draft;
  - the password-reset email using its template plus branding.
- Notification, CRM and template suites: 68/68 pass.
- Full suite: 61 failed / 1021 passed. Every remaining failure is pre-existing (the same list as untouched `HEAD`); none are new.
- Live on local MySQL with the database queue: a published template was used for a real send, and the log recorded subject and version `v1`. Test data was removed afterwards.
- Headless Chrome (2026-11-24), using the real built bundle, with pages rendered as an admin in the test environment and API calls answered by the real preview response or stubs:
  - dashboard (desktop EN, mobile AR), editor (desktop EN/AR, mobile EN) and branding page;
  - no JavaScript errors and no horizontal scroll;
  - the editor mounts, inserts variables at the cursor, adds blocks, live-previews in both languages, and posts save-draft and test-send requests.
  - Then live on syncora.test with a real admin login: 20 steps covering login, dashboard and search, editor and preview, variable insertion, draft, publish, version history, test send (arrived in the log mailer), off/on switch, reset, restore, the Arabic editor, branding, the log and mobile layout. All pass, with no JavaScript errors. QA data was removed afterwards.
  - **Bug found and fixed:** after "Reset to default", the editor didn't learn the new version number, so the Restore button for the earlier version was hidden. Reset and restore now return the version.
  - **Bug found and fixed:** the preview and test-send limits shared one per-user throttle counter, so typing (many previews) made "Send test" fail with 429. They now use separate named rate limiters, with a regression test.
  - **Bug found and fixed:** these pages sit inside the app-wide `#app` Vue root, so literal `{{ plan }}` text was compiled by Vue. It threw `ReferenceError` and emptied the dashboard table. The server-rendered pages are now marked `v-pre`, and a regression test checks this.

## 8. Files

**New:**
- `app/Services/PlatformEmails/`: `TemplateRegistry`, `TemplateValidator`, `TemplateRenderer`, `TemplateVariables`, `TemplateMailBuilder`, `EmailTemplates`, `EmailTemplateSwitch`, `EmailBranding`
- `app/Models/PlatformEmails/`: three models
- `app/Http/Controllers/Admin/PlatformEmailTemplateController.php`
- `resources/js/components/admin/PlatformEmailEditor.vue`
- `resources/views/admin/platform-emails/{index,edit,branding,logs,_nav}.blade.php`
- `public/assets/css/platform-emails.css`
- `lang/{en,ar}/platform_emails.php`
- The migration
- The test file

**Modified:**
- `PlatformNotificationMail` (templates, branding, version) and `BrandedPlatformMail` (branded sender)
- Reset and verify notifications
- `NotificationService` and `SendNotificationEmail` (switch, logged subject and version), `NotificationDelivery`
- The email layout (HTML and text: branding, blocks, test banner, footer)
- `routes/web.php`, `resources/js/app.js`, both admin sidebars (one line each), the test fixtures, `public/build`

## 9. Before production

1. Deploy, then run `php artisan migrate --force` **before** the queue workers pick up the new code (the send job writes the two new log columns).
2. Set the sender domain in Email Branding to one authenticated with your provider (SPF, DKIM, DMARC). These are **unverified**.
3. Queue workers and the scheduler are as in the notification report; nothing new to run.
4. Provider delivery, bounce and complaint webhooks are still not connected. Until they are, the log reflects "accepted by provider", not delivery.
5. Click through the editor once with a real admin login (save, publish, reset, versions, test send to your own inbox) before announcing it to the team.
