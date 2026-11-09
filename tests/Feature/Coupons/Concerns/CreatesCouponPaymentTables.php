<?php

namespace Tests\Feature\Coupons\Concerns;

trait CreatesCouponPaymentTables
{
    protected function createCouponPaymentTables(): void
    {
        (require database_path('migrations/2026_10_18_150000_create_wallet_transactions_table.php'))->up();
        (require database_path('migrations/2026_10_18_160000_create_subscription_coupons.php'))->up();
        // The plan picker lists each plan's features from the admin catalog.
        if (! \Illuminate\Support\Facades\Schema::hasTable('package_features')) {
            \Illuminate\Support\Facades\Schema::create('package_features', function ($table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name_en');
                $table->string('name_ar')->nullable();
                $table->text('description')->nullable();
                $table->string('type', 20);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }
}
