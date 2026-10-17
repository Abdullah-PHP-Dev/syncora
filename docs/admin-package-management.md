# Admin package and feature management

The admin-only `/plans` and `/package-features` routes manage the existing
`Bundle` catalog. Both are protected by authentication, active-user checks,
and the `admin` role. The packages sidebar item is a direct link.

## Admin workflow

1. Create a feature in Feature Catalog with English/Arabic names and a stable code.
2. Select its type: on/off permission, capacity, billing-cycle allowance, or text benefit.
3. Edit a package to set that feature's value, pricing, display benefits, and visibility.
4. Review Recent Changes for saved values, versions, and the responsible administrator.

Capacity and allowance values use `-1` for unlimited, `0` for not included,
and positive integers for a fixed limit. Text benefits allow up to 500 characters.
Feature codes and types cannot change after creation. Archive a feature to remove
it from editors; existing package values are retained and restored when reactivated.
An archived feature cannot be submitted as an editable field.

Packages store typed values in `bundles.entitlement_settings`. Existing legacy
`features`, `limits`, and other metadata are preserved. The migration seeds eight
initial features from `config/package_features.php` and explicit initial settings
for existing packages. New packages default to disabled permissions and zero limits.
Catalog changes after migration belong in the admin UI; config does not override them.

Every actual package or feature save writes a `package_changes` record in the same
transaction. Version checks reject stale forms. This history is for audit and future
subscription snapshots; there is no automatic version restoration UI.

## Seller access and usage enforcement

`PackageAccessService` reads the seller's latest subscription and the package's
structured `access_settings`. The admin package editor exposes disabled, preview,
included, and add-on-required modes. Preview permits reads; writes require included.
Add-on-required access is denied until explicitly enabled; purchasing add-ons is
not implemented. Tier labels remain descriptive metadata.

`EnsurePackageFeature` protects mapped seller module routes, including direct JSON
requests. Subscription expiry blocks module access while billing, profile, usage,
and support remain accessible. Background publishing, messaging, AI replies, and
scheduled email processing also check package access. Remote email cancellation
is best effort; messages already sent cannot be recalled.

`PackageUsageService` serializes reservations using the seller row and records
idempotent usage events. Limits cover publishing and advertising accounts, content
categories, gallery source-file storage, scheduled/published posts per destination,
AI requests, and hashtag generation. Soft-deleted retained files consume storage;
deleting posts does not refund usage. Failed HTTP AI requests refund reservations.
Existing published history is not backfilled into the ledger.

Monthly allowances reset from the subscription's original monthly anniversary,
including yearly subscriptions. Trial allowances apply across the trial. Future
paid renewals use calendar months; existing subscription dates are not rewritten.
Package edits affect existing subscribers immediately. Retired packages continue
serving existing subscriptions.

Custom catalog features require application integration. Approval workflows,
analytics history filtering, and differences between descriptive tiers are not
enforced yet. Public benefit text and structured access should be
kept consistent by the administrator.

## Seller teams

Sellers manage members at `/seller-team`; `/workspaces` lets an invited user select
an accepted workspace or return to their own account. This is separate from
platform employee and support roles. The package's `team_management` mode must be
included, and `team_members` controls total seats including the owner. Pending
unexpired invitations reserve seats. Zero denies access, one permits only the
owner, and -1 is unlimited. Invitations expire after seven days, store only a token
hash, and are accepted only by a logged-in account with the invited email address.
The owner receives a private invitation URL to share; no email is sent automatically.

Each member has none/read/write access per module. Shared workspace requests use
the owner's resources and quotas for that request while preserving the member's
login session. Both member permissions and live owner package settings apply.
The request attributes `workspace_actor` and `workspace_member` retain the real
actor for audit integrations. Billing, profile, platform administration, team
administration, and provider connections remain owner-managed. Inbox broadcast
authorization also checks member access and existing channel ownership.

Removing a member immediately invalidates their workspace selection. Expiry or
disabling team management blocks shared workspace access. A downgrade retains a
deterministic subset of accepted members, oldest membership first, within the new
seat allowance. Owners can still view and remove members after subscription expiry.
Permissions and seats are enforced server-side for direct requests as well as forms.

## Deployment

Run `php artisan migrate --force`, build frontend assets with `npm run build`,
refresh the deployment's application caches, and restart queue workers. The new
migration backfills structured access from existing package settings. Review the
Module Access settings after migration, especially preview and add-on modes.
Do not rerun `ReferencePackageSeeder` unless intentionally restoring its allocation.

## Validation

`php vendor/bin/phpunit tests/Feature/Packages`

The tests use an isolated SQLite database and cover admin authorization, typed
settings, limits, unknown keys, immutable codes/types, archived values, edit
conflicts, history, Arabic rendering, and direct package navigation.

## Screenshot allocation (17 October 2026)

`ReferencePackageSeeder` applies the supplied feature matrix: Free Trial (14 days),
Basic (SAR 49/month), Pro (SAR 149/month), and Business (SAR 399/month). It records
all 18 feature allocations, splits publishing and advertising accounts, and stores
trial-total versus monthly allowance periods in `meta.allowance_period`.
The package editor exposes that period; the admin list includes a full comparison.

Existing Starter and Empire rows become Basic and Business while retaining their
IDs and slugs. Unicorn is retained as inactive. The eight earlier generic controls
are archived, with their stored values preserved. Existing yearly prices are
retained; newly created packages use the application's existing 12-month default.
Email access descriptions remain exactly as supplied because quantities were not
specified. Tier descriptions are stored as text; structured modes control seller access.

The seeder records system changes in history and is idempotent. Run it explicitly
only when applying or restoring the reference allocation; it overwrites the
reference settings. It is also included after BundleSeeder for initial setup.

## Dynamic public pricing

`Front/PricingController` loads all active bundles (including the trial) and active
catalog features. `PackagePresentationService` formats each saved setting for
cards and comparison rows. The page no longer compares legacy hour/daily/monthly
metadata or generic integration flags. Prices, names, descriptions, recommendations,
feature labels, and allowance periods reflect current admin data on each request.
Yearly billing changes prices and checkout links, while monthly allowances and
trial totals retain their configured periods. Archived plans/features are excluded.
Manual display benefits are shown separately; seeded reference text is not reused
as a stale substitute for live feature settings. Editing those benefit lines in
the admin editor marks them as manual. Existing catalog data uses the reference
allocation marker to recognize generated benefit text without reseeding.

The homepage now uses the same catalog query and formatter through
`Front/HomeController`. Its pricing cards show live monthly prices, trial durations,
and six configured highlights with a link to the complete comparison. The adjacent
example subscription preview also uses an active package and its saved allowances.
Archived packages/features disappear from both pages on the next request.

Editing a package redirects back to its editor after save. Selecting Recommended
clears the flag from every other package in the same transaction. Catalog rows are
locked in ID order; automatic demotions increment their versions and write history,
so previously opened editors cannot silently restore old recommendations. Creating
a package retains the redirect to the catalog. Disabling Recommended allows zero
recommended packages.
