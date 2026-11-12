<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM detail screens (L-02 lead profile, D-05/D-07/D-08 deal detail and
 * won/lost, A-01/A-02 tasks): qualification answers and score breakdown on
 * leads; notes, win reasons and competitor on deals; priority, outcome and
 * duration on activities. All nullable, so existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_leads', 'qualification')) {
                $table->json('qualification')->nullable()->after('interest');
                $table->json('score_breakdown')->nullable()->after('score');
            }
        });
        Schema::table('crm_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_contacts', 'do_not_contact')) {
                $table->boolean('do_not_contact')->default(false)->after('whatsapp_consent');
            }
        });
        Schema::table('crm_deals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deals', 'notes')) {
                $table->text('notes')->nullable()->after('source');
                $table->json('win_reasons')->nullable()->after('lost_reason');
                $table->string('competitor', 160)->nullable()->after('win_reasons');
            }
        });
        Schema::table('crm_activities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_activities', 'priority')) {
                $table->string('priority', 10)->nullable()->after('type'); // low | medium | high
                $table->string('outcome', 30)->nullable()->after('body');
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('outcome');
                $table->timestamp('remind_at')->nullable()->after('due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', fn (Blueprint $t) => Schema::hasColumn('crm_activities', 'priority') ? $t->dropColumn(['priority', 'outcome', 'duration_minutes', 'remind_at']) : null);
        Schema::table('crm_deals', fn (Blueprint $t) => Schema::hasColumn('crm_deals', 'notes') ? $t->dropColumn(['notes', 'win_reasons', 'competitor']) : null);
        Schema::table('crm_contacts', fn (Blueprint $t) => Schema::hasColumn('crm_contacts', 'do_not_contact') ? $t->dropColumn('do_not_contact') : null);
        Schema::table('crm_leads', fn (Blueprint $t) => Schema::hasColumn('crm_leads', 'qualification') ? $t->dropColumn(['qualification', 'score_breakdown']) : null);
    }
};
