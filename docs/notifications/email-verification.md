# Email verification: six-digit code + magic link

Branch `email-notification`, 2026-11-26. Builds on the platform email templates ([email-templates.md](email-templates.md)) and the verification gate added on 2026-11-24. Not deployed. Uncommitted.

## 1. Audit summary

**Authentication (before this change)**
- Laravel Breeze routes in `routes/auth.php`, inside the localized (`/en`, `/ar`) group.
- `User` implements `MustVerifyEmail`.
- Signup (`RegisteredUserController`) fires `Registered`. Laravel's own listener then calls `User::sendEmailVerificationNotification()`.
- Verification was a signed `verify-email/{id}/{hash}` link that needs a signed-in session.
- `EnsureEmailIsVerified` (web group, before the workspace swap) blocks `auth` routes for unverified self-registered sellers created after `notifications.verification_since`. Older accounts, staff and invited teammates are never blocked.
- After verification, new sellers continue to Connections, as before.

**Email templates (reused, not duplicated)**
- Admin-managed `platform_email_*` tables with draft/publish/versions/reset, per event × language.
- Content rendered by `TemplateRenderer`: `{{ var }}` substitution, escaped, no code execution.
- `auth.verify_email` already existed as a locked template (it can't be switched off).
- The verification email now always renders through this system. Nothing is hardcoded in controllers or services.

## 2. How it works

```
Signup ─► Registered event ─► User::sendEmailVerificationNotification()
        └► EmailVerification::send(): new 6-digit code + 64-char link token
             (hashes stored, previous ones replaced) ─► BrandedVerifyEmail
             (admin template auth.verify_email or the built-in default) ─► sent immediately
Signup redirects to /verify-email  (six-digit screen; every app page redirects here until verified)

Code:  type/paste on the screen ─► POST verify-email/code ─► verified ─► Connections
Link:  GET  email/verify/link/{token}  ─► "Confirm my email" page (nothing changes)
       POST email/verify/link/{token}  ─► verified ─► Connections (same session) or "Sign in"
Either one deletes the row, so the other credential stops working.
```

## 3. Files

**Created**
- `database/migrations/2026_11_26_100000_create_email_verification_codes_table.php`
- `app/Models/Auth/EmailVerificationCode.php`
- `app/Services/Auth/EmailVerification.php`: issuing, verifying, the link, masking, destination
- `app/Http/Controllers/Auth/EmailVerificationController.php`: screen, code, resend, change email, link page and confirm
- `resources/views/layouts/verification.blade.php`: minimal layout with logo, "Need support?" and the language switch
- `resources/views/auth/verify-link.blade.php`
- `resources/js/verify-email.js` and `resources/js/components/onboarding/EmailVerification.vue`
- `public/assets/css/verify-email.css`
- `lang/en/verification.php`, `lang/ar/verification.php` (identical key sets)
- `tests/Feature/Auth/EmailVerificationCodeTest.php` (13 tests)

**Modified**
- `app/Notifications/Auth/BrandedVerifyEmail.php`: carries the code and token, always uses the template system
- `app/Models/User.php`: sends through the service; `sendAccountEmail()` reports success
- `routes/auth.php`: new routes; resend now uses the new controller
- `app/Http/Middleware/EnsureEmailIsVerified.php`: lets the new endpoints through
- `resources/views/auth/verify-email.blade.php`: now the six-digit screen
- `app/Services/PlatformEmails/TemplateRegistry.php`: new variables, `REQUIRED`, new default content
- `TemplateValidator.php`: `code` block, required-variable check
- `TemplateRenderer.php`: `code` block
- `TemplateMailBuilder.php`: preview sample values
- `PlatformEmailEditor.vue`: `code` block in the editor
- `lang/{en,ar}/notifications.php` (`auth.verify_*` wording) and `lang/{en,ar}/platform_emails.php`
- `vite.config.js` (new entry) and `public/build`
- Test fixtures (new migration) and `EmailVerificationGateTest`

**Removed**
- `EmailVerificationPromptController` and `EmailVerificationNotificationController`, replaced by the new controller.

## 4. Database

`email_verification_codes`: one row per user, replaced on every send and deleted on success. No data migration. Existing users and records are untouched.

| Column | Purpose |
|---|---|
| `user_id` (unique, FK cascade) | Owner |
| `email` | The address these credentials prove. If it changes, they stop working. |
| `code_hash` | HMAC-SHA256 of the code, keyed with `APP_KEY` and bound to user and email |
| `token_hash` (unique) | SHA-256 of the link token |
| `code_expires_at` / `token_expires_at` | 10 / 60 minutes |
| `attempts` | Wrong codes (max 5) |
| `sent_at` | Resend cooldown |

No template records were added. `auth.verify_email` starts on its new built-in default. An existing published custom version is used **only if** it contains both `{{ verification_otp }}` and `{{ verification_url }}`; otherwise the default is sent.

## 5. Routes

All are under the locale prefix.

| Method | Path | Name | Access |
|---|---|---|---|
| GET | `verify-email` | `verification.notice` | Signed in. Screen; JSON for status polling. |
| POST | `verify-email/code` | `verification.code` | Signed in. `throttle:10,1` |
| POST | `email/verification-notification` | `verification.send` | Signed in. `throttle:6,1` plus a 60 s cooldown |
| POST | `verify-email/change-email` | `verification.change-email` | Signed in, unverified. `throttle:5,10` |
| GET | `email/verify/link/{token}` | `verification.link` | Anyone. Shows the confirm page only. `throttle:30,1` |
| POST | `email/verify/link/{token}` | `verification.link.confirm` | Anyone, with CSRF. `throttle:10,1` |
| GET | `verify-email/{id}/{hash}` | `verification.verify` | Kept for emails sent before this change |

## 6. Security

- **Code:** `random_int`, 6 digits, 10 minutes. 5 wrong tries lock it until a resend. Compared with `hash_equals`.
- **Link token:** 64 random characters, 60 minutes, single use, stored as SHA-256.
- **Scanner-safe link:** opening it shows the masked address and a "Confirm my email" button. Only the POST verifies.
- **Single use:**
  - Every send replaces both credentials.
  - Success deletes them.
  - Verification runs in a transaction with row locks, so two tabs at once are harmless.
- **Ownership:** a code can only verify the signed-in person's own account (tested). Changing the email invalidates old credentials.
- **Nothing leaks:**
  - The code and token appear only in the email: not in logs, not in the database, not in responses.
  - The page receives only the **masked** address.
  - No endpoint reveals whether an email exists.
- **Admin safety:**
  - The template can't be disabled (locked).
  - Publishing without the code or the link is refused.
  - A broken older version falls back to the default at send time.
- **Rate limits:** per route, as in §5, plus the server-side 60-second resend cooldown.

## 7. Admin template management

**Email templates → Account: Verify email**, in English and Arabic.

- **New variables:**
  - `verification_url`, `verification_otp`, `otp_expiry`;
  - `company_name`, `company_logo`;
  - the existing `customer_name`, `customer_email`, `support_email`, `application_name`, etc.
- **New block:** **Verification code**, a large spaced box. Available in the editor's block list.
- **Default content:** follows the brief: welcome, Option 1 button, Option 2 code, 10-minute expiry, security note.
- Preview and test send use sample values (code `482915`).

## 8. Screen

Inspired by the reference layout: one large white card, with the logo, "Need support?" and the language switch at the top, and spacious centered content.

- Shows the masked email and six boxes.
- **Typing:** focuses the first box, moves to the next box, accepts digits only.
- **Keys:** Backspace goes back; Arrow, Home and End keys move; Enter submits.
- **Paste and autofill:** pasting anywhere spreads the digits, and the phone's one-time-code autofill works.
- **Submitting:** submits automatically when all six boxes are filled, or with the "Verify Email" button. Shows loading, error (shake, clear, refocus) and success states, then redirects.
- **Resend:** "Resend Code" with a 60-second countdown.
- **Change email:** updates the address and sends a new code.
- **Other:** Sign out; expiry note.
- **Other tabs or devices:** if the email is verified elsewhere (for example with the link on a phone), the page notices within about 5 seconds and continues.
- **Languages:** English (LTR) and Arabic (RTL). The code boxes always run left to right.

## 9. Tests

- `EmailVerificationCodeTest`, 13 tests:
  - only hashes are stored;
  - the right code verifies and kills the link;
  - 5 wrong codes lock;
  - an expired code is refused;
  - resend replaces the code, with the cooldown;
  - **opening the link (scanner) changes nothing** and the POST verifies;
  - confirming while signed in continues to onboarding;
  - expired, tampered and reused links;
  - a code can't verify another account;
  - change email;
  - the admin template is used, and publishing without the code is refused;
  - Arabic readers keep Arabic (RTL);
  - the screen in English and Arabic;
  - masking.
- With the gate, notification, template and CRM suites: **86/86 pass.**
- `tests/Feature/Auth` also contains 19 old Breeze tests. They failed before this change (RefreshDatabase on SQLite) and still do.
- **Browser (headless Chrome, real built bundle, server responses stubbed), 10/10 pass:**
  - first box focused;
  - typing advances;
  - letters ignored;
  - Backspace;
  - arrow keys;
  - pasting `98-76 54` fills all six;
  - a single automatic submission;
  - the wrong-code error clears and refocuses;
  - one-time-code autofill;
  - success and redirect.
- **Screenshots:** desktop EN and AR, 390 px mobile, the link page and the email.
- **Not yet done:** a real signup through the browser that receives the real email (see §11).

## 10. Deployment

1. Deploy, then `php artisan migrate --force` (one new table).
2. `public/build` is committed. Rebuild with `php artisan view:clear && npm run build` if the front end changes.
3. No new environment variables. `TRUSTED_PROXIES` (from 2026-11-24) is only for a local tunnel.
4. Emails sent before the deploy keep working through the old signed link.

## 11. Limitations

- **Live delivery not re-tested.** Locally, Hostinger/MailChannels blocks emails that link to `syncora.test`. A full end-to-end test needs a public `APP_URL` (ngrok) or production.
- The link page doesn't sign the person in. On another device they confirm, then sign in. This is deliberate: an email link shouldn't create a session.
- A custom admin template must keep `{{ verification_otp }}` and `{{ verification_url }}`. The editor enforces this.
