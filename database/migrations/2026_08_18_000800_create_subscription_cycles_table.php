<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('subscription_cycles', function (Blueprint $table) {
			$table->id();

			$table->foreignId('subscription_id')
				->constrained('subscriptions')
				->cascadeOnDelete();

			$table->foreignId('user_id')
				->constrained('users')
				->cascadeOnDelete();

			$table->foreignId('bundle_id')
				->constrained('bundles');

			$table->date('start_date');
			$table->date('end_date');

			$table->string('type')->default('renewal');
			$table->string('status')->default('active');

			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('subscription_cycles');
	}
};