<?php


namespace App\Services\Payments\Gateways;

abstract class BaseGateway implements CardGatewayInterface
{
    protected function callbackUrl(): string
    {
        return route('payment.callback');
    }
    protected function prepareData(array $data): array
    {
        $customer = $this->customerDetail($data);
        $transaction = $this->transDetail($data);

        $return = [
            'customerInfo' => $customer,
            'transaction'  => $transaction,
        ];
        return $return;
    }

    protected function customerDetail(array $data): array
    {
        $transaction = $data['transaction'];
        $shopDetail  = $transaction->shopInfo;
        return [
            'name'      => $shopDetail->name,
            'email'     => $transaction->seller->email,
            "mobile"    => $transaction->seller->mobile ?? '',
            "save_card" => 0
        ];
    }

    protected function transDetail(array $data): array
    {
        $transaction    = $data['transaction'];
        $vatRate        = 0.5; // getConfig('vat');
        $baseAmount     = round($data['amount'], 2);
        $isSubscription = $data['type'] === 'subscription';
        $charges        = $isSubscription ? 0 : round($baseAmount * 2.2 / 100, 2);
        $vat            = $isSubscription ? 0 : round($charges * $vatRate, 2);

        return [
            'id'          => $transaction->id,
            'ref_number'  => $transaction->transaction_no,
            'base_amount' => $baseAmount,
            'charges'     => $charges,
            'vat'         => $vat,
            'total'       => round($baseAmount + $charges + $vat, 2),
        ];
    }
}
