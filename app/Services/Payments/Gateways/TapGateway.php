<?php

namespace App\Services\Payments\Gateways;

use App\Services\CouponService;
use App\Services\Payments\PaymentCallbackUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TapGateway extends BaseGateway
{
    private function client(): PendingRequest
    {
        $key = config('services.payment.tap.secret_key');
        if (! $key) {
            throw new RuntimeException('Tap credentials are not configured.');
        }

        return Http::baseUrl(rtrim(config('services.payment.tap.base_url'), '/'))
            ->withToken($key)->acceptJson()->asJson()->connectTimeout(10)->timeout(30);
    }

    public function pay(array $data): array
    {
        if (! config('services.payment.tap.secret_key')) {
            return ['status' => 'failed', 'message' => __('Tap payments are not configured. Please choose another payment method.')];
        }
        $transaction = $data['transaction'];
        $returnUrl = PaymentCallbackUrl::forTransaction($transaction);
        $response = $this->client()->post('/charges', [
            'amount' => round((float) $data['amount'], 2),
            'currency' => $transaction->meta['currency'] ?? $data['bundle']->currency,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $data['bundle']->name.' / '.$data['cycle'],
            'reference' => ['transaction' => $transaction->transaction_no, 'order' => $transaction->transaction_no],
            'customer' => ['first_name' => $data['user']->name, 'email' => $data['user']->email],
            'source' => ['id' => 'src_all'],
            'redirect' => ['url' => $returnUrl],
            'post' => ['url' => PaymentCallbackUrl::forTransaction($transaction, true)],
        ]);

        if (! $response->successful()) {
            // Server errors may follow a created charge; keep the transaction pending.
            if ($response->serverError() || $response->status() === 429) {
                $response->throw();
            }

            return ['status' => 'failed', 'message' => __('Payment gateway could not create checkout.')];
        }

        $charge = $response->json();
        if (! is_string($charge['id'] ?? null) || $charge['id'] === '') {
            throw new RuntimeException('Tap did not return a charge reference.');
        }
        $transaction->update(['gateway_reference' => $charge['id']]);

        return ['status' => 'pending', 'redirect_url' => $charge['transaction']['url'] ?? $returnUrl,
            'transaction_id' => $transaction->id, 'session_id' => $charge['id']];
    }

    public function status(array $data): string
    {
        $transaction = $data['transaction'];
        $reference = $transaction->gateway_reference;
        if (! is_string($reference) || $reference === '') {
            return 'pending';
        }
        $charge = $this->client()->get('/charges/'.rawurlencode($reference))->throw()->json();
        if (($charge['id'] ?? null) !== $reference ||
            ($charge['reference']['transaction'] ?? null) !== $transaction->transaction_no ||
            ($charge['currency'] ?? null) !== ($transaction->meta['currency'] ?? null) ||
            ! is_numeric($charge['amount'] ?? null) ||
            CouponService::minor($charge['amount']) !== CouponService::minor($transaction->amount)) {
            throw new RuntimeException('Tap charge does not match the stored payment.');
        }

        return match ($charge['status'] ?? null) {
            'CAPTURED' => 'paid',
            'ABANDONED', 'CANCELLED', 'FAILED', 'DECLINED', 'RESTRICTED', 'VOID', 'TIMEDOUT' => 'failed',
            default => 'pending',
        };
    }

    public function verify(array $data): bool
    {
        return isset($data['transaction']) && $this->status($data) === 'paid';
    }
}
