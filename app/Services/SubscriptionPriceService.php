<?php

namespace App\Services;

class SubscriptionPriceService
{
    public function vatRate(): float
    {
        return round(max(0, min(100, (float) config('subscriptions.vat_rate', 15))), 2);
    }

    public function breakdown(int|float|string $inclusivePrice, int|float|string $discount = 0): array
    {
        $subtotal = max(0, CouponService::minor($inclusivePrice));
        $discount = min($subtotal, max(0, CouponService::minor($discount)));
        $total = $subtotal - $discount;
        $rate = (int) round($this->vatRate() * 100);
        $denominator = 10000 + $rate;
        $vat = intdiv($total * $rate + intdiv($denominator, 2), $denominator);

        return [
            'subtotal' => $subtotal / 100, 'discount' => $discount / 100,
            'net_amount' => ($total - $vat) / 100, 'vat' => $vat / 100,
            'vat_rate' => $this->vatRate(), 'prices_include_vat' => true, 'total' => $total / 100,
        ];
    }
}
