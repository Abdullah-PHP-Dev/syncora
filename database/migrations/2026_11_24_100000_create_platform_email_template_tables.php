<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator email templates (on top of the platform notification system).
 *
 * Table names carry a platform_ prefix: email_templates / email_template_versions already
 * belong to the customers' own marketing email designer.
 *
 * platform_email_templates          one row per (template key, locale) once an admin customises it;
 *                          content = published version, draft = unpublished edits. No row (or
 *                          content NULL) means the built-in default wording is used.
 * platform_email_template_versions  every publish / reset / restore, for history and rollback
 * platform_email_template_settings  per-event switch: a disabled event sends no email (bell and business
 *                          logic are unaffected)
 * notification_deliveries  + subject and template version actually sent, for the email log
 *
 * Guarded so a partial run can be re-run; full rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_email_templates')) {
            Schema::create('platform_email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80);
                $table->string('locale', 5);
                $table->json('content')->nullable();
                $table->json('draft')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['key', 'locale']);
            });
        }

        if (! Schema::hasTable('platform_email_template_versions')) {
            Schema::create('platform_email_template_versions', function (Blueprint $table) {
                $table->id();
                // Short explicit names: MySQL identifiers are limited to 64 characters.
                $table->unsignedBigInteger('platform_email_template_id');
                $table->foreign('platform_email_template_id', 'pe_template_versions_template_fk')->references('id')->on('platform_email_templates')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('action', 20);
                $table->json('content')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by', 'pe_template_versions_author_fk')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['platform_email_template_id', 'version'], 'pe_template_versions_lookup');
            });
        }

        if (! Schema::hasTable('platform_email_template_settings')) {
            Schema::create('platform_email_template_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80)->unique();
                $table->boolean('enabled')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('notification_deliveries')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                if (! Schema::hasColumn('notification_deliveries', 'subject')) {
                    $table->string('subject', 255)->nullable();
                }
                if (! Schema::hasColumn('notification_deliveries', 'template_version')) {
                    $table->string('template_version', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_deliveries')) {
            Schema::table('notification_deliveries', function (Blueprint $table) {
                foreach (['subject', 'template_version'] as $column) {
                    if (Schema::hasColumn('notification_deliveries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        Schema::dropIfExists('platform_email_template_settings');
        Schema::dropIfExists('platform_email_template_versions');
        Schema::dropIfExists('platform_email_templates');
    }
};
