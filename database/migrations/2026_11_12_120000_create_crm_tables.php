<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socialeaz CRM (Release 1). Every table is scoped by workspace_id: the tenant
 * users row the request runs as (UseSellerWorkspace), the same scope every
 * other module uses through user_id. owner_id / created_by are the people.
 * Indexes follow the list, filter and lookup queries in App\Services\Crm.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->boolean('is_default')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['workspace_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('crm_stages')) {
            Schema::create('crm_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('kind', 10)->default('open'); // open | won | lost
                $table->unsignedTinyInteger('probability')->default(0);
                $table->string('color', 20)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['pipeline_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('crm_companies')) {
            Schema::create('crm_companies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->string('domain', 160)->nullable();
                $table->string('industry', 80)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('city', 80)->nullable();
                $table->string('country', 80)->nullable();
                $table->text('notes')->nullable();
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['workspace_id', 'name']);
                $table->index(['workspace_id', 'domain']);
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->string('first_name', 80);
                $table->string('last_name', 80)->nullable();
                $table->string('arabic_name', 160)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('phone', 40)->nullable();
                // Match key: the last 9 digits, so +966 55…, 0096655… and 055… are one person.
                $table->string('phone_key', 20)->nullable();
                $table->string('job_title', 120)->nullable();
                $table->string('lifecycle', 20)->default('lead');
                $table->string('source', 40)->nullable();
                $table->string('language', 10)->nullable();
                $table->string('preferred_channel', 20)->nullable();
                $table->unsignedTinyInteger('score')->default(0);
                $table->boolean('whatsapp_consent')->default(false);
                $table->json('tags')->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['workspace_id', 'phone_key']);
                $table->index(['workspace_id', 'email']);
                $table->index(['workspace_id', 'lifecycle']);
                $table->index(['workspace_id', 'owner_id']);
                $table->index(['workspace_id', 'last_activity_at']);
            });
        }

        if (! Schema::hasTable('crm_contact_identities')) {
            // A contact's handle on a channel (WhatsApp number, IG user id…): links future chats automatically.
            Schema::create('crm_contact_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
                $table->string('platform', 30);
                $table->string('external_id', 191);
                $table->string('handle', 160)->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'platform', 'external_id'], 'crm_identities_unique');
                $table->index('contact_id');
            });
        }

        if (! Schema::hasTable('crm_leads')) {
            Schema::create('crm_leads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                // One person record: a lead always points at its contact.
                $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('new'); // new | contacted | qualified | unqualified | converted
                $table->string('source', 40)->nullable();
                $table->string('campaign', 160)->nullable();
                $table->foreignId('ad_campaign_id')->nullable()->constrained('ad_campaigns')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
                $table->text('interest')->nullable();
                $table->decimal('estimated_value', 14, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->unsignedTinyInteger('score')->default(0);
                $table->string('disqualify_reason', 40)->nullable();
                $table->text('disqualify_note')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('deal_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['workspace_id', 'status', 'created_at']);
                $table->index(['workspace_id', 'owner_id']);
                $table->index('contact_id');
            });
        }

        if (! Schema::hasTable('crm_deals')) {
            Schema::create('crm_deals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
                $table->foreignId('stage_id')->constrained('crm_stages');
                $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 160);
                $table->decimal('value', 14, 2)->default(0);
                $table->string('currency', 3)->default('SAR');
                $table->unsignedTinyInteger('probability')->default(0);
                $table->date('expected_close_date')->nullable();
                $table->string('status', 10)->default('open'); // open | won | lost
                $table->string('source', 40)->nullable();
                $table->string('lost_reason', 160)->nullable();
                $table->timestamp('won_at')->nullable();
                $table->timestamp('lost_at')->nullable();
                $table->timestamp('stage_changed_at')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['workspace_id', 'pipeline_id', 'stage_id', 'position'], 'crm_deals_board_index');
                $table->index(['workspace_id', 'status', 'won_at']);
                $table->index('contact_id');
                $table->index('company_id');
            });
        }

        if (! Schema::hasTable('crm_activities')) {
            // Tasks, calls, meetings, notes and the record history (type "event").
            Schema::create('crm_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 20); // task | call | meeting | note | email | event
                $table->string('subject', 200)->nullable();
                $table->text('body')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->cascadeOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'type', 'completed_at', 'due_at'], 'crm_activities_tasks_index');
                $table->index(['contact_id', 'created_at']);
                $table->index(['deal_id', 'created_at']);
                $table->index(['lead_id', 'created_at']);
                $table->index(['company_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_activities', 'crm_deals', 'crm_leads', 'crm_contact_identities', 'crm_contacts', 'crm_companies', 'crm_stages', 'crm_pipelines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
