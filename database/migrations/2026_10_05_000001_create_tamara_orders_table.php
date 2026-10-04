<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tamara_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bundle_id')->constrained()->restrictOnDelete();
            $table->string('bundle_name');
            $table->string('cycle');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('environment')->default('sandbox');
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('checkout_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('items');
            $table->string('capture_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tamara_orders');
    }
};
