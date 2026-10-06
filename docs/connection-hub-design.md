# Connection Hub — Phase 3 Design

Status: **draft — production answers recorded (§12), awaiting go-ahead for Phase 4** · 2026-10-07 · No code yet. Builds on [connection-audit.md](connection-audit.md).

> Facts below come from this **local** environment (settings and `social_accounts`). Re-run the read-only checks in §0 on production before Phase 4; anything that differs there changes a decision marked ⚑.

## 0. Facts behind the decisions (read-only checks, 2026-10-07)

| Check | Result |
|---|---|
| `access_token` state (15 rows) | 14 plaintext · 0 encrypted-OK · **1 encrypted but undecryptable** (#69 facebook/ad_account; wrong `APP_KEY`) |
| `refresh_token` state | 10 plaintext · 5 null · 2 rows store the access token here (#111 FB page, #112 IG) |
| Identical access tokens on several rows | 3 groups of 2 (Meta user token copied onto ad_account rows) |
| Expired but `is_token_valid = true` | **7 / 15**: #62, #78 TikTok · #69 FB ad · #72 Snapchat · #79, #83 X · #84 TikTok DM |
| `scopes` column | never written (0 / 15) |
| Meta apps ⚑ | `posts.facebook.client_id` **== `ads.facebook.client_id`** (same app). `messaging.meta.app_id`/`app_secret`/`whatsapp_config_id` **unset**. `posts.instagram` set. |
| Meta webhook HMAC + `appsecret_proof` | both use `posts.facebook.client_secret` (`MetaMessagingTrait`, `InstagramMessagingTrait`) |
| Google clients ⚑ | `posts.google` → project **868193692422** · `ads.google` → project **805156061921** (different projects). `ads.google.developer_token` unset. |
| TikTok flow #14 test | The round-trip works (row #84, 3 Oct, with a channel). Granted scopes: `biz.brand.insights, biz.creator.info, comment.list, tto.campaign.link, video.insights, user.info.basic, biz.creator.insights, video.list, biz.ads.recommend`. **No messaging scope**, so `has_messaging_permission = false` and DMs can't work. Token expired 4 Oct. |

## 1. Step 0: Security & data (ships before the Hub)

### 1a. Token encryption
- **Command** `connections:encrypt-tokens {--dry-run}` reports per column: `plain` / `enc_ok` / `enc_broken` / `null`, then:
  - plaintext → `Crypt::encryptString`
  - undecryptable → try `APP_PREVIOUS_KEYS` first; if that fails, null the token, set `is_token_valid = false` and status `needs_reauth`, and log the id. **`APP_PREVIOUS_KEYS` is not set**, so #69 can't be recovered: the Hub shows it as Reconnect (one click). No action needed unless an old `APP_KEY` turns up in a backup.
  - idempotent; chunked; with `--dry-run` it writes nothing.
- **Cast rollout** (switching to a strict `encrypted` cast while any plaintext remains would throw `DecryptException` on read):
  1. Release A ships a `TolerantEncrypted` cast: reads encrypted values, otherwise passes plaintext through; always writes encrypted. Run the command.
  2. Release B, once the dry-run reports 0 plaintext: switch to Laravel's built-in `encrypted` cast.
- The same applies to the new token columns in §2. `APP_KEY` rotation is documented via `APP_PREVIOUS_KEYS`.

### 1b. Persist granted scopes
Every callback stores what was actually **granted**, not what was requested, as `granted_scopes` (array) plus `scopes_checked_at`:

| Platform | Source of granted scopes |
|---|---|
| Meta | `GET /me/permissions` (status = granted) after the code exchange |
| Google | `scope` in the token response (space-separated) |
| LinkedIn | `scope` in the token response |
| TikTok (Login Kit / Business) | `scope` in the token response |
| X (OAuth 1.0a, §6b) | OAuth 1.0a has no scopes. Store `["oauth1"]`; capabilities come from the X app's permission level and Ads API approval, confirmed by the validation pass (§7) |
| Snapchat, Threads, Pinterest | `scope` in the token response |

Capabilities (ads / posting / messaging / insights) are **derived** from the granted scopes by each driver's capability map (§3), replacing hand-set `has_*_permission` flags. The flags stay as computed columns during the transition.

### 1c. Page and asset tokens
- Page / IG tokens move to the asset record (`connected_assets.asset_token`, encrypted).
- `refresh_token` holds a real refresh token or null; this fixes #111 and #112.
- Ad-account rows stop copying the user token. They read it through their parent connection.

## 2. Data model (evolve, don't replace)

`social_accounts` already models **assets** (pages, IG accounts, ad accounts, channels), and every module reads it. Keep it as the asset table and add a parent connection, so modules keep working throughout.

**`social_connections`** (new): one row per *user × platform × provider account × auth step*
- `id, user_id, workspace_id, platform, step` (e.g. `meta.login`, `meta.whatsapp`, `tiktok.login_kit`, `tiktok.business`, `x.oauth1`)
- `provider_account_id, provider_app` (adminSetting prefix used, e.g. `posts.facebook`)
- `access_token`, `refresh_token`, `token_secret` (OAuth 1.0a) — all encrypted
- `expires_at, refresh_expires_at, granted_scopes (json), capabilities (json)`
- `status` enum: `active | expiring | expired | needs_reauth | revoked | error`
- `last_checked_at, last_refreshed_at, last_error, revoked_at, timestamps`; unique on (user_id, platform, step, provider_account_id)

**`social_accounts`**, which becomes the "connected assets" (columns added):
- `social_connection_id` (FK, nullable during backfill)
- `asset_token` (encrypted, for page/IG tokens)
- `asset_kind` (normalises `account_type`)
- `enabled_capabilities` (json: the user's toggles in the Hub, a subset of the connection's capabilities)
- Legacy `access_token` / `refresh_token` are read-only fallbacks during migration (§9), then dropped.

`message_channels` is unchanged (it still points at the asset).

## 3. Service layer

- **`ConnectionService`** is the only entry point modules use:
  - `connect(platform, step, ?extraScopes)` → redirect
  - `handleCallback(platform, step, request)`
  - `tokenFor(SocialAccount $asset, string $capability)` → returns a fresh token, refreshing it if needed
  - `ensure(user, platform, capability)` → `Ok` or `UpgradeRequired(url)`
  - `disconnect(connection)`, `refresh(connection)`, `validate(connection)`
- **`ProviderDriver`** interface, one per platform: `steps(): Step[]`, `authorizeUrl(step, scopes, state)`, `exchange(step, request): TokenSet`, `grantedScopes(TokenSet)`, `capabilityMap(): scope→capability`, `discoverAssets(connection)`, `refresh()`, `validate()`, `revoke()`.
- Drivers: `Meta` (including WhatsApp and the Instagram Login steps), `Google`, `LinkedIn`, `TikTok`, `Snapchat`, `X`, `Threads`, `Pinterest`. They reuse the existing service HTTP code rather than rewriting the platform calls.
- **Incremental authorization:** `ensure()` compares the capability against `granted_scopes`. If it's missing, the feature page shows an inline **"Upgrade access"** prompt that calls `connect()` with only the extra scopes (Google `include_granted_scopes=true`; Meta re-runs the same config; others request the union).
- **Callbacks:** one handler. **Existing callback URLs keep working** as aliases of the new handler, because they're registered in each provider's developer dashboard.

## 4. Meta: one app

**Proposal: keep `posts.facebook` as the single Meta app.** Reasons:
1. It is the same app as `ads.facebook`, locally **and on production (confirmed)**, so ad accounts need no re-consent.
2. Webhook signature checks (`X-Hub-Signature-256`) and `appsecret_proof` already use its secret.
3. Messenger and Instagram webhooks are subscribed to it; moving them would mean re-subscribing every Page.
4. It holds the widest scope set and has the most code references (6 ID, 11 secret).
5. `messaging.meta.*` is unset, so retiring it costs nothing. Create the WhatsApp Embedded Signup configuration inside `posts.facebook` and read `config_id` from `connections.meta.whatsapp_config_id`.

**Instagram Login** stays a secondary option inside the Meta card ("Connect Instagram without a Facebook Page"), using `posts.instagram`. **Confirmed:** this Instagram app ID belongs to the same Meta app, so everything goes in one App Review.

**Final permission set (one App Review submission, all at Advanced Access): approved.**

| Group | Permissions | Used by |
|---|---|---|
| Login | `public_profile` (FLfB requires Advanced Access) | all |
| Business | `business_management` | asset discovery, ads |
| Pages / posting | `pages_show_list`, `pages_manage_posts`, `pages_read_engagement`, `pages_read_user_content`, `pages_manage_engagement`, `pages_manage_metadata`, `read_insights` | Publishing, Comments, Insights |
| Messenger | `pages_messaging` | Inbox |
| Instagram (via Page) | `instagram_basic`, `instagram_content_publish`, `instagram_manage_comments`, `instagram_manage_insights`, **`instagram_manage_messages`** (new) | Publishing, Comments, Inbox |
| Ads | `ads_management`, `ads_read` | Ads |
| WhatsApp (Embedded Signup config) | `whatsapp_business_management`, `whatsapp_business_messaging` | Inbox, Publishing |
| Instagram Login (secondary, same submission) | `instagram_business_basic`, `instagram_business_content_publish`, `instagram_business_manage_comments`, `instagram_business_manage_messages`, `instagram_business_manage_insights` | IG without a Page |

## 5. Google: which client survives

| | `posts.google` | `ads.google` |
|---|---|---|
| Cloud project | 868193692422 | 805156061921 |
| Scopes requested today | youtube.upload, youtube, adwords, analytics.readonly | adwords |
| **Google Ads API access level** | **Production (confirmed)** | **Production (confirmed)** |

**Decision: keep `posts.google` (project 868193692422).** Both projects have production Ads access, so access level no longer decides it. `posts.google`:
- already requests every scope the Hub needs (YouTube, `adwords`, Analytics) in one consent
- already powers the combined-consent flow (#8)

`ads.google` is then redundant.

**Retiring `ads.google` safely:**
- Google refresh tokens only work with the OAuth client that issued them. Ads accounts connected through `ads.google` keep refreshing **only while the `ads.google` credentials remain configured**.
- So: keep `ads.google.*` settings in place; the backfill (§9) tags those connections with `provider_app = ads.google`; `tokenFor()` refreshes them with their own client.
- The Hub shows them as "Reconnect to upgrade" (non-blocking). Once none remain, remove the `ads.google.*` settings.

Other notes:
- The flag `google.oauth_client` stays (default `posts`) as a rollback switch.
- Developer tokens are sunset per Google's docs, so the code stops requiring `ads.google.developer_token`.

## 6. TikTok

- **Flow #14 → remove.** Evidence in §0: it completes OAuth but never receives a messaging scope, so DMs can't work, and its granted `biz.*` scopes show it is really the TikTok **Business account** authorization of the Ads app.
- Final card:
  1. **Login Kit** (`posts.tiktok`): posting
  2. **TikTok for Business** (`ads.tiktok`): advertiser authorization (ads), plus Business-account authorization behind flags:
     - `tiktok.business_messaging`: when TikTok approves Business Messaging, DMs ride this step
     - `tiktok.business_account_posting`: Unclear
- The Inbox's TikTok "Connect" button is removed. The existing channel #84 is marked `needs_reauth`, with the explanation "Waiting for TikTok Business Messaging approval".

## 6b. X: one OAuth 1.0a flow (decided 2026-10-07)

**Decision:** one 3-legged OAuth 1.0a connection for posting + DMs + Ads. `posts.x` and `ads.x` are consolidated into a single X app. There's no refresh logic; a revocation check replaces it.

| Point | Evidence |
|---|---|
| Ads API requires OAuth 1.0a user context | [Ads step-by-step](https://docs.x.com/x-ads-api/getting-started/step-by-step-guide) |
| Posting accepts OAuth 1.0a ("3-legged OAuth") | [Manage Posts](https://docs.x.com/x-api/posts/manage-tweets/introduction) |
| DMs accept OAuth 1.0a: the API reference lists `UserToken` alongside `OAuth2UserToken` | [Create DM](https://docs.x.com/x-api/direct-messages/create-dm-message-by-participant-id) · [Get DM events](https://docs.x.com/x-api/direct-messages/get-dm-events) |
| OAuth 1.0a user tokens "do not expire but can be revoked by the user at any time" | [Obtaining user access tokens](https://docs.x.com/fundamentals/authentication/oauth-1-0a/obtaining-user-access-tokens) |

This resolves the audit's "Unclear" X row, so the X minimum becomes **1** Connect button.

**Consequences:**
- **App:** the surviving X app needs (a) Ads API access approved and (b) its permission level set to *Read, write and Direct Messages*. A permission change only applies to tokens issued **after** it, so set the level before users connect.
- **Settings:** one set, `connections.x.consumer_key` / `consumer_secret`. The Hub migration reads the existing `ads.x.*` values, since `XAdService` already signs OAuth 1.0a. `posts.x.*` (the OAuth 2.0 client) is retired after cut-over.
- **Tokens:** `social_connections.access_token` + `token_secret` (both encrypted). `expires_at` = null, and `refresh_token` is unused.
- **Modules:** `XPostService` and `XMessagingService` switch from Bearer (OAuth 2.0) to OAuth 1.0a signing, reusing `XAdService`'s signer. Their `refresh_token` / `ensureFreshToken` code is removed.
- **Existing users:** today's OAuth 2.0 posting/DM rows (#79, #83 locally) can't be converted to OAuth 1.0a, so they need **one reconnect**. The Hub shows "Reconnect X (one-time upgrade)". Old rows keep working with their OAuth 2.0 tokens until then (dual-read, §9).
- **Status:** X is skipped by the expiry pass; the daily validation pass calls `GET /2/users/me` signed with the token. 401 → `revoked`. A 403 on the DM/Ads probe → `needs_reauth`, with the hint "app permission level / Ads access".
- **To verify during implementation:** media upload (`media.write`) under OAuth 1.0a. If it isn't supported, posting media falls back to the v1.1 media upload, which is OAuth 1.0a-native.

## 7. Scheduled status job

`connections:check-status` in `bootstrap/app.php` → `withSchedule`:

| Cadence | Work |
|---|---|
| hourly | Expiry pass: `expires_at < now()+7d` → `expiring`; past expiry with a refresh token → `driver->refresh()`, otherwise `expired`; a failed refresh → `needs_reauth` |
| daily (staggered, rate-limited) | Validation pass: `driver->validate()` checks the provider for revocation and scope changes (Meta `/me/permissions`, Google tokeninfo, LinkedIn introspection, X `users/me` signed with OAuth 1.0a (X has no expiry, §6b), TikTok user info). Revoked → `revoked`. Updates `granted_scopes`. |
| on transition | Write `last_error`; notify via the existing `NotificationCenter` on → `expiring` / `needs_reauth` / `revoked` (once per transition) |

`withoutOverlapping()`, chunked per connection, never throws. The Hub reads `status` only; it never calls providers itself. Locally this would flip 7 rows at once, so plan the first run with notifications muted.

## 8. Feature flags (every "Unclear" from the audit)

`config/connections.php`, overridable via `adminSetting('connections.flags.*')`. Drivers build `steps()` from the flags; the Hub renders steps, so flipping a flag needs no redesign.

| Flag | Default | Effect when on |
|---|---|---|
| `meta.whatsapp_in_main_config` | off | WhatsApp permissions join the main FLfB config: one Meta step instead of two |
| `meta.instagram_login` | on | Shows "Instagram without a Facebook Page" in the Meta card |
| `google.oauth_client` | `posts` | Which client/project is used (`posts` \| `ads`) |
| `google.business_profile` | off | Adds `business.manage` (after GBP API approval) |
| `linkedin.single_app` | off | One app (Ads + CM Standard). While off: two steps, `posts.linkedin` + `ads.linkedin` |
| `tiktok.business_messaging` | off | DMs via the Business step |
| `tiktok.business_account_posting` | off | Organic posting via Business instead of Login Kit |
| `snapchat.public_profile` | off | Adds the Public Profile step (allowlist) |
| `snapchat.combined_scopes` | off | Requests marketing + profile scopes in one consent |
| `x.ads` | on | Exposes the Ads capability of the single X connection (off if the X app loses Ads API access) |

## 9. Migration (no forced reconnects)

1. Create `social_connections`; add the new columns to `social_accounts`.
2. **Backfill:** group existing rows by (user, platform, source app, user-level token) into connections:
   - copy the user token / refresh token / expiry to the connection
   - move page tokens to `asset_token`
   - link the assets
   - derive `granted_scopes` from the validation pass (§7) rather than guessing
3. **Dual-read:** `ConnectionService::tokenFor()` prefers connection / asset tokens and falls back to the legacy columns. Modules migrate to `tokenFor()` one platform at a time (Meta first).
4. Once no code reads the legacy token columns, drop them in a later release.
5. Rows with unrecoverable tokens (#69) or expired, non-refreshable tokens appear in the Hub as **Reconnect**. That's the only reconnect users see.

## 10. Hub UI

- **Page `connections`:** one Vue card per platform, showing:
  - status badge (from `status`)
  - connected assets (pages, IG, WABA numbers, ad accounts, channels)
  - capability toggles (Ads / Posting / Messaging / Insights)
  - the card's steps (from flags), Reconnect / Disconnect, and expiry warnings
- **"Connect all recommended" wizard:** runs Meta → Google → LinkedIn → X → TikTok steps in sequence.
- **Feature pages:** the connect UI listed in the audit is replaced by a compact "Connected via Hub" chip plus a deep link (`connections#meta`), and an inline "Upgrade access" prompt when `ensure()` fails.

## 11. Phase 4 commit order

The Meta card is ordered early so App Review screencasts can be recorded from it.

| # | Commit | Tests |
|---|---|---|
| 0 | Docs (this file + the audit) | — |
| 1 | **Step 0a:** `TolerantEncrypted` cast on the token columns + `connections:encrypt-tokens {--dry-run}` | dry-run writes nothing; counts; plaintext encrypted; undecryptable → invalid + reason; idempotent; cast reads both |
| 2 | **Step 0b:** persist granted scopes on every OAuth callback | per-platform parser; representative callbacks write `scopes` |
| 3 | **Step 0c:** page tokens → `asset_token`; `refresh_token` no longer misused | migration moves data; Meta callback writes `asset_token` |
| — | *Stop: Step 0 review* | |
| 4 | `social_connections` + columns on `social_accounts`, models, backfill (dry-run first) | backfill grouping, idempotency |
| 5 | `ConnectionService`, `ProviderDriver`, flags, **Meta driver** (FLfB `config_id`, single callback, existing callback URL aliased) | connect, callback, scope upgrade |
| 6 | **Hub page with the Meta card:** connect, **asset picker** (Pages / IG / ad accounts / WABA), capability toggles, status. ← *App Review screencasts can be recorded here* | feature tests on the card endpoints |
| 7 | Status job (§7) + notifications | expiry, refresh, revoke transitions |
| 8 | Meta modules → `tokenFor()`; remove Meta connect buttons from Ads / Publishing / Inbox | module token reads |
| 9 | Google driver + card; retire `ads.google` gradually (§5) | |
| 10 | X driver (§6b) + card; X services → OAuth 1.0a | signing, revocation |
| 11 | LinkedIn, TikTok (remove #14), Snapchat, Threads, Pinterest | per driver |
| 12 | Wizard + "Upgrade access" prompts; remove remaining connect UI | |

## 12. Production answers (2026-10-07)

| Question | Answer | Effect |
|---|---|---|
| `posts.facebook` == `ads.facebook` on production? | **Yes** | One Meta app; no ad-account re-consent (§4) |
| `posts.instagram` under the same Meta app? | **Yes** | Instagram Login is in the same App Review (§4) |
| Google Ads access level, both projects | **Production, both** | Keep `posts.google`; retire `ads.google` gradually (§5) |
| `APP_PREVIOUS_KEYS` | **Not set** | #69 → Reconnect in the Hub (§1a). **Applied locally 2026-10-07:** per [Laravel encryption docs](https://laravel.com/docs/12.x/encryption#gracefully-rotating-encryption-keys) the value is unrecoverable without the previous key, and per [Meta long-lived tokens](https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived) the token (expired 2026-09-09) can't be refreshed, so the user must log in again. Token cleared, `is_token_valid = false`, reason in `metadata.reauth`. Do the same on production once the Step 0 command's dry-run lists it. |
| Meta permission list | **Approved** | Submit App Review with the §4 list |

No open questions. Phase 4 starts with Step 0 (§1) on your go-ahead.
