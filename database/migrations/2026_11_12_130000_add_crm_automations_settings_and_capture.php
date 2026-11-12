<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Release 2: automations (+ run log), per-workspace CRM settings,
 * custom field definitions, saved segments, lead capture idempotency, and the
 * "CRM contacts" plan capacity (unset = unlimited until an admin sets it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_automations')) {
            Schema::create('crm_automations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('trigger', 40);
                $table->json('trigger_config')->nullable();
                $table->json('conditions')->nullable();
                $table->json('actions');
                $table->boolean('is_active')->default(false);
                $table->unsignedInteger('runs_count')->default(0);
                $table->timestamp('last_run_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'trigger', 'is_active']);
            });
        }

        if (! Schema::hasTable('crm_automation_runs')) {
            Schema::create('crm_automation_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('automation_id')->constrained('crm_automations')->cascadeOnDelete();
                $table->string('subject_type', 20);
                $table->unsignedBigInteger('subject_id');
                // Time-based triggers fire once per subject and idle period.
                $table->string('run_key', 80)->nullable();
                $table->string('status', 10); // done | skipped | failed
                $table->json('result')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['automation_id', 'run_key']);
                $table->index(['automation_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('crm_settings')) {
            Schema::create('crm_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->json('settings')->nullable();
                $table->string('capture_token', 64)->nullable()->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_fields')) {
            Schema::create('crm_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->string('entity', 20)->default('contact'); // contact | company | deal
                $table->string('key', 40);
                $table->string('label', 80);
                $table->string('type', 20); // text | number | date | dropdown
                $table->json('options')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'entity', 'key']);
            });
        }

        if (! Schema::hasTable('crm_segments')) {
            Schema::create('crm_segments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->json('filters');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('workspace_id');
            });
        }

        if (Schema::hasTable('crm_leads') && ! Schema::hasColumn('crm_leads', 'external_lead_id')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                // Provider lead id (Meta leadgen id, website form submission id): one lead per submission.
                $table->string('external_lead_id', 100)->nullable()->after('conversation_id');
                $table->json('meta')->nullable()->after('disqualify_note');
                $table->unique(['workspace_id', 'external_lead_id']);
            });
        }

        if (Schema::hasTable('package_features') && ! DB::table('package_features')->where('key', 'crm_contacts')->exists()) {
            DB::table('package_features')->insert([
                'key' => 'crm_contacts', 'name_en' => 'CRM contacts', 'name_ar' => 'جهات اتصال إدارة العملاء', 'type' => 'capacity',
                'is_active' => true, 'version' => 1, 'sort_order' => (int) DB::table('package_features')->max('sort_order') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_leads', 'external_lead_id')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                $table->dropUnique(['workspace_id', 'external_lead_id']);
                $table->dropColumn(['external_lead_id', 'meta']);
            });
        }
        foreach (['crm_automation_runs', 'crm_automations', 'crm_settings', 'crm_fields', 'crm_segments'] as $table) {
            Schema::dropIfExists($table);
        }
        if (Schema::hasTable('package_features')) {
            DB::table('package_features')->where('key', 'crm_contacts')->delete();
        }
    }
};
