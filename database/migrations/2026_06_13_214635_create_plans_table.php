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
	    Schema::create('plans', function (Blueprint $table) {

		    $table->id();

		    $table->string('name');

		    $table->decimal('price',10,2);

		    $table->integer('ads_limit');

		    $table->integer('featured_ads');

		    $table->boolean('chat_enabled')
			    ->default(true);

		    $table->integer('duration_days');

		    $table->timestamps();
	    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
