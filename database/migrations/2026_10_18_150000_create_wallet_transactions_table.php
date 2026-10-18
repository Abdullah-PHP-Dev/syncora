<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wallet_transactions predates this repo's migrations (it only exists on
 * databases restored from the legacy app), yet
 * 2026_10_18_160000_create_subscription_coupons alters it - so a fresh or
 * local database failed with "Base table or view not found". Creates it
 * from App\Models\WalletTransaction's columns only when it's missing; a
 * no-op wherever the legacy table already exists. checkout_key /
 * fulfilled_at are left to the coupons migration that introduced them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->nullable()->index();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('parent_transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('direction', 20);
            $table->string('category', 50);
            $table->string('status', 20)->default('pending');
            $table->decimal('available_after', 14, 2)->nullable();
            $table->decimal('pending_after', 14, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->string('card_brand')->nullable();
            $table->string('card_scheme')->nullable();
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('service_charges', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_cashback')->default(false);
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('callback')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    // Deliberately no drop: on legacy databases the table (and its money
    // records) existed before this migration, and rolling back must never
    // delete it.
    public function down(): void
    {
    }
};
