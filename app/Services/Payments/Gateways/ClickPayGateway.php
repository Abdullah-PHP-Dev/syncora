<?php

namespace App\Services\Payments\Gateways;

use App\Services\CouponService;
use App\Services\Payments\PaymentCallbackUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClickPayGateway implements CardGatewayInterface
{
    private function client(): PendingRequest
    {
        $key = config('services.payment.clickpay.secret_key');
        if (! $key || ! config('services.payment.clickpay.merchant_id')) {
            throw new RuntimeException('ClickPay credentials are not configured.');
        }

        return Http::baseUrl(rtrim(config('services.payment.clickpay.base_url'), '/'))
            ->withHeaders(['Authorization' => $key])->acceptJson()->asJson()->connectTimeout(10)->timeout(30);
    }

    public function pay(array $data): array
    {
        if (! config('services.payment.clickpay.secret_key') || ! config('services.payment.clickpay.merchant_id')) {
            return ['status' => 'failed', 'message' => __('ClickPay payments are not configured. Please choose another payment method.')];
        }
        $transaction = $data['transaction'];
        $response = $this->client()->post('/payment/request', [
            'profile_id' => config('services.payment.clickpay.merchant_id'),
            'tran_type' => 'sale', 'tran_class' => 'ecom',
            'cart_id' => $transaction->transaction_no,
            'cart_description' => $data['bundle']->name.' / '.$data['cycle'],
            'cart_currency' => $transaction->meta['currency'] ?? $data['bundle']->currency,
            'cart_amount' => round((float) $data['amount'], 2),
            'callback' => PaymentCallbackUrl::forTransaction($transaction, true),
            'return' => PaymentCallbackUrl::forTransaction($transaction),
            'return_using_get' => true,
        ]);
        if (! $response->successful()) {
            if ($response->serverError() || $response->status() === 429) {
                $response->throw();
            }

            return ['status' => 'failed', 'message' => __('Payment gateway could not create checkout.')];
        }
        $payment = $response->json();
        if (! is_string($payment['tran_ref'] ?? null) || $payment['tran_ref'] === '') {
            throw new RuntimeException('ClickPay did not return a transaction reference.');
        }
        $transaction->update(['gateway_reference' => $payment['tran_ref']]);

        return ['status' => 'pending', 'redirect_url' => $payment['redirect_url'] ?? PaymentCallbackUrl::forTransaction($transaction),
            'session_id' => $payment['tran_ref']];
    }

    public function status(array $data): string
    {
        $transaction = $data['transaction'];



        if (! $transaction->gateway_reference) {
            return 'pending';
        }
        $payment = $this->client()->post('/payment/query', [
            'profile_id' => config('services.payment.clickpay.merchant_id'),
            'tran_ref' => $transaction->gateway_reference,
        ])->throw()->json();





        if (($payment['tran_ref'] ?? null) !== $transaction->gateway_reference ||
            ($payment['cart_id'] ?? null) !== $transaction->transaction_no ||
            ($payment['cart_currency'] ?? null) !== ($transaction->meta['currency'] ?? null) ||
            strtolower($payment['tran_type'] ?? '') !== 'sale' ||
            ! is_numeric($payment['cart_amount'] ?? null) ||
            CouponService::minor($payment['cart_amount']) !== CouponService::minor($transaction->amount)) {
            throw new RuntimeException('ClickPay transaction does not match the stored payment.');
        }

        return match ($payment['payment_result']['response_status'] ?? null) {
            'A' => 'paid',
            'D', 'E', 'V', 'X' => 'failed',
            default => 'pending',
        };
    }

    public function verify(array $data): bool
    {
        return isset($data['transaction']) && $this->status($data) === 'paid';
    }
}
