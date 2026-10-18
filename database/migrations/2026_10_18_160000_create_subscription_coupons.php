<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type', 20);
                $table->decimal('value', 12, 2);
                $table->string('currency', 3);
                $table->decimal('minimum_amount', 12, 2)->default(0);
                $table->decimal('maximum_discount', 12, 2)->nullable();
                $table->decimal('discount_budget', 14, 2)->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('per_user_limit')->nullable()->default(1);
                $table->unsignedBigInteger('claimed_count')->default(0);
                $table->decimal('claimed_discount', 14, 2)->default(0);
                $table->json('bundle_ids')->nullable();
                $table->json('seller_ids')->nullable();
                $table->json('cycles');
                $table->json('payment_methods')->nullable();
                $table->boolean('first_purchase_only')->default(false);
                $table->boolean('allow_yearly_discount')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('wallet_transaction_id')->unique();
                $table->unsignedBigInteger('bundle_id');
                $table->string('code', 50);
                $table->string('cycle', 10);
                $table->string('currency', 3);
                $table->decimal('subtotal', 12, 2);
                $table->decimal('discount', 12, 2);
                $table->decimal('total', 12, 2);
                $table->string('status', 20)->default('reserved');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->index(['coupon_id', 'status']);
                $table->index(['coupon_id', 'user_id', 'status']);
            });
        }
        if (! Schema::hasTable('coupon_changes')) {
            Schema::create('coupon_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('before')->nullable();
                $table->json('after');
                $table->timestamps();
            });
        }
        // wallet_transactions itself: 2026_10_18_150000. Guarded like the
        // creates above so a run that failed half-way (MySQL can't roll DDL
        // back) can simply be re-run.
        if (! Schema::hasColumn('wallet_transactions', 'checkout_key')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->uuid('checkout_key')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->unique(['seller_id', 'checkout_key']);
            });
        }

    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique(['seller_id', 'checkout_key']);
            $table->dropColumn(['checkout_key', 'fulfilled_at']);
        });
        Schema::dropIfExists('coupon_changes');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
