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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->nullable();
            $table->string('post_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('post_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('post_url')->nullable();
            $table->text('content')->nullable();
            // Engagement Metrics
            $table->integer('likes')->nullable()->default(0);
            $table->integer('shares')->nullable()->default(0);
            $table->integer('comments')->nullable()->default(0);
            $table->integer('saves')->nullable()->default(0);
            $table->integer('views')->nullable()->default(0);
            $table->integer('clicks')->nullable()->default(0);
            $table->integer('reach')->nullable()->default(0);
            $table->integer('impressions')->nullable()->default(0);
            $table->float('engagement_rate')->nullable()->default(0);
            $table->float('performance_score')->nullable()->default(0);
            $table->string('visibility')->nullable();
            $table->boolean('schedule_mode')->nullable();
            $table->datetime('schedule_at')->nullable();
            $table->boolean('expiry_mode')->nullable();
            $table->datetime('expiry_at')->nullable();
            $table->string('status')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
