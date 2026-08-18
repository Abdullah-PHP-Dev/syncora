<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('bundles', function (Blueprint $table) {
			$table->id();

			$table->string('name_en');
			$table->string('name_ar');
			$table->string('slug')->nullable()->unique();

			$table->decimal('price', 10, 2)->default(0);
			$table->decimal('discount_price', 10, 2)->nullable();

			$table->string('currency', 10)->default('SAR');

			$table->boolean('is_default')->default(false);
			$table->boolean('is_free')->default(false);
			$table->boolean('is_popular')->default(false);
			$table->boolean('is_active')->default(true);

			$table->unsignedInteger('version')->default(1);
			$table->unsignedInteger('sort_order')->default(0);

			$table->boolean('allow_integration')->default(false);

			$table->json('features')->nullable();
			$table->json('allowed_categories')->nullable();
			$table->json('allowed_product')->nullable();
			$table->json('limits')->nullable();
			$table->json('meta')->nullable();

			$table->timestamps();

			$table->index(['is_default', 'is_active']);
			$table->index('is_free');
			$table->index('is_popular');
			$table->index('sort_order');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('bundles');
	}
};