<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Added nullable alongside the old post_account_id so the data-backfill
     * migration can populate it before drop_legacy_account_columns_and_tables
     * removes the old column and makes this one required.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('post_account_id')
                ->constrained('social_accounts')->cascadeOnDelete();
        });

        Schema::table('post_media', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('post_account_id')
                ->constrained('social_accounts')->cascadeOnDelete();
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('post_account_id')
                ->constrained('social_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
        });

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
        });

        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
        });
    }
};
