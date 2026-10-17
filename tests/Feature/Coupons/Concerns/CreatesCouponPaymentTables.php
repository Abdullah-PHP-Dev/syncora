<?php

namespace Tests\Feature\Coupons\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesCouponPaymentTables
{
    protected function createCouponPaymentTables(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('transaction_no')->nullable();
            $t->unsignedBigInteger('seller_id');
            $t->decimal('amount', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('vat', 12, 2)->default(0);
            $t->unsignedBigInteger('coupon_id')->nullable();
            $t->string('direction');
            $t->string('category');
            $t->string('status');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('callback')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_gateway')->nullable();
            $t->string('gateway_reference')->nullable();
            $t->text('remarks')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_18_160000_create_subscription_coupons.php'))->up();
    }
}
