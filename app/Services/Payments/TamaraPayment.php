<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\TamaraOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CouponService;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class TamaraPayment implements PaymentInterface
{
    private function client(): PendingRequest
    {
        $token = config('services.payment.tamara.api_token');
        if (! $token) {
            throw new RuntimeException('Tamara credentials have not been configured.');
        }

        return Http::baseUrl(config('services.payment.tamara.base_url'))
            ->withToken($token)->acceptJson()->asJson()->connectTimeout(10)->timeout(30);
    }

    public function pay(array $data): array
    {
        $order = null;
        try {
            $client = $this->client();
            $user = $data['user'];
            if (! is_string($user->mobile) || ! preg_match('/^\+?[0-9]{9,15}$/D', $user->mobile)) {
                return ['status' => 'failed', 'message' => __('A valid mobile number is required for Tamara.')];
            }
            $transaction = $data['transaction'] ?? null;
            $amount = round((float) $data['amount'], 2);
            $currency = $transaction?->meta['currency'] ?? $data['bundle']->currency;
            $money = ['amount' => $amount, 'currency' => $currency];
            $zero = ['amount' => 0, 'currency' => $currency];
            $order = TamaraOrder::create([
                'user_id' => $user->id, 'bundle_id' => $data['bundle']->id,
                'bundle_name' => $data['bundle']->name, 'cycle' => $data['cycle'],
                'amount' => $amount, 'currency' => $currency, 'items' => [],
                'environment' => $this->environment(), 'status' => 'pending',
            ]);
            if ($transaction) {
                $meta = $transaction->fresh()->meta;
                $meta['tamara_order_id'] = $order->id;
                $transaction->update(['meta' => $meta]);
            }
            $returnUrl = fn (string $result) => $transaction
                ? PaymentCallbackUrl::forTransaction($transaction)
                : URL::signedRoute('payments.tamara.return', ['order' => $order->id, 'result' => $result]);
            $name = preg_split('/\s+/', trim($user->name), 2);
            $address = ['first_name' => $name[0], 'last_name' => $name[1] ?? $name[0],
                'line1' => 'Digital delivery', 'city' => 'Riyadh', 'country_code' => 'SA'];
            $items = [['name' => $data['bundle']->name, 'type' => 'Digital',
                'reference_id' => $order->id, 'sku' => (string) $data['bundle']->id,
                'quantity' => 1, 'total_amount' => $money]];
            $order->update(['items' => $items]);
            $body = [
                'total_amount' => $money, 'shipping_amount' => $zero,
                'tax_amount' => ['amount' => (float) ($transaction?->vat ?? 0), 'currency' => $currency],
                'order_reference_id' => $order->id, 'items' => $items,
                'consumer' => ['email' => $user->email, 'first_name' => $name[0],
                    'last_name' => $name[1] ?? $name[0], 'phone_number' => $user->mobile],
                'country_code' => 'SA', 'description' => 'Subscription purchase',
                'merchant_url' => ['success' => $returnUrl('success'), 'cancel' => $returnUrl('cancel'),
                    'failure' => $returnUrl('failure'),
                    'notification' => $transaction ? PaymentCallbackUrl::forTransaction($transaction, true)
                        : url('/api/payments/tamara/webhook')],
                'payment_type' => config('services.payment.tamara.payment_type') ?: 'PAY_BY_INSTALMENTS',
                'instalments' => (int) config('services.payment.tamara.instalments', 3),
                'shipping_address' => $address, 'billing_address' => $address,
                'platform' => config('app.name'), 'locale' => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            ];
            $response = $client->post('/checkout', $body);
            if ($response->serverError() || $response->status() === 429) {
                $response->throw();
            }
            if (! $response->successful()) {
                $order->update(['status' => 'failed']);

                return ['status' => 'failed', 'message' => __('Tamara could not create checkout. Please choose another payment method.')];
            }
            $result = $response->json();
            $url = $result['checkout_url'] ?? '';
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (($result['status'] ?? null) !== 'new' ||
                ! is_string($result['order_id'] ?? null) || $result['order_id'] === '' ||
                parse_url($url, PHP_URL_SCHEME) !== 'https' ||
                ! is_string($host) || ! str_ends_with($host, '.tamara.co')) {
                throw new RuntimeException('Tamara returned an incomplete checkout response.');
            }
            $order->update(['gateway_order_id' => $result['order_id'], 'checkout_id' => $result['checkout_id'] ?? null,
                'checkout_url' => $url, 'status' => 'new']);
            if ($transaction) {
                $transaction->update(['gateway_reference' => $result['order_id']]);
            }

            return ['status' => 'pending', 'redirect_url' => $url,
                'transaction_id' => $order->id, 'session_id' => $result['order_id']];
        } catch (Throwable $exception) {
            // An order may have been created remotely: preserve its reservation for reconciliation.
            if ($order) {
                report($exception);
                throw $exception;
            }

            return ['status' => 'failed', 'message' => __('Tamara credentials or customer details are not configured.')];
        }
    }

    private function environment(): string
    {
        return str_contains((string) config('services.payment.tamara.base_url'), 'sandbox') ? 'sandbox' : 'production';
    }

    public function status(array $data): string
    {
        $transaction = $data['transaction'];
        $order = TamaraOrder::find($transaction->meta['tamara_order_id'] ?? '');
        if (! $order || ! $transaction->gateway_reference) {
            return 'pending';
        }
        if ($order->gateway_order_id !== $transaction->gateway_reference ||
            (int) $order->user_id !== (int) $transaction->seller_id ||
            $order->currency !== ($transaction->meta['currency'] ?? null) ||
            CouponService::minor($order->amount) !== CouponService::minor($transaction->amount)) {
            throw new RuntimeException('Tamara order does not match the stored transaction.');
        }
        if ($this->synchronize($order)) {
            return 'paid';
        }

        return in_array($order->fresh()->status, ['declined', 'expired', 'canceled', 'cancelled', 'failed'], true)
            ? 'failed' : 'pending';
    }

    public function verify(array $data): bool
    {
        $id = $data['gateway_reference'] ?? null;
        if (! is_string($id) || $id === '') {
            return false;
        }
        $order = TamaraOrder::where('gateway_order_id', $id)->first();

        return $order ? $this->synchronize($order) : false;
    }

    public function synchronize(TamaraOrder $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = TamaraOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->paid_at) {
                return true;
            }
            if (! $order->gateway_order_id || $order->environment !== $this->environment()) {
                return false;
            }
            $transaction = $this->linkedTransaction($order);
            $client = $this->client();
            $remote = $this->details($client, $order);
            $status = $remote['status'];
            if (in_array($status, ['declined', 'expired', 'canceled', 'cancelled'], true)) {
                $order->update(['status' => $status]);
                if ($transaction = $this->linkedTransaction($order)) {
                    app(SubscriptionCheckoutService::class)->fail($transaction, __('Your payment was not completed. Please try again.'));
                }

                return false;
            }
            if ($status === 'approved') {
                $client->post('/orders/'.$order->gateway_order_id.'/authorise')->throw();
                $remote = $this->details($client, $order);
                $status = $remote['status'];
            }
            if ($status === 'authorised') {
                $money = ['amount' => (float) $order->amount, 'currency' => $order->currency];
                $zero = ['amount' => 0, 'currency' => $order->currency];
                $capture = $client->post('/payments/capture', [
                    'order_id' => $order->gateway_order_id, 'total_amount' => $money,
                    'items' => $order->items, 'shipping_amount' => $zero,
                    'tax_amount' => ['amount' => (float) ($transaction?->vat ?? 0), 'currency' => $order->currency],
                    'discount_amount' => $zero,
                    'shipping_info' => ['shipped_at' => now()->toIso8601String(), 'shipping_company' => 'Digital delivery'],
                ])->throw()->json();
                $order->capture_id = $capture['capture_id'] ?? null;
                $remote = $this->details($client, $order);
                $status = $remote['status'];
            }
            $order->status = $status;
            $order->save();
            if ($status !== 'fully_captured') {
                return false;
            }
            $transaction = $this->linkedTransaction($order);
            if ($transaction) {
                app(SubscriptionCheckoutService::class)->complete($transaction);
                $order->update(['paid_at' => now()]);

                return true;
            }
            // Also lock the user: separate checkout attempts for one user must not race.
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $start = now();
            $months = $order->cycle === 'yearly' ? 12 : 1;
            $end = $start->copy()->addMonthsNoOverflow($months);
            $subscription = Subscription::updateOrCreate(['user_id' => $order->user_id], [
                'bundle_id' => $order->bundle_id, 'bundle_name' => $order->bundle_name,
                'billing_period' => $months, 'start_date' => $start, 'end_date' => $end,
                'status' => 'active', 'is_active' => true,
            ]);
            SubscriptionCycle::create([
                'subscription_id' => $subscription->id, 'user_id' => $order->user_id,
                'bundle_id' => $order->bundle_id, 'start_date' => $start, 'end_date' => $end,
                'type' => 'purchase', 'status' => 'active',
            ]);
            $order->update(['paid_at' => now()]);

            return true;
        });
    }

    private function linkedTransaction(TamaraOrder $order): ?WalletTransaction
    {
        // Legacy Tamara orders predate wallet-based subscription checkouts.
        if (! Schema::hasTable('wallet_transactions')) {
            return null;
        }

        return WalletTransaction::where('payment_gateway', 'tamara')
            ->where('meta->tamara_order_id', $order->id)->first();
    }

    private function details(PendingRequest $client, TamaraOrder $order): array
    {
        $remote = $client->get('/orders/'.$order->gateway_order_id)->throw()->json();
        if (($remote['order_id'] ?? null) !== $order->gateway_order_id ||
            ($remote['order_reference_id'] ?? null) !== $order->id ||
            ($remote['total_amount']['currency'] ?? null) !== $order->currency ||
            ! isset($remote['total_amount']['amount']) ||
            (int) round((float) $remote['total_amount']['amount'] * 100) !== (int) round((float) $order->amount * 100) ||
            ! is_string($remote['status'] ?? null)) {
            throw new RuntimeException('Tamara order does not match the stored payment.');
        }

        return $remote;
    }
}
