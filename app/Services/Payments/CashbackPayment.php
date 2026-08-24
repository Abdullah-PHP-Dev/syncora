<?php

namespace App\Services\Payments;

use Exception;

class CashbackPayment implements PaymentInterface
{
    public function pay(array $data): array
    {
        $user   = $data['user'];
        $amount = $data['amount'];

        // assuming you store cashback in wallet-like field
        $cashbackBalance = $user->cashback_balance ?? 0;

        if ($cashbackBalance < $amount) {
            throw new Exception('Insufficient cashback balance.');
        }

        // deduct cashback
        $user->decrement('cashback_balance', $amount);

        // optional: log transaction (recommended)
        // CashbackTransaction::create([...]);

        return [
            'status' => 'paid',
            'method' => 'cashback',
            'transaction_id' => uniqid('cashback_'),
        ];
    }

    public function verify(array $data): bool
    {
        // cashback is internal system payment → always valid if deducted
        return true;
    }
}
