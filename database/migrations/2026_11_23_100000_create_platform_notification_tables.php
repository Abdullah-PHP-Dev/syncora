<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform notifications (docs: Socialeaz Email Notification Audit §7.5).
 *
 * notification_events       durable outbox, one row per domain event (dedup_key unique = idempotent emit)
 * user_notifications        the unified in-app bell / notification center, per person
 * notification_deliveries   every channel attempt (email, digest), retry-safe by (event, user|address, channel)
 * notification_preferences  per person x workspace (0 = all) x category|event
 * notification_digest_items buffer for daily / weekly digests
 * email_suppressions        hard bounces / complaints / manual blocks for platform mail
 *
 * Every create is guarded so a partially applied run can be re-run safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_events')) {
            Schema::create('notification_events', function (Blueprint $table) {
                $table->id();
                $table->string('event', 80);
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('subject_type', 80)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('dedup_key', 191)->unique();
                $table->json('payload')->nullable();
                $table->unsignedTinyInteger('priority')->default(2);
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
                $table->index(['workspace_id', 'event', 'occurred_at']);
                $table->index(['processed_at', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->foreignId('notification_event_id')->nullable()->constrained('notification_events')->nullOnDelete();
                $table->string('event', 80);
                $table->string('category', 40);
                $table->unsignedTinyInteger('priority')->default(2);
                $table->json('params')->nullable();
                $table->text('url')->nullable();
                $table->string('group_key', 191)->nullable();
                $table->unsignedInteger('group_count')->default(1);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'archived_at', 'read_at', 'id']);
                $table->index(['user_id', 'group_key', 'read_at']);
                $table->index(['user_id', 'category', 'id']);
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                // A recipient without an account (team invitee) is keyed by address.
                $table->string('recipient_key', 191);
                $table->string('channel', 16);
                $table->string('status', 20)->default('queued');
                $table->string('group_key', 191)->nullable();
                $table->string('locale', 5)->nullable();
                $table->string('to_address', 191)->nullable();
                $table->string('provider_message_id', 191)->nullable();
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
                $table->unique(['notification_event_id', 'recipient_key', 'channel'], 'notification_deliveries_retry_safe');
                $table->index(['recipient_key', 'group_key', 'status']);
                $table->index(['user_id', 'channel', 'status', 'created_at']);
                $table->index('provider_message_id');
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // 0 = every workspace (MySQL unique indexes treat NULLs as distinct).
                $table->unsignedBigInteger('workspace_id')->default(0);
                $table->string('scope', 10);
                $table->string('key', 80);
                $table->boolean('in_app')->nullable();
                $table->string('email', 10)->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'workspace_id', 'scope', 'key'], 'notification_preferences_unique');
            });
        }

        if (! Schema::hasTable('notification_digest_items')) {
            Schema::create('notification_digest_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('cadence', 10);
                $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('sent_at')->nullable();
                $table->unique(['user_id', 'cadence', 'notification_event_id'], 'notification_digest_items_unique');
                $table->index(['cadence', 'sent_at', 'user_id']);
            });
        }

        if (! Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table) {
                $table->string('email', 191)->primary();
                $table->string('reason', 20);
                $table->string('source', 40)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_login_devices')) {
            Schema::create('user_login_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('fingerprint', 64);
                $table->string('label', 120)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'fingerprint']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->nullable();
            }
            if (! Schema::hasColumn('users', 'ui_locale')) {
                $table->string('ui_locale', 5)->nullable();
            }
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable();
            }
            if (! Schema::hasColumn('users', 'notification_settings')) {
                $table->json('notification_settings')->nullable();
            }
            if (! Schema::hasColumn('users', 'marketing_opt_in_at')) {
                $table->timestamp('marketing_opt_in_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['user_login_devices', 'email_suppressions', 'notification_digest_items', 'notification_preferences', 'notification_deliveries', 'user_notifications', 'notification_events'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $table) {
            foreach (['locale', 'ui_locale', 'timezone', 'notification_settings', 'marketing_opt_in_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
