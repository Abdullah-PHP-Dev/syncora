<?php

namespace App\Services\Payments\Gateways;

use InvalidArgumentException;

class CardGatewayManager
{
    public function driver(string $gateway)
    {
        return match ($gateway) {

            'tap'      => app(TapGateway::class),
            'clickpay' => app(ClickPayGateway::class),
            'noon'     => app(NoonGateway::class),

            default => throw new InvalidArgumentException(
                "Unsupported card gateway: {$gateway}"
            ),
        };
    }
}
