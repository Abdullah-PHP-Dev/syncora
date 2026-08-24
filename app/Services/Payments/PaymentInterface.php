<?php

namespace App\Services\Payments;

interface PaymentInterface
{
    public function pay(array $data): array;

    public function verify(array $data): bool;
}
