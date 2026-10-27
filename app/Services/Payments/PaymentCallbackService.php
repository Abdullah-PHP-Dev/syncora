<?php

namespace App\Services\Payments;

use App\Models\WalletTransaction;
use App\Services\Payments\Gateways\CardGatewayManager;
use App\Services\SubscriptionCheckoutService;
use RuntimeException;

class PaymentCallbackService
{
    public function synchronize(WalletTransaction $transaction): WalletTransaction
    {


        if ($transaction->fulfilled_at || $transaction->status === 'rejected') {
            return $transaction;
        }
        if ($transaction->status === 'completed') {
            app(SubscriptionCheckoutService::class)->fulfill($transaction);

            return $transaction->fresh();
        }
        $data = ['transaction' => $transaction, 'gateway_reference' => $transaction->gateway_reference];
        $provider = match (true) {
            $transaction->payment_method === 'card' && in_array($transaction->payment_gateway, ['tap', 'clickpay'], true) => app(CardGatewayManager::class)->driver($transaction->payment_gateway),
            $transaction->payment_method === 'installment' && $transaction->payment_gateway === 'tamara' => app(TamaraPayment::class),
            default => throw new RuntimeException('This payment provider is not configured for confirmation.'),
        };
        $status = $provider->status($data);


        if ($status === 'paid') {
            app(SubscriptionCheckoutService::class)->complete($transaction);
        } elseif ($status === 'failed') {
            app(SubscriptionCheckoutService::class)->fail($transaction, __('Your payment was not completed. Please try again.'));
        }

        return $transaction->fresh();
    }
}
