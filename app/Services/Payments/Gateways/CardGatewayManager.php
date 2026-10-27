<?php

namespace App\Services\Payments\Gateways;

use InvalidArgumentException;

class CardGatewayManager
{
    public function selectedGateway(): string
    {
        $gateway = adminSetting('current_payment_gateway', 'tap');

        if (! is_string($gateway) || ! in_array($gateway, ['tap', 'clickpay', 'noon'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => __('Unsupported card payment gateway configuration.'),
            ]);
        }

        return $gateway;
    }

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
