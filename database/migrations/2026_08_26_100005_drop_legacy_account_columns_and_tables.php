<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Final step: every row that had an old *_account_id now has the
     * matching social_account_id populated by backfill_social_accounts_data
     * (verified: zero NULLs across all nine child tables before writing
     * this migration), so the new column can be made required and the old
     * one dropped. post_accounts/ad_accounts are gone entirely;
     * message_channels survives as a trimmed child of social_accounts.
     *
     * Each block is guarded with hasColumn/hasTable so this migration is
     * safe to resume after a partial failure - MySQL DDL isn't
     * transactional, so an error partway through (this one hit a real
     * ordering bug on its first run: the FK on message_channels.user_id had
     * to be dropped before the composite unique index that supports it,
     * not after) leaves everything before it already applied.
     */
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'post_account_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropForeign(['post_account_id']);
                $table->dropColumn('post_account_id');
                $table->foreignId('social_account_id')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('post_media', 'post_account_id')) {
            Schema::table('post_media', function (Blueprint $table) {
                $table->dropForeign(['post_account_id']);
                $table->dropColumn('post_account_id');
                $table->foreignId('social_account_id')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('post_comments', 'post_account_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->dropForeign(['post_account_id']);
                $table->dropColumn('post_account_id');
                $table->foreignId('social_account_id')->nullable(false)->change();
            });
        }

        foreach (['ad_campaigns', 'ad_adgroups', 'ad_creatives', 'ad_media', 'ads'] as $tableName) {
            if (Schema::hasColumn($tableName, 'ad_account_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['ad_account_id']);
                    $table->dropColumn('ad_account_id');
                    $table->foreignId('social_account_id')->nullable(false)->change();
                });
            }
        }

        // platform_pages.ad_account_id was nullable (nullOnDelete) - keep
        // social_account_id nullable to match, don't force it required.
        if (Schema::hasColumn('platform_pages', 'ad_account_id')) {
            Schema::table('platform_pages', function (Blueprint $table) {
                $table->dropForeign(['ad_account_id']);
                $table->dropColumn('ad_account_id');
            });
        }

        if (Schema::hasColumn('conversations', 'message_channel_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['message_channel_id']);
                $table->dropUnique(['message_channel_id', 'customer_external_id']);
                $table->dropColumn('message_channel_id');
                $table->foreignId('social_account_id')->nullable(false)->change();
                $table->unique(['social_account_id', 'customer_external_id']);
            });
        }

        if (Schema::hasColumn('message_channels', 'user_id')) {
            Schema::table('message_channels', function (Blueprint $table) {
                // The FK on user_id must go before the composite unique index -
                // MySQL refuses to drop an index while it's still the
                // supporting index for a foreign key constraint.
                $table->dropForeign(['user_id']);
                $table->dropUnique(['user_id', 'platform', 'external_id']);
                $table->dropColumn(['user_id', 'name', 'username', 'access_token', 'refresh_token', 'status']);
                $table->foreignId('social_account_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('post_accounts');
        Schema::dropIfExists('ad_accounts');
    }

    public function down(): void
    {
        // Irreversible by design: the old tables' data now lives merged
        // inside social_accounts, and reconstructing the original
        // post_accounts/ad_accounts split from a merged row is lossy.
        // Restore from a backup taken before migrating if this needs undoing.
        throw new \RuntimeException('This migration cannot be reversed. Restore from a database backup instead.');
    }
};
