<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content-posting-side profile statistics, split out of social_accounts
     * so a Facebook Ad Account row (which has no followers, no media) no
     * longer sits in the same flat table/columns as a Facebook Page row
     * (which has no ad spend, no currency). One row per social_accounts
     * row that has has_posting_permission - never populated for a
     * pure ad-account row.
     */
    public function up(): void
    {
        Schema::create('social_account_post_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->unique()->constrained('social_accounts')->cascadeOnDelete();

            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('subscribers_count')->nullable();
            $table->unsignedBigInteger('likes_count')->nullable();
            $table->unsignedBigInteger('views_count')->nullable();
            $table->unsignedBigInteger('impressions_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_post_details');
    }
};
