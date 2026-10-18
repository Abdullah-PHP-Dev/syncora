<?php

namespace Tests\Feature\Coupons\Concerns;

trait CreatesCouponPaymentTables
{
    protected function createCouponPaymentTables(): void
    {
        (require database_path('migrations/2026_10_18_150000_create_wallet_transactions_table.php'))->up();
        (require database_path('migrations/2026_10_18_160000_create_subscription_coupons.php'))->up();
    }
}
