<?php

namespace App\Services\Payments;

class TabbyPayment implements PaymentInterface
{
    public function pay(array $data): array
    {
        return [
            'status' => 'failed',
            'message' => 'Tabby payments are not configured yet. Please choose another payment method.',
        ];
    }

    public function verify(array $data): bool
    {
        return false;
    }
}
