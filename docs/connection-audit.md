# Connection Audit — Phases 1–2

Audit date: 2026-10-07 · Read-only audit; this file is the only change.

## 1. Connect flows today

All paths are relative to the localized `admin.` route group. "App" means the `adminSetting()` key prefix for client ID and secret. Every flow writes tokens to **`social_accounts`**; `message_channels` only links to them.

| # | Platform | Route → handler | App | Scopes requested | Rows written |
|---|---|---|---|---|---|
| 1 | Facebook Pages + IG + Messenger + Ads | `social-accounts/facebook/*` → `SocialAccountController` → `SocialAuthService::redirectFacebook` | `posts.facebook` | pages_show_list, pages_manage_posts, pages_read_engagement, pages_manage_metadata, pages_read_user_content, pages_manage_engagement, pages_messaging, read_insights, business_management, instagram_basic, instagram_content_publish, instagram_manage_comments, instagram_manage_insights, ads_management, ads_read | page, IG and ad_account rows + `message_channels` |
| 2 | Facebook Ads | `ads/facebook/*` → `FacebookAdService` | **`ads.facebook`** | ads_management, ads_read, pages_show_list, pages_read_engagement, business_management, instagram_basic | ad_account rows |
| 3 | Instagram Ads | `ads/instagram/*` → `InstagramAdService` | `ads.facebook` | same as #2 | ad_account rows |
| 4 | Instagram DMs | `messaging/auth/instagram/*` → `InstagramMessagingTrait` | `posts.facebook` | instagram_basic, instagram_manage_messages, pages_show_list, pages_read_engagement, pages_messaging, pages_manage_metadata | IG row + channel |
| 5 | Instagram (Instagram Login) | `post-accounts/instagram/*` → `PostAccountController` | **`posts.instagram`** | instagram_business_basic, _manage_messages, _manage_comments, _content_publish, _manage_insights | IG row |
| 6 | WhatsApp (Embedded Signup) | `post-accounts/whatsapp/embedded` (FB JS SDK + `config_id`) | **`messaging.meta.app_id`** | set by `config_id` | WA row |
| 7 | WhatsApp (manual entry) | `post-accounts/whatsapp` **and** `messaging/channels/whatsapp` | none (pasted token) | none | WA row (+ channel) |
| 8 | Google: YouTube + Ads + Analytics | `social-accounts/google/*` → `SocialAuthService::redirectGoogle` | `posts.google` | youtube.upload, youtube, adwords, analytics.readonly | channel + Ads customer rows |
| 9 | Google Ads / YouTube Ads | `ads/google/*`, `ads/youtube/*` → `GoogleAdsApiTrait` | **`ads.google`** | adwords | Ads customer rows |
| 10 | LinkedIn org + Ads | `social-accounts/linkedin/*` | `posts.linkedin` | w_member_social, r_organization_admin, r_organization_social, w_organization_social (**ads scopes commented out**) | org rows; the ad-row code can't run because r_ads is never requested |
| 11 | LinkedIn Ads | `ads/linkedin/*` → `LinkedinAdService` | **`ads.linkedin`** | r_ads, rw_ads, r_ads_reporting, w_organization_social, r_organization_social | ad_account rows |
| 12 | TikTok posting (Login Kit) | `social-accounts/tiktok/*` (+ alias `post-accounts/tiktok/*`) | `posts.tiktok` | user.info.basic, video.upload, video.publish, user.info.profile, user.info.stats | profile row |
| 13 | TikTok Ads | `ads/tiktok/*` → `business-api.tiktok.com/portal/auth` | `ads.tiktok` | set on the app | ad rows |
| 14 | TikTok DMs | `messaging/auth/tiktok/*` → `www.tiktok.com/v2/auth/authorize` | `ads.tiktok` used as `client_key` | user.info.basic | `business_messaging` row |
| 15 | Snapchat Ads | `ads/snapchat/*` → `SnapchatAdService` | `ads.snapchat` | snapchat-marketing-api | ad rows |
| 16 | X posting | `post-accounts/x/*` | `posts.x` | tweet.read, tweet.write, users.read, media.write, dm.read, dm.write, offline.access | x row |
| 17 | X DMs | `messaging/auth/x/*` → `XMessagingService` | `posts.x` | **same constant as #16** | x row + channel |
| 18 | X Ads | `ads/x/*` → `XAdService` (OAuth 1.0a) | **`ads.x`** | OAuth 1.0a | x row (token in metadata) |
| 19 | Threads | `post-accounts/threads/*` | `posts.threads` | threads_basic, threads_content_publish | threads row |
| 20 | Pinterest | `post-accounts/pinterest/*` | `posts.pinterest` | boards:read/write, pins:read/write, user_accounts:read | pinterest row |
| — | Dead route | `posts/{platform}/redirect` → `PostController::redirect` | — | **the method doesn't exist** | — |

Out of scope (not social platforms): the Slack, Discord, Google Chat, Zalo, Teams, Telegram, LINE and Matrix channels on `chats/channels`.

**Token storage** is `social_accounts` (access_token, refresh_token, token_type, `scopes`, expires_at, is_token_valid, has_{posting,messaging,ads}_permission). The legacy `post_accounts` and `ad_accounts` tables were dropped (migration `2026_08_26_100005`).

**Refresh** is on demand inside each service, and nothing on the schedule refreshes tokens or checks expiry:
- Google: `YoutubePostService` (×2), `GoogleAdsApiTrait`
- X: `XPostService`, `XMessagingService::ensureFreshToken`
- Meta `fb_exchange_token`: `MetaPostService`, `InstagramPostService`, `InstagramMessagingTrait`
- Others: `LinkedinAdService`, `SnapchatAdService`, `TiktokPostService`, `PinterestPostService`

### Connect UI the Hub will replace

| Module | Location | What it shows |
|---|---|---|
| Ads | `ads/AdsDashboard.vue` | "Connect a platform" empty state, per-platform `connect_url` / `reconnect_url`, "Connect new" |
| Ads | `ads/PlatformCampaignsDashboard.vue` | "Connect an account" prompt |
| Ads | `admin/ads/partials/account-switcher.blade.php`, `<x-social-connect-modal>` on the Ads dashboard | `ads.redirect` links |
| Publishing | `admin/posts/create.blade.php` | `social-accounts` ×4; `post-accounts` IG / Threads / Pinterest / X; WhatsApp manual + Embedded Signup |
| Publishing | `admin/posts/dashboard.blade.php` | "Add Account" modal (same links); WhatsApp links to `posts.create` |
| Publishing | `posts/composer/AccountSelector.vue` | "connect one" → `manageAccountsUrl` |
| Inbox | `admin/chats/channels.blade.php` | `social-accounts/facebook`; `messaging/auth` IG / X / TikTok; WhatsApp modal |
| Inbox | `admin/chats/dashboard.blade.php` | FB / IG / X / TikTok connect links |
| Inbox | `components/whatsapp-connect-modal.blade.php` | manual WhatsApp entry |

## 2. Duplicates and risks

1. **Meta has 4 app registrations:** `posts.facebook`, `ads.facebook`, `messaging.meta.app_id` and `posts.instagram`. A Page / IG account can be connected through 5 flows (#1–#5). Ad accounts are written by both #1 and #2.
2. **X:** #16 and #17 are the same app with the same scopes, but two buttons (Publishing and Inbox).
3. **Google:** #8 already requests `adwords` and creates Ads rows; #9 repeats this with a second app.
4. **LinkedIn:** two apps. The combined flow's ads scopes are commented out, so its ads half never runs.
5. **TikTok:** 3 flows. #14 reuses the **Ads** app ID as a Login Kit `client_key`.
6. **WhatsApp:** 3 entry points (2 manual in different modules, 1 Embedded Signup).
7. **Tokens:**
   - The same Meta user token is copied onto every ad_account row.
   - Page rows store the page token in `refresh_token`.
   - `scopes` is **never written**, so granted scopes are unknown.
8. **Encryption:** the `encrypted` casts on `access_token` / `refresh_token` are commented out, yet migration `..._100007` encrypted existing tokens. Locally: 14 plaintext, 1 encrypted, and that one can't be used as-is.
9. **Refresh:** the logic is duplicated per service, with no central expiry or revocation status.
10. **Dead route:** `posts/{platform}/redirect`.

## 3. Platform capability matrix (verified 2026-10-07)

| Platform | Verdict | Finding | Approval needed | Doc |
|---|---|---|---|---|
| Meta: FB + IG + Messenger + Ads | **Corrected** | One Facebook Login for Business configuration (`config_id`) bundles the assets and permissions in one consent. **WhatsApp must use the "WhatsApp Embedded Signup" login variation**; whether it can share a configuration with Pages/IG/Ads permissions is undocumented (**Unclear**). | Advanced Access via App Review, for each permission | [FLfB](https://developers.facebook.com/docs/facebook-login/facebook-login-for-business) · [Embedded Signup](https://developers.facebook.com/docs/whatsapp/embedded-signup) · [ES implementation](https://developers.facebook.com/docs/whatsapp/embedded-signup/implementation) |
| Google (YouTube, Ads, Business Profile, Drive) | **Corrected** | One OAuth client with incremental authorization (`include_granted_scopes`) confirmed. **Developer tokens were sunset 2026-09-09**; access now depends on the Cloud project's access level. Business Profile API needs a separate access application. `drive.file` is non-sensitive (confirmed). | Ads Basic/Standard: brand verification (Standard also manual review). Business Profile: application form. | [OAuth web server](https://developers.google.com/identity/protocols/oauth2/web-server) · [Ads access](https://developers.google.com/google-ads/api/docs/api-policy/developer-token) · [GBP prereqs](https://developers.google.com/my-business/content/prereqs) · [Drive scopes](https://developers.google.com/workspace/drive/api/guides/api-specific-auth) |
| LinkedIn | **Corrected** | Not two apps for good. Community Management (CM) Development tier must be **requested** on a new, product-free app; after approval, CM Standard can be added to the **existing Advertising API app** (that verification app can then be discarded). The Ads API itself grants `w_organization_social`. No page-messaging API. | CM: vetted, Dev → Standard with screencast. Ads: Dev → Standard via support ticket. | [CM overview](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview) · [Increasing access](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access) |
| TikTok | **Confirmed** | Login Kit (`client_key`, `www.tiktok.com/v2/auth/authorize/`) and API for Business (`app_id`, `business-api.tiktok.com`) are separate apps and authorizations. Business Messaging approval, regions, and whether Business-API account authorization can also do organic posting: **Unclear** (the pages render with JS and couldn't be read). | Content Posting audit (not fetched); Business Messaging access: Unclear | [Login Kit Web](https://developers.tiktok.com/doc/login-kit-web) · [BM hub](https://business-api.tiktok.com/portal/bm-api/education-hub) · [Creator auth](https://business-api.tiktok.com/portal/docs/get-authorization-from-creators/v1.3) |
| Snapchat | **Corrected** | Not Login Kit. Ads uses a Business Manager OAuth app with `snapchat-marketing-api`. Organic posting uses the **Public Profile API** (`snapchat-profile-api`), also a Business Manager app (not the Developer Portal). Its messaging is brand↔creator only, on a separate allowlist. One consent for both scopes: **Unclear**. | Public Profile API is allowlist-only; messaging and Profile Asset Management have their own allowlists | [Ads auth](https://developers.snap.com/api/marketing-api/Ads-API/authentication) · [Public Profile: Get Started](https://developers.snap.com/marketing-api/Public-Profile-API/GetStarted) · [Messaging](https://developers.snap.com/marketing-api/Public-Profile-API/Messaging) |
| X | **Confirmed** | OAuth 2.0 PKCE scopes tweet.write, dm.read, dm.write, media.write and offline.access cover posting + DMs. The Ads API needs separate approval **and OAuth 1.0a** tokens. Posting accepts OAuth 1.0a too. *Update 2026-10-07:* the DM API reference lists `UserToken` (OAuth 1.0a), so **one OAuth 1.0a consent covers posting + DMs + Ads** (decision in the design doc §6b). | Ads API Access Form, per app | [OAuth 2.0](https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code) · [Ads step-by-step](https://docs.x.com/x-ads-api/getting-started/step-by-step-guide) · [Create DM](https://docs.x.com/x-api/direct-messages/create-dm-message-by-participant-id) · [Posts](https://docs.x.com/x-api/posts/manage-tweets/introduction) |
| Threads, Pinterest | not in hypothesis | Own OAuth each; posting only. Not re-verified. | — | — |

### Minimum Connect buttons

| Platform | Today (social flows) | Hub minimum |
|---|---|---|
| Meta: FB/IG/Messenger/Ads | 5 | **1** (one FLfB config; also add `instagram_manage_messages`) |
| WhatsApp | 3 | **1** (Embedded Signup); 0 extra if it can join the Meta config (Unclear) |
| Google | 2 | **1** (incremental scopes) |
| LinkedIn | 2 | **1** (after CM Standard is added to the Ads app) |
| TikTok | 3 | **2** (Login Kit + Business; DMs folded into Business auth if confirmed) |
| Snapchat | 1 | **1** (2 if the Public Profile scope can't be combined) |
| X | 3 | **1** (one OAuth 1.0a flow: posting + DMs + Ads) |
| Threads / Pinterest | 1 / 1 | 1 / 1 |
| **Total** | **20** | **10** (range 9–11) |
