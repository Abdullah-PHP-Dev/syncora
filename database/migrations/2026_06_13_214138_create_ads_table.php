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
	    Schema::create('ads', function (Blueprint $table) {

		    $table->id();

		    $table->foreignId('user_id')->constrained();

		    $table->foreignId('category_id')->constrained();

		    $table->string('title');

		    $table->string('slug')->unique();

		    $table->longText('description');

		    $table->decimal('price',12,2)->default(0);

		    $table->boolean('is_featured')->default(false);

		    $table->timestamp('featured_until')->nullable();

		    $table->timestamp('published_at')->nullable();

		    $table->timestamp('expires_at')->nullable();

		    $table->enum('status',[
			    'draft',
			    'pending',
			    'approved',
			    'rejected',
			    'sold',
			    'expired'
		    ])->default('pending');

		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
