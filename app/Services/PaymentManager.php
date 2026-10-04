<?php

namespace App\Services;

use App\Services\Payments\CardPayment;
use App\Services\Payments\CashbackPayment;
use App\Services\Payments\InstallmentPayment;
use App\Services\Payments\WalletPayment;
use InvalidArgumentException;

class PaymentManager
{
    public function driver(string $method)
    {

        return match ($method) {
            'wallet' => app(WalletPayment::class),
            'card' => app(CardPayment::class),
            'cashback' => app(CashbackPayment::class),
            'installment' => app(InstallmentPayment::class),

            default => throw new InvalidArgumentException(
                "Unsupported payment method: {$method}"
            ),
        };
    }
}
