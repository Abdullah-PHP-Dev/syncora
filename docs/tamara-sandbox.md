# Tamara sandbox

This integration deliberately uses only https://api-sandbox.tamara.co. It pays for one monthly or yearly subscription period; it does not store cards or schedule recurring charges. Refunds are outside this checkout flow.

1. Add your merchant sandbox API token and notification token to `.env` as `TAMARA_API_TOKEN` and `TAMARA_NOTIFICATION_TOKEN`. Never commit these tokens.
2. Run `php artisan migrate --path=database/migrations/2026_10_05_000001_create_tamara_orders_table.php` and `php artisan config:clear`.
3. Use a public HTTPS development host or tunnel, and set `APP_URL` to that address. Open checkout on that same host so your login session and the signed return URL use the same origin. `socialize.test` is local and cannot receive Tamara webhooks.
4. In the Tamara sandbox partner portal, register `https://YOUR-HOST/api/payments/tamara/webhook`. Subscribe to the approved event (mandatory), and captured, declined, canceled and expired events available to your merchant. Notifications require an HS256 JWT signed with your notification token, supplied as a bearer token or `tamaraToken` query parameter.
5. Ensure the seller profile contains a valid mobile number and email. Use test customer details supplied in your Tamara sandbox portal. Choose Tamara at subscription checkout.
6. Complete Tamara checkout. The return page verifies the order through the API. An approved order is authorised and captured for digital delivery; only a matching fully captured order activates the subscription.
7. Ensure Laravel's scheduler is running (`php artisan schedule:work` locally). `php artisan payments:reconcile-tamara` retries delayed confirmations manually. Each payment can create only one subscription cycle, even when the webhook and return URL are repeated.

Leave `TAMARA_PAYMENT_TYPE` empty for single checkout. If your merchant has single checkout disabled, set the payment type and `TAMARA_INSTALMENTS` according to the options enabled for your merchant.

Tests: `vendor/bin/phpunit tests/Feature/Payments/TamaraPaymentTest.php`.

Official references:
- https://docs.tamara.co/reference/createcheckoutsession
- https://docs.tamara.co/reference/getorderdetails
- https://docs.tamara.co/reference/authoriseorder
- https://docs.tamara.co/reference/captureorder
- https://docs.tamara.co/reference/tamara-api-reference-documentation
