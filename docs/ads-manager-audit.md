# Ads Manager audit: 2026-11-26

Branch `email-notification`. Uncommitted. Not deployed.

**Scope:** the code, the database usage, the tests, and the official API documentation for Meta and LinkedIn.

**Not done:** no request reached a real ad account. Every API call was faked in tests, as the brief requires for campaigns and budgets. I also did not open the production site, because I don't sign in to production.

## Summary

| # | Finding | Severity | Status |
|---|---|---|---|
| 1 | **Cross-tenant access:** any seller could open another seller's campaign edit page by changing the id. On Google, YouTube, LinkedIn, X and Snapchat (update), they could also send update, pause/resume or delete for it. Those services load campaigns by id alone (`AdCampaign::findOrFail($id)`). | Critical | Fixed and tested |
| 2 | **Expired Meta API version:** insights, pause/resume, duplicate and account lookups called Marketing API **v22.0**, which expired on 2026-02-19 (`#2635`). The admin setting `ads.facebook.base_url` also held v22.0. | Critical | Fixed: everything pinned to v25.0 |
| 3 | **Meta missing from the dashboard:** Meta (Facebook/Instagram) spend wasn't on the Ads Dashboard, although its campaigns page had real insights. | High | Fixed |
| 4 | **No LinkedIn reporting:** LinkedIn had none. The dashboard, page, CSV export and emailed reports said "not reported", even though OAuth already requests `r_ads_reporting`. | High | Implemented (adAnalytics) |
| 5 | **Broken test fixtures:** 68 of 175 ads tests failed. There were three causes, all in the fixtures, not the product: the new email-verification gate, missing `users` tables, and a pinned date. | Medium | Fixed |

## A. Platforms

The status in each cell is from code review and faked-API tests. **None of it is verified against a live account.**

| Platform | Connections | Campaigns | Analytics | Reports | Overall |
|---|---|---|---|---|---|
| **Meta (Facebook + Instagram)** | OAuth, multiple ad accounts and switching, read-only role and expiry blockers | Builder create/edit, pause/resume, duplicate (Meta `/copies`, paused), delete, Sync Now | Campaign insights, daily series, breakdowns, previous period, AI insights. **Now on the dashboard**, with Instagram counted inside Meta and not twice. | CSV and scheduled email | Code-complete. Needs a live test. |
| **Google Ads** | OAuth, customer selection, refresh token | Builder: Search, Display, Performance Max; edit, pause, delete | GAQL: spend, clicks, impressions, conversions, value, daily, previous period | CSV and scheduled email | Code-complete. Needs a live test. No campaign import. |
| **YouTube** (Google Ads Demand Gen) | Google Ads account | Builder (Demand Gen) | Sum of its own campaigns, inside the Google totals | CSV and scheduled email | As Google |
| **TikTok** | OAuth, advertiser selection | Builder, pause/resume, delete, Sync Now | Report API: campaign, daily, audience breakdowns | CSV and scheduled email | Code-complete. Needs a live test. |
| **Snapchat** | OAuth, refresh shared across sibling accounts | Builder, pause/resume, delete, Sync Now | Measurement API | CSV and scheduled email | Code-complete. Needs a live test. |
| **X** | OAuth 1.0a | Builder, pause/resume, delete, Sync Now | Analytics API: 7-day chunks for long ranges | CSV and scheduled email | Code-complete. Needs a live test. |
| **LinkedIn** | OAuth, refresh when issued | Campaign group, campaign and creative create; edit, pause, archive | **New:** adAnalytics by campaign group, previous period, daily series, results by objective | CSV and scheduled email (now with spend) | Code-complete. Needs a live test. No campaign import. |
| **ChatGPT Ads** | API key | Sync, pause/resume, archive. Editing is done in ads.openai.com. | Period totals per campaign (spend, clicks, impressions). The API gives no daily series or conversions. | CSV and scheduled email | Code-complete within its API |

## B. Tests

| Feature | Test | Expected | Result |
|---|---|---|---|
| Tenant isolation | Seller A uses edit, update, delete, status and duplicate on seller B's campaigns, on 9 platforms | 404 (update may answer 422 from form validation), campaign unchanged, no API call | **Failed before, passes after the fix** (`CampaignOwnershipTest`) |
| Platform/id confusion | A Google id sent on the LinkedIn/X routes; a non-numeric id | 404 | Pass |
| Meta on the dashboard | Account with a Facebook campaign, an Instagram campaign, and one created in Meta | Meta totals include all three; Instagram is `in_meta`; per-campaign rows for local campaigns only | Pass (new) |
| Meta API version | Every ads Graph call | v25.0 only | Pass (all Meta suites on v25.0) |
| LinkedIn request format | adAnalytics URL and headers | Rest.li 2.0: literal `(…)`, URN encoded, `LinkedIn-Version`, `X-Restli-Protocol-Version: 2.0.0`, 20 fields or fewer, UTC dates ending yesterday | Pass (new) |
| LinkedIn numbers | Two campaign groups, one created outside Socialeaz | Account totals, previous period, 7-day series with zeros, results by objective (leads), CPR and CTR correct | Pass (new) |
| LinkedIn errors | 403 (missing scope), expired without refresh, 429 | "Reconnect / allow r_ads_reporting", no call when expired, "try again", no invented numbers | Pass (new) |
| LinkedIn export and email report | CSV and the scheduler | Real spend and formula-safe names (`'=HYPERLINK`) | Pass (updated) |
| Metric formulas | CTR, CPC, CPM, CPA, ROAS, cost per result | Only computed from source values; null when the divisor is 0; no mixing of currencies | Pass (`BuildsCampaignInsights`, `AdsDashboard.vue` reviewed, existing tests) |
| Currency | Accounts in different currencies | Totals add up only within the main currency; other platforms are shown separately | Pass (reviewed) |
| Member permissions | Non-GET and write GETs (`*.redirect`, `*.create`, `*.edit`, `*.export`, `*.sync`) | Need write permission and the plan feature | Pass (`UseSellerWorkspace`, `EnsurePackageFeature`) |
| Ads suites | Builders, pages, insights, sync, AI credits, reports, isolation, LinkedIn | All pass | **183 pass, 1,244 assertions** (was 68 failing of 175) |

## C. Changes

**Security**
- `AdCampaignController::ownCampaign()` returns the signed-in owner's campaign or a 404. Facebook and Instagram share it.
- It now runs at the start of `edit`, `update`, `destroy`, `updateStatus` and `duplicate` on every platform.

**Meta**
- `MetaAdsGraph::VERSION = 'v25.0'` and `MetaAdsGraph::pin()`.
- `FacebookAdService` uses the pinned version everywhere, including the admin base URL setting and the account endpoint setting.

**Dashboard**
- `AdsPerformanceService` reads Meta (`FacebookAdService::insights`) and LinkedIn.
- Instagram is `in_meta`.
- Campaign metrics include both Meta builders.

**LinkedIn**
- New `LinkedinInsightsService` and `LinkedinReportingException`.
- The LinkedIn campaigns page now loads performance.

**Text**
- `lang/{en,ar}/ads_dashboard.php` (`no_reporting_text`).

**Tests**
- New: `CampaignOwnershipTest`, `LinkedinInsightsTest`.
- Updated: Meta fakes moved to v25.0, `AdsPerformanceTest`, `PlatformCampaignsPageTest`, `ConnectionGateTest` (current signup flow).
- Fixture fixes:
  - `TestCase` turns the verification gate off by default;
  - the builder fixtures create `users`;
  - the X date test.

**No database, migration, job, schedule or front-end build changes.**

## D. Outstanding items: external, not Socialeaz bugs

**Live verification**
- Every platform needs one PAUSED campaign on a test ad account: create, edit, pause, resume, delete, then compare the reported numbers with each platform's own Ads Manager.
- That needs connected test accounts and your go-ahead, because it touches real ad accounts.

**Platform approvals and credentials (check each app's status)**
- **Meta:** advanced access for `ads_management` and `ads_read`, plus Business Verification.
- **Google Ads:** a developer token with Basic or Standard access. Test access only reaches test accounts.
- **TikTok:** Marketing API app approval.
- **Snapchat:** Marketing API access.
- **X:** Ads API access (by application).
- **LinkedIn:** Advertising API access. Reconnect after approval so `r_ads_reporting` is granted.
- **ChatGPT Ads:** API key.

**API lifetimes**
- **Meta Marketing API v25.0:** no end date yet. Check the changelog when v26.0 reaches the Marketing API.
- **LinkedIn 202606:** supported until about June 2027.
- **Outside Ads:** publishing code still calls Graph v20.0, v21.0 and v23.0 (`PostAccountController`, `InstagramPostService`, `WhatsAppPostService`, `MetaPostService`). Meta auto-upgrades expired Graph API versions, but behaviour can change. I recommend pinning those too, separately.

## E. Not implemented (gaps, not bugs)

- **Campaign import ("Sync Now")** for Google Ads and LinkedIn. Their campaigns pages show only campaigns created in Socialeaz, while the totals cover the whole account.
- **Ad set / ad group and ad-level analytics** on the dashboard. It works at campaign level. Meta's campaigns page has breakdowns.
- **Excel and PDF exports.** CSV only.
- **Currency conversion.** By design, each currency is shown separately and never converted.
- **Automatic background status sync.** Statuses update on Sync Now and on changes made in Socialeaz.

## F. Production readiness

| Area | Assessment |
|---|---|
| Functional | Broad. Every platform has create, change and report flows in code. |
| API integration | Request formats checked for Meta (version) and LinkedIn (docs, Nov 2026). Others unchanged from their earlier builds. **No live call made.** |
| Campaign management | Code and faked tests only. Live PAUSED-campaign test outstanding. |
| Analytics | Real-data only, correct formulas, currency-safe. Now covers 8 of 9 platform keys; Instagram is counted within Meta. |
| Reporting | CSV and scheduled email for every platform, matching the page's numbers. |
| Security | The critical cross-tenant hole is fixed. Member and plan permissions are enforced server-side. Tokens are never sent to the front end. |
| Performance | Reporting is cached for 15 minutes per account and period. Every API call has a timeout. The dashboard loads reporting after the page renders. |
| Multi-tenant | Tested on 9 platforms. |
| Automated tests | Ads suites: 183 pass. Full suite: 46 failed / 1,065 passed, against a baseline of 61 failed. Every remaining failure is in a known pre-existing class (old Breeze auth, Slack, Profile, Localization, Tamara...), none in Ads. |
| Blockers | Live verification and the platform approvals in D. |

**Verdict:** ready to deploy as a fix release (security plus the Meta version). Don't call it fully production-verified until each platform passes the live PAUSED-campaign check.
