<?php

namespace Tests\Feature\EmailMarketing\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same technique TeamWorkspaceTest uses - require specific migration files
 * by hand rather than RefreshDatabase (which shells to migrate:fresh,
 * booting the console kernel and every command's constructor before the
 * schema exists - breaks the moment any of them injects a service that
 * calls adminSetting() in its own constructor, as several EmailMarketing
 * services do). Requires in FK dependency order.
 */
trait CreatesEmailMarketingTables
{
    protected function createEmailMarketingTables(): void
    {
        if (Schema::hasTable('admin_settings')) {
            return;
        }

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        foreach ([
            '0001_01_01_000000_create_users_table',
            // The shared admin layout (navbar/sidebar) calls hasRole() on
            // the authenticated user - needed even though these tests
            // aren't about roles, or rendering any full-layout view 500s.
            '2026_06_13_213124_create_permission_tables',
            '2026_09_18_000001_create_email_subaccounts_table',
            '2026_09_18_000002_create_verified_domains_table',
            '2026_09_18_000003_create_domain_dns_records_table',
            '2026_09_18_000004_create_sender_identities_table',
            '2026_09_18_000005_create_email_segments_table',
            '2026_07_31_220836_create_email_lists_table',
            '2026_07_31_220837_create_email_subscribers_table',
            '2026_07_31_220838_create_email_list_subscriber_table',
            '2026_07_31_220839_create_email_templates_table',
            '2026_09_18_000006_create_email_template_versions_table',
            '2026_09_18_000007_add_sendgrid_columns_to_email_lists_table',
            '2026_09_18_000008_add_sendgrid_columns_to_email_subscribers_table',
            '2026_09_18_000009_add_sendgrid_columns_to_email_templates_table',
            '2026_07_31_220840_create_email_campaigns_table',
            '2026_09_18_000010_add_sendgrid_columns_to_email_campaigns_table',
            '2026_07_31_220841_create_email_campaign_sends_table',
            '2026_09_18_000011_create_email_events_table',
            '2026_09_18_000012_add_webhook_public_key_to_email_subaccounts_table',
            '2026_09_18_000013_make_email_list_id_nullable_on_email_campaigns_table',
            '2026_09_18_223326_add_source_to_email_subscribers_table',
            '2026_09_19_111946_add_campaign_type_to_email_campaigns_table',
            '2026_09_19_113707_add_suppression_group_id_to_email_campaigns_table',
            '2026_09_20_190804_add_dns_last_synced_at_to_verified_domains_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }
}
