{{--
    Shared ".socialeaz-dash" mini design-system - originally written inline
    in admin/posts/dashboard.blade.php only. Extracted here so the Email
    Marketing module (dashboard/setup/lists/campaigns) can reuse the exact
    same stat cards, trend arrows, dot-status pills, badges, and table
    style instead of a second hand-copied version. Only the genuinely
    generic primitives moved here - posts/dashboard.blade.php's own
    calendar/inbox-grid/action-tile/account-card rules (and the
    deliberately-unscoped .cal-modal-* rules, which render outside this
    wrapper entirely) stay in that file, since nothing else uses them.

    Any page using this partial must wrap its @section('content') body in
    <div class="socialeaz-dash">...</div> for these rules to apply -
    scoping is deliberate (see the calendar-modal comment this was copied
    from: unscoped Bootstrap modals never inherit .socialeaz-dash rules).
--}}
<style>
:root {
    --status-success-bg: rgba(22,163,74,.1);
    --status-success-color: #16a34a;
    --status-info-bg: rgba(8,145,178,.1);
    --status-info-color: #0891b2;
    --status-warning-bg: rgba(217,119,6,.1);
    --status-warning-color: #d97706;
    --status-danger-bg: rgba(225,29,72,.1);
    --status-danger-color: #e11d48;
    --status-muted-bg: rgba(139,141,156,.12);
    --status-muted-color: #8b8d9c;
}

.socialeaz-dash {
    --dash-bg: #f5f5fa;
    --dash-card: #ffffff;
    --dash-card-hover: #f7f7fc;
    --dash-border: rgba(20,20,40,.08);
    --dash-text: #4b4d5c;
    --dash-heading: #1e1e2d;
    --dash-muted: #8b8d9c;
    --dash-primary: #7c5cff;
    --dash-primary-2: #a855f7;
    --dash-success: #16a34a;
    --dash-danger: #e11d48;
    --dash-warning: #d97706;
    --dash-info: #0891b2;

    background: var(--dash-bg);
    color: var(--dash-text);
    border-radius: 1rem;
    padding: 1.5rem;
    margin: -1.5rem;
    min-height: calc(100vh - 8rem);
}
.socialeaz-dash .dash-title { color: var(--dash-heading); font-weight: 700; }
.socialeaz-dash .dash-subtitle { color: var(--dash-muted); }
.socialeaz-dash .dash-input {
    background: var(--dash-card); border: 1px solid var(--dash-border); color: var(--dash-text);
    border-radius: .5rem; padding: .4rem .75rem; font-size: .8125rem;
}
.socialeaz-dash .dash-input::placeholder { color: var(--dash-muted); }
.socialeaz-dash .dash-btn {
    display: inline-flex; align-items: center; gap: .375rem;
    border-radius: .5rem; padding: .5rem .9rem; font-size: .8125rem; font-weight: 600;
    border: 1px solid var(--dash-border); text-decoration: none; position: relative;
}
.socialeaz-dash .dash-btn-ghost { background: var(--dash-card); color: var(--dash-text); }
.socialeaz-dash .dash-btn-ghost:hover { background: var(--dash-card-hover); color: var(--dash-primary); border-color: var(--dash-primary); }
.socialeaz-dash .dash-btn-primary { background: linear-gradient(135deg, var(--dash-primary), var(--dash-primary-2)); color: #fff; box-shadow: 0 4px 12px rgba(124,92,255,.28); }
.socialeaz-dash .dash-btn-primary:hover { opacity: .92; color: #fff; }

.socialeaz-dash .dash-card {
    background: var(--dash-card); border: 1px solid var(--dash-border);
    border-radius: .85rem; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(20,20,50,.04);
}
.socialeaz-dash .dash-card-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: .5rem;
}
.socialeaz-dash .dash-card-header h6 { color: var(--dash-heading); font-weight: 600; }
.socialeaz-dash .dash-link { color: var(--dash-primary); font-size: .8125rem; text-decoration: none; font-weight: 500; white-space: nowrap; }
.socialeaz-dash .dash-link:hover { text-decoration: underline; }

.socialeaz-dash .dash-stat-label { color: var(--dash-muted); font-size: .8125rem; margin-bottom: .5rem; }
.socialeaz-dash .dash-stat-value { color: var(--dash-heading); font-size: 1.6rem; font-weight: 700; line-height: 1; }
.socialeaz-dash .dash-stat-foot { color: var(--dash-muted); font-size: .75rem; margin-top: .6rem; }
.socialeaz-dash .dash-trend { display: inline-flex; align-items: center; gap: .1rem; font-weight: 700; }
.socialeaz-dash .dash-trend-up { color: var(--dash-success); }
.socialeaz-dash .dash-trend-down { color: var(--dash-danger); }
.socialeaz-dash .dash-sparkline { margin-top: .5rem; height: 32px; }

.socialeaz-dash .dash-status-pill { display: inline-flex; align-items: center; gap: .35rem; color: var(--dash-success); font-size: .7rem; font-weight: 600; }
.socialeaz-dash .dash-status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--dash-success); display: inline-block; }
.socialeaz-dash .dash-status-pill.is-warning { color: var(--dash-warning); }
.socialeaz-dash .dash-status-pill.is-warning .dot { background: var(--dash-warning); }
.socialeaz-dash .dash-status-pill.is-danger { color: var(--dash-danger); }
.socialeaz-dash .dash-status-pill.is-danger .dot { background: var(--dash-danger); }
.socialeaz-dash .dash-status-pill.is-muted { color: var(--dash-muted); }
.socialeaz-dash .dash-status-pill.is-muted .dot { background: var(--dash-muted); }

.socialeaz-dash .dash-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.socialeaz-dash .dash-table th { text-align: left; color: var(--dash-muted); font-weight: 600; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; padding: 0 .5rem .6rem; border-bottom: 1px solid var(--dash-border); }
.socialeaz-dash .dash-table td { padding: .6rem .5rem; border-bottom: 1px solid var(--dash-border); vertical-align: middle; color: var(--dash-text); }
.socialeaz-dash .dash-table tr:last-child td { border-bottom: none; }
.socialeaz-dash .dash-badge { display: inline-block; padding: .2rem .55rem; border-radius: .4rem; font-size: .68rem; font-weight: 600; }
.socialeaz-dash .dash-badge-success { background: var(--status-success-bg); color: var(--status-success-color); }
.socialeaz-dash .dash-badge-info { background: var(--status-info-bg); color: var(--status-info-color); }
.socialeaz-dash .dash-badge-warning { background: var(--status-warning-bg); color: var(--status-warning-color); }
.socialeaz-dash .dash-badge-danger { background: var(--status-danger-bg); color: var(--status-danger-color); }
.socialeaz-dash .dash-badge-muted { background: var(--status-muted-bg); color: var(--status-muted-color); }
.socialeaz-dash .dash-empty-row { color: var(--dash-muted); text-align: center; padding: 1.5rem 0 !important; border-bottom: none !important; display: block; }

.socialeaz-dash .apexcharts-text { fill: var(--dash-muted); }
.socialeaz-dash .apexcharts-legend-text { color: var(--dash-muted) !important; }

/* =========================================================
   NUMBERED-CIRCLE STEP INDICATOR - used by the Email Marketing
   setup wizard header and the campaign builder's 4-step flow.
   Distinct from admin/ads/google/campaigns/create.blade.php's
   .campaign-steps (a rounded PILL, no connecting line) - the
   reference screenshots for this module specifically show numbered
   circles joined by a line, which didn't exist anywhere in this
   codebase yet.
========================================================= */
.socialeaz-dash .dash-stepper { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 0; margin-bottom: 1.5rem; }
.socialeaz-dash .dash-stepper-item { display: flex; align-items: center; }
.socialeaz-dash .dash-stepper-item:last-child .dash-stepper-line { display: none; }
.socialeaz-dash .dash-stepper-circle {
    width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; background: var(--dash-card-hover); color: var(--dash-muted);
    border: 2px solid var(--dash-border); flex-shrink: 0;
}
.socialeaz-dash .dash-stepper-label { margin: 0 .6rem 0 .5rem; font-size: .8125rem; font-weight: 600; color: var(--dash-muted); white-space: nowrap; }
.socialeaz-dash .dash-stepper-line { width: 32px; height: 2px; background: var(--dash-border); margin-right: .6rem; }
.socialeaz-dash .dash-stepper-item.is-current .dash-stepper-circle { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; }
.socialeaz-dash .dash-stepper-item.is-current .dash-stepper-label { color: var(--dash-heading); }
.socialeaz-dash .dash-stepper-item.is-done .dash-stepper-circle { background: var(--dash-success); border-color: var(--dash-success); color: #fff; }
.socialeaz-dash .dash-stepper-item.is-done .dash-stepper-label { color: var(--dash-heading); }
.socialeaz-dash .dash-stepper-item.is-done .dash-stepper-line { background: var(--dash-success); }

@media (max-width: 576px) {
    .socialeaz-dash .dash-stepper-label { display: none; }
    .socialeaz-dash .dash-stepper-line { width: 16px; }
}

/* =========================================================
   WIZARD STEP PANELS - same show/hide-by-data-step mechanism as
   admin/ads/google/campaigns/create.blade.php's own .wizard-step, scoped
   under .socialeaz-dash so it can sit alongside .dash-stepper for the
   Email Marketing campaign builder's 4-step flow.
========================================================= */
.socialeaz-dash .wizard-step { display: none; }
.socialeaz-dash .wizard-step.active { display: block; }
.socialeaz-dash .wizard-nav { display: flex; justify-content: space-between; margin-top: 1rem; }
.socialeaz-dash .review-row { display: flex; justify-content: space-between; padding: .6rem 0; border-bottom: 1px solid var(--dash-border); font-size: .85rem; }
.socialeaz-dash .review-row:last-child { border-bottom: none; }
.socialeaz-dash .review-row span:first-child { color: var(--dash-muted); }
.socialeaz-dash .review-row span:last-child { font-weight: 600; color: var(--dash-heading); text-align: right; }
.socialeaz-dash .preflight-check { display: flex; align-items: center; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid var(--dash-border); font-size: .85rem; }
.socialeaz-dash .preflight-check:last-child { border-bottom: none; }
.socialeaz-dash .preflight-check .bx { font-size: 1.1rem; }
.socialeaz-dash .preflight-check.is-pass .bx-check-circle { color: var(--dash-success); }
.socialeaz-dash .preflight-check.is-fail .bx-x-circle { color: var(--dash-danger); }
</style>
