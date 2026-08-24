<?php

namespace App\Services\Payments\Gateways;

interface CardGatewayInterface
{
    public function pay(array $data): array;

    public function verify(array $data): bool;
}
