<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * followers_count/subscribers_count/likes_count already existed, but
     * several platform syncAccountStats() methods (most critically
     * YoutubePostService, a straight carry-over from the old post_accounts
     * schema) already fetch views/impressions/following/media counts at
     * connect time and on refresh - they just had nowhere real to put them
     * after the social_accounts consolidation, so those writes were either
     * silently dropped (non-fillable columns) or buried in the metadata
     * JSON blob instead of being queryable like the other counters.
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->nullable()->after('likes_count');
            $table->unsignedBigInteger('impressions_count')->nullable()->after('views_count');
            $table->unsignedBigInteger('following_count')->nullable()->after('impressions_count');
            $table->unsignedBigInteger('media_count')->nullable()->after('following_count');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'impressions_count', 'following_count', 'media_count']);
        });
    }
};
