# Subscription coupons

Administrators manage coupons at `/coupons`. Sellers apply them through the
subscription checkout's dedicated `/subscription/coupon` endpoint. Previewing a
coupon never creates a payment transaction or consumes usage.

## Rules and admin management

- Fixed amount or percentage; percentages support two decimal places, up to 100%.
- Currency, minimum subtotal, maximum discount per checkout, total discount budget.
- Global and per-seller usage limits; blank means unlimited.
- Eligible packages, sellers, billing cycles, and payment methods, or all of them.
- First paid-tier subscription only, including checks for existing pending purchases.
- Optional combination with an already discounted yearly catalog price.
- Start/expiry dates in the app timezone, enable/disable, archive/restore, redemption
  history, audit history, and protection against stale administrator edits.

Codes are case insensitive and cannot be reused after archival. Codes and currencies
with redemption history cannot change. Existing payment reservations retain their
financial snapshot when coupon rules change. One coupon applies to one payment;
future renewals use current prices. No stacking, recurring promotions, or automatic refund of usage inventory is introduced.
Catalog prices include VAT. Checkout extracts VAT from the discounted total using
`SUBSCRIPTION_VAT_RATE` (default 15%) without adding it again. The transaction stores
the included VAT and the before-VAT amount alongside its pricing snapshot.

## Checkout integration

The server recalculates the catalog subtotal and coupon discount and sends the net
amount through the existing PaymentManager. Submitted amount/discount values are
ignored. An expected-total check rejects a changed price before starting payment.
Tamara's existing hard-coded test amount was replaced with the server checkout amount;
no new gateway, provider configuration, callback route, webhook, or reconciliation
system is introduced by this module.

A UUID checkout key is unique per seller. Repeating it returns the existing checkout,
while changing its selections is rejected. Full discounts activate the subscription
transactionally without a gateway request. Internal payment and activation changes
share a transaction. Existing subscription service success/failure hooks complete
or release the coupon reservation; the existing payment integration is responsible
for verifying payment and invoking these hooks with its stored transaction.

Pending and completed redemptions count against limits. Seller and coupon row locks
serialize reservations; counters on the locked coupon row avoid stale aggregate
reads under MySQL repeatable-read isolation. Confirmed failures release inventory.
Ambiguous provider errors retain reservations, because a payment may have started.
They are not automatically released by elapsed time. Provider verification and
reconciliation remain responsibilities of the existing payment flow.

## Deployment and testing

Run `php artisan migrate --force`, build assets using `npm run build`, and refresh
normal deployment caches. The migration adds coupons, redemption and audit tables,
and checkout idempotency/fulfillment fields on the existing wallet transaction table.
It creates no promotions and changes no existing package prices.

```sh
php vendor/bin/phpunit tests/Feature/Coupons
```

The tests mock provider requests and do not make real payments. They cover admin
roles and forms, history and archival, eligibility, calculation/rounding, usage and
budget reservations, failure release, checkout replay, price changes, zero totals,
and subscription completion hooks. Gateway integration is reused and is not a new
provider certification or production payment test.
