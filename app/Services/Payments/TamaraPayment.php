<?php

namespace App\Services\Payments;

class TamaraPayment implements PaymentInterface
{
    public function pay(array $data): array
    {
        return [
            'status' => 'pending',
            'redirect_url' => route('subscription.payment.redirect'),
            'transaction_id' => uniqid('card_'),
        ];
    }

    public function verify(array $data): bool
    {
        return true;
    }
}
