<?php

namespace App\Services\Payments\Gateways;

use App\Services\Payments\TamaraPayment;
use App\Services\Payments\TabbyPayment;
use App\Services\Payments\PaymentInterface;
use InvalidArgumentException;

class InstallmentGatewayManager
{
    public function driver(string $gateway): PaymentInterface
    {
        return match ($gateway) {

            'tamara'      => app(TamaraPayment::class),
            'tabby' => app(TabbyPayment::class),


            default => throw new InvalidArgumentException(
                "Unsupported installment gateway: {$gateway}"
            ),
        };
    }
}
