<?php

namespace Tests\Feature\Connections;

use Illuminate\Support\Facades\Schema;

/**
 * Runs only the migrations the Connection Hub touches - the full set
 * doesn't run on SQLite (same approach as the other feature tests here).
 */
trait CreatesConnectionTables
{
    protected function createConnectionTables(): void
    {
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
            '2026_08_28_100001_create_social_account_ad_details_table.php',
            '2026_10_07_100000_add_asset_and_user_tokens_to_social_accounts_table.php',
            '2026_10_07_110000_create_social_connections_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
    }

    /** admin_settings, so adminSetting() can be set via Settings::set(). */
    protected function createSettingsTable(): void
    {
        Schema::create('admin_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    /** Minimal shape the Meta connect writes its Messenger/Instagram channels to. */
    protected function createMessageChannelsTable(): void
    {
        Schema::create('message_channels', function ($table) {
            $table->id();
            $table->foreignId('social_account_id')->nullable();
            $table->string('platform');
            $table->string('external_id');
            $table->string('verify_token')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('webhook_subscribed')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
