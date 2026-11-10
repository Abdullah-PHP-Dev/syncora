# Socialeaz CRM — Product Strategy & UI/UX Design (Phase 1)

Design reference only. No application code was changed.

| File | Contents |
|---|---|
| `Socialeaz-CRM-Complete.pdf` | All 79 boards in review order |
| `sections/01 … 11 *.pdf` | Same boards split by section (matches the Canva design) |
| `01-…`, `02-…`, `03-…` PDFs | Earlier screen sets (kept; now included in the complete PDF) |

## Recommended structure

CRM is **one item in the existing Socialeaz sidebar** with nine sub-pages: Overview, Contacts, Companies, Leads, Deals, Activities, Automations, Reports, Settings. Conversations stay in the existing Inbox and Comments, which get a CRM panel instead of a second inbox.

**Positioning:** the CRM for businesses whose customers start the conversation on social (WhatsApp, Instagram, Facebook, TikTok lead ads). Socialeaz's edge over HubSpot/Pipedrive/Zoho is that the inbox, comments, ads, email marketing and AI already live here.

## Sitemap → screen IDs

- **Overview**: D-01 dashboard · G-01 ⌘K search · G-02 notifications
- **Contacts**: C-01 list · C-02 filters/bulk · C-03 create drawer · C-04 profile 360° · C-05 edit · C-06 duplicates/merge · C-07–09 import (upload, map, review) · C-10 export · C-11 segments · C-12 empty
- **Companies**: CO-01 list · CO-02 profile · CO-03 create
- **Leads**: L-01 list · L-02 profile/qualification · L-03 create · L-04 assign · L-05 convert · L-06 disqualify · AI-04 AI qualification
- **Deals**: D-02 Kanban · D-03 drag state · D-04 list · D-05 detail · D-06 create · D-07 won · D-08 lost · D-09 empty
- **Activities**: A-01 tasks · A-02 log/follow-up · A-03 calendar · A-04 team activity
- **Conversations**: M-01 Inbox + CRM panel · M-02 identify/link · M-03 comments → CRM
- **AI**: AI-01 insights · AI-02 follow-up generator · AI-03 Ask AI panel · AI-04 qualification
- **Automations**: AU-01 list · AU-02 builder
- **Reports**: R-01 sales performance · R-02 lead sources & ROI
- **Settings**: S-01 general · S-02 pipelines & stages · S-03 sources & statuses · S-04 custom fields · S-05 tags & lifecycle · S-06 lead scoring · S-07 roles & permissions · S-08 team access · S-09 import/export · S-10 channels & capture · S-11 AI · S-12 notifications
- **Cross-cutting**: W-01 workspace switcher · W-02 permission & plan states · T-01 tablet · MB-01–03 mobile
- **Strategy & system**: P-01–P-06 strategy, ecosystem, sitemap, data model, scope · DS-01–03 design system · J-01–05 journeys

## Main journeys (J-01 – J-05)

1. **Contact**: list → create → profile → note → follow-up → history
2. **Lead**: list → create → assign → AI qualification → qualify → convert → deal (alternative: disqualify)
3. **Sales**: pipeline → create deal → drag stage → log activity → won / lost
4. **Social → CRM**: buying-intent comment / DM → identify → link conversation → AI follow-up → task
5. **Workspace & permissions**: roles → member access → switch workspace → restricted / read-only states

## Release 1 (launch)

- Contacts, companies, leads and deals (list, profile, create/edit)
- One pipeline with configurable stages
- Tasks and calendar
- Inbox and Comments CRM panel
- Meta and TikTok lead-ad capture
- Import/export with duplicate check
- Tags, lifecycle and custom fields
- Own/team/all roles
- AI summary, next step and follow-up draft
- Overview dashboard and two reports
- Notifications and ⌘K search

**Release 2:** automations, scoring rules plus AI signal, AI qualification, segments → email lists and ad audiences, merge at scale, Business Profile reviews, campaign ROAS, mobile polish.

**Release 3:** quotes and products, WhatsApp sequences, Gmail/Outlook sync, forecasting, custom objects, CRM API and webhooks.

**Cut from launch:** built-in calling, territories, custom objects, a separate CRM inbox.

## Recommendations before development

1. **Tenancy.** Every CRM table gets `workspace_id` (the tenant user, via the existing `UseSellerWorkspace` swap) plus `owner_id`. AI, exports and search use the same scope.
2. **Permissions.** Add a `crm` key to `config/package_access.php` modules so the existing none/read/write team permission gates the module. Layer a CRM role on top (own / team / all record visibility, plus action rights for delete, export, merge, reassign and settings).
3. **One person record.** Link `Messaging\Conversation` (nullable `contact_id`) and `EmailMarketing\EmailSubscriber` (1:1 to contact) instead of duplicating people. Decide sync direction before building import.
4. **Plan limits.** Add contact, pipeline and automation limits through the existing package limits. Only Pro currently enforces limit keys, so set Basic, Business and free-trial values explicitly.
5. **Cleanup.** `resources/views/admin/crm-dashboard.blade.php` is a leftover theme demo ("Customer Ratings"), not CRM. Remove or replace it when the real CRM route is added.
6. **AI guardrails.** AI only reads records the member can see, never sends anything without a person approving it, and skips fields marked sensitive. It bills the existing AI credits.
7. **Arabic first.** Every label needs an `ar` entry. Use RTL layouts, and keep numbers, phone numbers and currency LTR.
