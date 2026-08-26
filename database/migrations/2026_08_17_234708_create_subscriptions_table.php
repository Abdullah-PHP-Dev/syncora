<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		// Redesign of the original plan_id/starts_at/expires_at subscriptions
		// table (2026_06_13_214641_create_subscriptions_table) into this
		// bundle-based schema. On every environment migrated so far, the old
		// table was dropped by hand before this ran - nothing in the app
		// reads plan_id/starts_at off `subscriptions` any more (SellerBundle
		// is the model still using those column names, an unrelated table).
		// Doing the drop here instead makes a genuine fresh install
		// (`migrate:fresh`) reproduce that same transition without manual
		// intervention; this has no effect on already-migrated databases
		// since a migration only runs once.
		Schema::dropIfExists('subscriptions');

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