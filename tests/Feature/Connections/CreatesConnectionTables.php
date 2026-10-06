<?php

namespace Tests\Feature\Connections;

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
}
