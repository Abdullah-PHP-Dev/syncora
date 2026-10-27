# Payment callback simulation

Created 2026-10-27. Local mocks follow official provider contracts:
[Tap charge creation](https://developers.tap.company/reference/create-a-charge),
[Tap redirects](https://developers.tap.company/docs/redirect),
[ClickPay query](https://support.clickpay.com.sa/en/support/solutions/articles/73000623668-7-1-hosted-payment-page-apis-query-transaction),
[ClickPay statuses](https://support.clickpay.com.sa/en/support/solutions/articles/73000632871-what-is-response-code-vs-the-response-status-),
[Tamara checkout](https://docs.tamara.co/reference/createcheckoutsession).

## Purpose

Exercise real Laravel checkout, HTTP clients, callback routing, coupon reservations,
and fulfillment against stateful stand-ins for Tap, ClickPay, and Tamara.
Owner mock discovery was skipped because Postman CLI is signed out. Orbit search
returned unrelated providers; the official contracts above were used instead.

## Execution

Run from the repository root in separate terminals:

```sh
postman simulation run postman/simulations/payment-callback.sim.yaml --port 4900 --no-history --output ndjson
PAYMENT_SIMULATION_URL=http://127.0.0.1:4900 vendor/bin/phpunit tests/Feature/Payments/SharedPaymentCallbackTest.php
```

Use the port in the simulation's `listening` event. Ctrl-C stops the server.
Tests use an isolated SQLite database and dummy credentials. No real gateway calls.
The simulation-backed tests skip when PAYMENT_SIMULATION_URL is absent.

## Assumptions

- Local `__test/{reference}` routes change status, details, and HTTP failures;
  these are test controls, not provider endpoints. Remote responses preserve the
  submitted reference, amount, currency, and customer details (2026-10-27).
- Live credentials, provider delivery retries, public HTTPS reachability, and
  browser 3DS flows still need a sandbox smoke test. The simulation does not prove
  provider acceptance of every customer/address field (2026-10-27).

## Observations

<!-- begin:observations -->
2026-10-27: session preservation regression run passed 76 tests / 782 assertions.
All three gateway members served traffic. Browser POST returns now bounce to the
same URL as GET before StartSession, with no Set-Cookie headers or session
creation. GET performs verification and emits the subscription toast redirect;
notification POSTs still verify directly. ClickPay creation requests include
return_using_get=true. Real production browser cookies/domain settings remain
unverified.

2026-10-27: verified browser GET/POST returns redirect to subscription selection
with success/error/pending toasts, while gateway notifications retain JSON
responses. All members served traffic. Payment/subscription checks passed (66),
as did shared OAuth toast regression checks (9). The destination-page test
confirmed a success toast renders once and disappears on the next page load.
Locale redirect middleware is disabled only in that test because PHPUnit keeps
one set of default-locale routes across redirects; production locale middleware
reflashes session data during its redirect.
2026-10-27: all three members served checkout and verification traffic. The final
regression run passed 66 tests / 533 assertions, including 32 simulation-backed
callback tests. Verified duplicate fulfillment protection, ignored forged status,
amount/currency/reference mismatches, normal CSRF-exempt POST delivery, early
notification retry, provider outage recovery, and Tamara authorise/capture.
No member remained silent.
<!-- end:observations -->

## What didn't work

- 2026-10-27: CLI 1.75 requires each member path to end in config.yaml rather
  than pointing to its directory. Sandbox restrictions also require permission
  to bind/connect to localhost.
