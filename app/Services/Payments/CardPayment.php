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


        $gateway     = $data['gateway'] ?? 'tap';
      //  $transaction = $data['transaction'];
      //  $transaction->update(['payment_method' => 'card', 'payment_gateway' => $gateway]);



        return $this->gatewayManager
            ->driver($gateway)
            ->pay($data);
    }

    public function verify(array $data): bool
    {
        $gateway = $data['gateway'] ?? 'tap';

        return $this->gatewayManager
            ->driver($gateway)
            ->verify($data);
    }
}
