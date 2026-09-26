<?php

namespace Tests\Feature\AiCopilot\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same hand-migration technique as CreatesSupportModuleTables/
 * CreatesEmailMarketingTables - build a fresh :memory: schema instead of
 * RefreshDatabase. conversations/messages are hand-defined in their
 * CURRENT final shape (social_account_id, ai_paused_at already included)
 * rather than replaying the real historical migration chain: that chain
 * includes a raw multi-table MySQL DELETE (unique-index migration) and a
 * data-backfill migration reading from post_accounts/ad_accounts/
 * message_channels - neither runs on SQLite nor makes sense against an
 * empty test schema. Every other table here is a real, self-contained
 * migration file required as-is.
 */
trait CreatesAiCopilotTables
{
    protected function createAiCopilotTables(): void
    {
        if (Schema::hasTable('ai_copilot_settings')) {
            return;
        }

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_06_13_213124_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_08_26_100000_create_social_accounts_table.php'))->up();
        // SocialAccount appends followers_count/etc via ->postDetails - any
        // serialization of a SocialAccount (eg. ChatController::store()'s
        // JSON response) queries this table even when it's empty.
        (require database_path('migrations/2026_08_28_100000_create_social_account_post_details_table.php'))->up();

        // AiCopilotService::embed() bails out (empty($apiKey) check) before
        // ever calling Gemini unless adminSetting('gemini_api_key_free')
        // resolves to something truthy - Settings::all() itself degrades
        // gracefully to [] without this table (see its own docblock), but
        // that means every embed() call in these tests would silently
        // no-op instead of exercising (faked) Http calls at all.
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        DB::table('admin_settings')->insert(['key' => 'gemini_api_key_free', 'value' => 'test-key', 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('external_conversation_id')->nullable();
            $table->string('customer_external_id');
            $table->string('customer_name')->nullable();
            $table->text('customer_avatar_url')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('status')->default('open');
            $table->timestamp('ai_paused_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'customer_external_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id')->nullable();
            $table->string('direction');
            $table->string('sender_type');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'external_message_id']);
        });

        // Faq uses Spatie's LogsActivity trait - these three predate this
        // app's anonymous-migration-class convention, so (like
        // CreatesSupportModuleTables) they're guarded by class name rather
        // than required unconditionally, in case another test class in the
        // same PHPUnit process already declared them.
        foreach ([
            ['2026_06_13_214010_create_activity_log_table', 'CreateActivityLogTable'],
            ['2026_06_13_214011_add_event_column_to_activity_log_table', 'AddEventColumnToActivityLogTable'],
            ['2026_06_13_214012_add_batch_uuid_column_to_activity_log_table', 'AddBatchUuidColumnToActivityLogTable'],
        ] as [$file, $class]) {
            if (!class_exists($class)) {
                require database_path('migrations/' . $file . '.php');
            }
            (new $class())->up();
        }

        foreach ([
            '2026_07_30_180003_create_message_attachments_table',
            '2026_09_05_143251_create_faq_categories_table',
            '2026_09_05_143252_create_faqs_table',
            '2026_09_05_173244_add_embedding_to_faqs_table',
            '2026_09_26_100003_add_source_columns_to_faqs_table',
            '2026_09_05_173257_create_copilot_messages_table',
            '2026_09_26_100001_create_ai_copilot_settings_table',
            '2026_09_26_100002_create_business_profiles_table',
            '2026_09_26_120001_create_knowledge_gap_reports_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }
}
