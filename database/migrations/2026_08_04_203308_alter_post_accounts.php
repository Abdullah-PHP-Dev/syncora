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
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->bigInteger('media_count')->nullable()->default(0);
            $table->bigInteger('following_count')->nullable()->default(0);
            $table->bigInteger('likes_count')->nullable()->default(0);
            $table->bigInteger('follower_count')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->dropColumn(['media_count', 'following_count', 'likes_count', 'follower_count']);
        });
    }
};
