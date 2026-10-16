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

## Stage boundary

This stage manages settings only. It does not enforce seller permissions or
allowances, modify subscriptions, or introduce merchant staff sharing. Existing
pricing and checkout continue to consume the pricing and display-benefit fields
as before. New custom feature definitions require a corresponding application
integration before they can control seller actions. The next stage must define
subscription snapshots, usage periods, renewal/upgrade behavior, and enforcement
for every supported feature before activating restrictions.

## Validation

`php vendor/bin/phpunit tests/Feature/Packages/PackageManagementTest.php`

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
specified. Tiered access is stored as text; seller-side interpretation is deferred.

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
