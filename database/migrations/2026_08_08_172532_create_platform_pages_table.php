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
        Schema::create('platform_pages', function (Blueprint $table) {
            $table->id();
            // Which platform's OAuth flow discovered this page - lets the
            // same table back Facebook Pages today and Instagram/TikTok/X/
            // LinkedIn equivalents later without a separate table per platform.
            $table->string('platform');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->nullable()->constrained('ad_accounts')->nullOnDelete();
            $table->string('page_id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('link')->nullable();
            $table->unsignedBigInteger('likes_count')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->string('business_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('picture')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'page_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_pages');
    }
};
