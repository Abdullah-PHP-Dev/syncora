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
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('media_id')->nullable();
            $table->string('media_url')->nullable();
            $table->text('media_type')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('alt_text')->nullable();
            $table->integer('sort_order')->nullable()->default(0);
            $table->json('platform_metadata')->nullable()->default('{}');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
