<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
	    Schema::create('seller_bundles', function (Blueprint $table) {

		    $table->id();

		    $table->foreignId('user_id')
			    ->constrained()
			    ->cascadeOnDelete();

		    $table->foreignId('package_id')
			    ->constrained()
			    ->cascadeOnDelete();

		    $table->decimal('price',10,2);

		    $table->string('currency')->default('SAR');

		    $table->enum('status',[
			    'pending',
			    'active',
			    'expired',
			    'cancelled'
		    ])->default('pending');

		    $table->enum('payment_status',[
			    'pending',
			    'paid',
			    'failed',
			    'refunded'
		    ])->default('pending');

		    $table->timestamp('starts_at')->nullable();

		    $table->timestamp('expires_at')->nullable();

		    $table->string('payment_gateway')->nullable();

		    $table->string('transaction_id')->nullable();

		    $table->boolean('auto_renew')->default(false);

		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_bundles');
    }
};
