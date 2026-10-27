<?php

namespace App\Services\Payments\Gateways;

class NoonGateway implements CardGatewayInterface
{
    public function pay(array $data): array
    {
        return [
            'status' => 'failed',
            'message' => __('Noon payments are not configured yet. Please choose another payment method.'),
        ];
    }

    public function verify(array $data): bool
    {
        return false;
    }
}
