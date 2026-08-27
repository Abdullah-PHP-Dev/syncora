<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The 7 posting-profile stat columns now live on
     * social_account_post_details (previous migration backfilled them).
     * category/account_type and metadata stay on social_accounts - they're
     * cheap identity/classification fields, not statistics, and nothing
     * outside this table currently reads them, so splitting them out would
     * add risk with no benefit.
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'followers_count', 'subscribers_count', 'likes_count',
                'views_count', 'impressions_count', 'following_count', 'media_count',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('subscribers_count')->nullable();
            $table->unsignedBigInteger('likes_count')->nullable();
            $table->unsignedBigInteger('views_count')->nullable();
            $table->unsignedBigInteger('impressions_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
        });
    }
};
