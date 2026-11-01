# Connection Hub — Capability Authorization

Status: **implemented locally, not live-tested** · 2026-11-01 · Builds on [connection-audit.md](connection-audit.md) and [connection-hub-design.md](connection-hub-design.md).

Customers choose capabilities (Publishing, Ads, Inbox, Comments, Insights) on a platform's panel. The backend plans the provider consents those capabilities need, walks the customer through them one at a time, and reports each capability's real state, which comes from the scopes the provider actually granted.

## 1. What already existed (reused, not replaced)

| Piece | Where |
|---|---|
| One driver per platform; each driver's steps are its provider consents | `app/Services/Connections/Drivers/*` |
| Connections table: encrypted tokens, `granted_scopes`, derived `capabilities`, status | `SocialConnection`, `ConnectionRecorder` |
| Granted-scope parsing per provider | `GrantedScopes` |
| Scope → capability maps | `config/connections.php` → `capabilities` |
| Expiry/validation job, notifications | `connections:check-status` |
| OAuth state checks, callbacks, asset discovery | the existing module callbacks (unchanged) |

## 2. Provider authorization matrix

Classification: **Shared** = same consent as other capabilities · **Separate** = its own consent/app · **Approval** = user consent alone is insufficient · **Unsupported** · **Unverified**.

| Provider | Capability | Consent (step) | Scopes requested today | Provider approval | Account | Class | Hub |
|---|---|---|---|---|---|---|---|
| Meta | Publishing | FLfB `meta.login` | `pages_manage_posts`, `instagram_content_publish` (+ `pages_show_list`, `pages_read_engagement`, `instagram_basic`, `business_management`) | App Review, Advanced Access | FB Page; IG professional linked to it | Shared | Selectable |
| Meta | Ads | `meta.login` | `ads_management`, `ads_read`, `pages_manage_ads` | App Review | Ad account | Shared | Selectable |
| Meta | Inbox | `meta.login` (WhatsApp: `meta.whatsapp`, Embedded Signup) | `pages_messaging`, `instagram_manage_messages` | App Review; webhooks | Page / IG professional | Shared (WhatsApp Separate) | Selectable, "Partially supported" |
| Meta | Comments | `meta.login` | `pages_manage_engagement`, `pages_read_user_content`, `instagram_manage_comments` | App Review; webhooks | Page / IG professional | Shared | Selectable (**newly mapped**) |
| Meta | Insights | `meta.login` | `read_insights`, `instagram_manage_insights` | App Review | Page / IG professional | Shared | Selectable |
| Google | Publishing (YouTube) | `google.oauth` | `youtube.upload`, `youtube` | Consent-screen verification | YouTube channel | Shared | Selectable |
| Google | Ads | `google.oauth` | `adwords` | Ads API access level (Production, confirmed) | Ads account | Shared | Selectable |
| Google | Insights | `google.oauth` (GA4/Search Console: `google.analytics`) | `analytics.readonly` | Verification | Google account | Shared | Selectable |
| Google | Comments | — | needs `youtube.force-ssl`, not on the verified consent screen | Add to the consent screen first | — | Unsupported | "Not available" |
| X | Publishing + Inbox | `x.oauth2` (`posts.x`) | `tweet.write`, `dm.read`, `dm.write`, `media.write`, `offline.access` | — | X account | Shared | Selectable |
| X | Ads | `x.ads` (`ads.x`, OAuth 1.0a) | none (OAuth 1.0a) | Ads API Access Form | X Ads account | Separate + Approval | Selectable while flag `x.ads` is on |
| LinkedIn | Publishing + Page insights | `linkedin.pages` | `w_member_social`, `r_organization_admin`, `r_organization_social`, `w_organization_social` | Community Management API | Page admin | Shared | Selectable |
| LinkedIn | Ads | `linkedin.ads` (`ads.linkedin`) | `r_ads`, `rw_ads`, `r_ads_reporting` | Advertising API | Ad account | Separate | Selectable |
| LinkedIn | Inbox | — | no page-messaging API | — | — | Unsupported | "Not available" |
| TikTok | Publishing + Insights | Login Kit `tiktok.login_kit` | `video.upload`, `video.publish`, `user.info.stats`, `video.list` … | Content Posting audit (**Unverified**: page not readable) | TikTok account | Shared | Selectable |
| TikTok | Ads | TikTok for Business `tiktok.business` | set on the app | Business API app | Advertiser | Separate | Selectable |
| TikTok | Comments | `tiktok.comments` | `comment.list`, `comment.list.manage` | Accounts API | TikTok account | Separate | Selectable |
| TikTok | Inbox | — | — | Business Messaging (**Unverified**) | — | Approval | "Additional approval required" (flag `tiktok.business_messaging`) |
| Snapchat | Ads | `snapchat.marketing` | `snapchat-marketing-api` | Business Manager app | Ad account | Shared | Selectable |
| Snapchat | Publishing | — | `snapchat-profile-api` | Public Profile allowlist | — | Approval | "Additional approval required" (flag `snapchat.public_profile`) |
| Threads | Publishing | `threads.login` | `threads_basic`, `threads_content_publish` (+ `threads_read_replies`, `threads_manage_replies`) | Meta App Review | Threads profile | Shared | Selectable |
| Threads | Comments / Insights | — | the token response reports no granted scopes, so a grant can't be confirmed; `threads_manage_insights` isn't requested | — | — | **Unverified** | Not offered |
| Pinterest | Publishing + Insights | `pinterest.login` | `pins:write`, `boards:*`, `user_accounts:read` | Trial vs Standard access (**Unverified**) | Pinterest account | Shared | Selectable |
| ChatGPT Ads | Ads | API key (no OAuth) | — | — | — | — | Existing key form; not in the picker |

Sources: the citations in [connection-audit.md §3](connection-audit.md) and design §6b; [Threads access tokens & permissions](https://developers.facebook.com/docs/threads/get-started/get-access-tokens-and-permissions). The Pinterest scopes page (developers.pinterest.com/docs/getting-started/scopes/) returned 404 to an automated fetch on 2026-11-01, so its rows rest on the working implementation.

**Incremental authorization.** Google: `include_granted_scopes=true`, and the token response lists the granted scopes. Meta: earlier grants persist across logins, users can decline individual permissions, and `/me/permissions` reports the real grants. With `connections.meta.login_config_id` set, the FLfB configuration fixes the permission set. X OAuth 1.0a, TikTok and LinkedIn: each consent is all-or-nothing per app.

## 3. Architecture

| Component | Implementation |
|---|---|
| Platform registry | `config('connections.drivers')` (existing) |
| Capability registry | `config('connections.catalog')`: capability → `step`, `approval` flag, `partial`; texts in `lang/*/connections.php` → `catalog` |
| Capability status | `CapabilityCatalog::forUser()`: `connected` / `available` / `not_granted` / `reconnect` / `approval_required` / `unavailable`, all from `SocialConnection::capabilities` |
| Authorization planner | `AuthorizationPlanner::plan()`: groups the picked capabilities by consent, orders them like the driver's steps, and drops what's already connected or blocked |
| Provider adapters | the existing drivers, unchanged |
| Authorization workflow | `AuthorizationFlow` (session; user-bound; 30-minute TTL) |
| HTTP | `ConnectionAuthorizationController`: `POST connections/{platform}/plan` (JSON preview), `POST connections/{platform}/authorize`, `POST connections/flow/{continue,skip,cancel}` |

**Planning works per consent, not per scope.** Each driver step still requests its provider-verified scope set: the Google consent screen ("exact scope match", see `SocialAuthService::redirectGoogle`) or Meta's App Review set / Login configuration. Picking only "Ads" on Meta therefore still opens the full Meta consent. The customer can untick permissions there, and only granted ones turn on. Narrowing scopes within a consent is a follow-up decision (§6).

**Step completion.** A step is `done` when a usable connection for that step was recorded (`updated_at` ≥ the step's start, `last_error` null), or when every capability it was planned for is already connected. A step that returns without a recorded consent is `failed` and offered for retry. Capability results always come from granted scopes, never from the redirect.

**Security.** The platform and capabilities are validated against the catalog server-side. Flow state lives in the server session, bound to the user ID, and expires. Each provider callback's own state/CSRF check is unchanged. No token reaches Vue (the catalog rows carry none; covered by a test).

## 4. Customer journey

1. **Hub → platform panel:** "Choose what to connect" lists each catalog capability with a description, the account it needs, known limits, and a status badge. Connected, approval-pending and unavailable capabilities are locked.
2. **Selection:** a live plan preview ("One Meta sign-in covers your selection" / "2 sign-ins: 1. … 2. …"), then **Connect N features**.
3. **Guided run:** a banner with the step list, the current step's provider and what it turns on, and Continue / Skip / Cancel. After each callback the customer lands back on the Hub; a failed step offers "Try again".
4. **Summary:** "Your Meta account is connected" / "partly connected", with each capability's real status and actions: Complete missing permissions, Add more features, Done.

## 5. Database

No migration. `social_connections` already holds per-consent tokens, granted scopes, capabilities, status and expiry; multiple accounts per platform; unique per (user, platform, step, provider account). The flow is transient session state. Audit trail: flow start and step start go to the application log (`Connection authorization …`); a persistent audit table wasn't added.

## 6. Open decisions

1. **Scope narrowing within a consent.** Google officially supports incremental scopes, but the code pins the verified set at the review team's request. Meta classic `scope` could be narrowed, but not while a `config_id` is set. Needs a product decision before changing either working flow.
2. **YouTube comment replies** need `youtube.force-ssl` added to the verified consent screen and to `redirectGoogle` together.
3. **Threads comments/insights**: confirm a way to read granted Threads permissions before offering them.
4. **Persistent authorization audit table**, if the application log isn't enough.
