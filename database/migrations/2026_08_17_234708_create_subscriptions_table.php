<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('subscriptions', function (Blueprint $table) {
			$table->id();

			$table->foreignId('user_id')
				->constrained('users')
				->cascadeOnDelete();

			$table->foreignId('bundle_id')
				->default(1)
				->constrained('bundles')
				->cascadeOnDelete();

			$table->integer('billing_period')->default(1);

			$table->string('bundle_name');

			$table->date('start_date')->nullable();
			$table->date('end_date')->nullable();

			$table->string('status')->default('trial');

			$table->boolean('is_active')->default(true);

			$table->timestamps();

			$table->index(['user_id', 'is_active']);
			$table->index('status');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('subscriptions');
	}
};