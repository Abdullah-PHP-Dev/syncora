<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled ad performance reports (Facebook campaigns page): what to send,
 * how often, and to whom. Guarded so the migration can be re-run safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_report_schedules')) {
            return;
        }

        Schema::create('ad_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // workspace owner
            $table->unsignedBigInteger('social_account_id');
            $table->string('platform', 20)->default('facebook');
            $table->string('name', 120);
            $table->string('frequency', 10);          // daily | weekly | monthly
            $table->unsignedTinyInteger('weekday')->nullable();      // 0 = Sunday (weekly)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-28 (monthly)
            $table->string('send_time', 5)->default('09:00');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->unsignedSmallInteger('period_days')->default(7);
            $table->string('recipient_email');
            $table->string('locale', 5)->default('en');
            $table->string('status', 10)->default('active');         // active | paused
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'social_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_report_schedules');
    }
};
