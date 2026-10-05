<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\InstallmentGatewayManager;

class InstallmentPayment implements PaymentInterface
{
    public function __construct(
        private InstallmentGatewayManager $gatewayManager
    ) {}

    public function pay(array $data): array
    {


        $gateway = $data['gateway'] ?? '';
        $driver = $this->gatewayManager->driver($gateway);
      $transaction = $data['transaction'];
       $transaction->update(['payment_method' => 'installment', 'payment_gateway' => $gateway]);



        return $driver->pay($data);
    }

    public function verify(array $data): bool
    {
        $gateway = $data['gateway'] ?? '';

        return $this->gatewayManager
            ->driver($gateway)
            ->verify($data);
    }
}
