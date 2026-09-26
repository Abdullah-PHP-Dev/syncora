<?php

namespace Tests\Feature\Support\Concerns;

use Illuminate\Support\Facades\Schema;

/**
 * Same technique CreatesEmailMarketingTables uses - require specific
 * migration files by hand rather than RefreshDatabase (which shells to
 * migrate:fresh, booting the console kernel and every command's
 * constructor before the schema exists). Covers both ticket systems
 * (Ticket/TicketMessage and SupportTicket/SupportTicketMessage) plus
 * Faq/FaqCategory and the Spatie permission tables every role-based check
 * in this test suite depends on.
 */
trait CreatesSupportModuleTables
{
    protected function createSupportModuleTables(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        foreach ([
            '0001_01_01_000000_create_users_table',
            '2026_06_13_213124_create_permission_tables',
            // These three predate this app's later convention of anonymous
            // migration classes, so a second test class in the same PHPUnit
            // process (each getting its own fresh :memory: connection) would
            // fatal on "class already declared" if required the same way -
            // guard by class name and instantiate directly instead.
            ['2026_06_13_214010_create_activity_log_table', 'CreateActivityLogTable'],
            ['2026_06_13_214011_add_event_column_to_activity_log_table', 'AddEventColumnToActivityLogTable'],
            ['2026_06_13_214012_add_batch_uuid_column_to_activity_log_table', 'AddBatchUuidColumnToActivityLogTable'],
            '2026_09_05_143251_create_faq_categories_table',
            '2026_09_05_143252_create_faqs_table',
            '2026_09_05_173244_add_embedding_to_faqs_table',
            '2026_09_05_143253_create_tickets_table',
            '2026_09_05_143254_create_ticket_messages_table',
            '2026_09_17_000001_create_team_support_workspace',
        ] as $migration) {
            if (is_array($migration)) {
                [$file, $class] = $migration;
                if (!class_exists($class)) {
                    require database_path('migrations/' . $file . '.php');
                }
                (new $class())->up();

                continue;
            }

            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }
}
