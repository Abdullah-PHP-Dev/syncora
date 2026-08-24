<?php

namespace App\Services\Payments;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Exception;

class WalletPayment implements PaymentInterface
{
    public function pay(array $data): array
    {
        $user        = $data['user'];
        $amount      = $data['amount'];
        $plan        = $data['bundle'] ?? null;
        $cycle       = $data['cycle'];
        $type        = $data['type'];
        $transaction = $data['transaction'];
        $transaction->update(['payment_method' => 'wallet']);

        $wallet = Wallet::where('seller_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $transaction->update(['status' => 'rejected']);
            throw new Exception('Wallet not found');
        }

        if ($user->wallet_balance < $amount) {
            $msg = 'Insufficient wallet balance';
           // $transaction->delete();
            $transaction->update(['status' => 'rejected', 'remarks' => $msg]);
            return [
                'status' => 'failed',
                'message' => $msg
            ];
        }

        // 1. Deduct from wallet table
        $wallet->decrement('available_balance', $amount);

        // 2. Deduct from user table (keep sync)
        $user->decrement('wallet_balance', $amount);

        // 3. Create transaction log

        $transaction->update(['status' => 'completed']);

        return [
            'status' => 'paid',
            'transaction_id' => $transaction->transaction_no,
            'id' => $transaction->id,
        ];
    }

    public function verify(array $data): bool
    {
        return true;
    }
}
