<?php

namespace App\Services\Payments;

use App\Models\WalletTransaction;
use Illuminate\Support\Str;

class PaymentCallbackUrl
{
    public static function forTransaction(WalletTransaction $transaction, bool $notification = false): string
    {
        $meta = $transaction->fresh()->meta ?? [];
        if (empty($meta['callback_token'])) {
            $meta['callback_token'] = Str::random(64);
            $transaction->update(['meta' => $meta]);
        }

        return route('payment.callback', [
            'transaction' => $transaction->id,
            'token' => $meta['callback_token'],
            'notification' => $notification ? 1 : 0,
        ]);
    }
}
