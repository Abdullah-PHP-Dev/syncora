<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\CardGatewayManager;

class CardPayment implements PaymentInterface
{
    public function __construct(
        private CardGatewayManager $gatewayManager
    ) {}

    public function pay(array $data): array
    {
        $transaction = $data['transaction'];
        $gateway = $transaction->payment_gateway ?? $data['gateway'] ?? $this->gatewayManager->selectedGateway();
        $data['gateway'] = $gateway;
        $transaction->update(['payment_method' => 'card', 'payment_gateway' => $gateway]);

        return $this->gatewayManager
            ->driver($gateway)
            ->pay($data);
    }

    public function verify(array $data): bool
    {
        // Existing payments must keep their original provider after settings change.
        $gateway = ($data['transaction']->payment_gateway ?? null) ?? $data['gateway'] ?? 'tap';
        $data['gateway'] = $gateway;

        return $this->gatewayManager
            ->driver($gateway)
            ->verify($data);
    }
}
