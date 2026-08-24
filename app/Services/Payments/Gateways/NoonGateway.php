<?php


class NoonGateway implements CardGatewayInterface
{
    public function pay(array $data): array
    {
        return [
            'status' => 'pending',
            'redirect_url' => 'https://noon-url',
            'transaction_id' => uniqid('noon_'),
        ];
    }

    public function verify(array $data): bool
    {
        return true;
    }
}
