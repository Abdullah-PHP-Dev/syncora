# Subscription package selection

While the latest subscription is active (`status=active`, `is_active=true`) and
has not expired, the seller may renew the same bundle or select a higher tier.
An end date of today remains valid through the end of the day in the application
timezone. A missing end date is treated as an active term without a fixed expiry.
Expired, cancelled, inactive, and free-trial subscriptions allow a new selection.

Higher tier means greater bundle `sort_order`; when orders match, the undiscounted
monthly catalog price determines the higher tier. Yearly totals and coupons do
not change tier eligibility.

- `GET /subscription/plans`: each `data.packages[]` includes `can_select` (boolean),
  `selection_message` (localized explanation or null), `selection_label`, and
  `blocked_label`. Locale-prefixed page `/en/subscription/select` uses these fields
  to disable lower packages while keeping them visible.
- `GET /subscription/checkout`: a prohibited selection redirects to
  `subscription/select` with the localized error.
- `GET /subscription/checkout-data`, `POST /subscription/coupon`, and
  `POST /subscription/checkout`: prohibited selections return HTTP 422 with
  `errors.bundle_id`. No new transaction, coupon reservation, or gateway call is made.
- Fulfillment checks eligibility again under the seller lock. An older payment
  cannot downgrade a newer active package; it remains unfulfilled for reconciliation.

Allowed same-bundle renewals extend an unexpired end date. Bundle changes and
expired renewals start today. Payment verification and duplicate-callback guards
remain required before fulfillment.
