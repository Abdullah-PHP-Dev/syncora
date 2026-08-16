<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * message_channels only ever had a plain index on (platform, external_id)
 * - every connect flow's updateOrCreate() (storeDiscord() and its
 * equivalents for the other eleven platforms) has therefore only been
 * guarded against duplicates by its own SELECT-then-write, never by the
 * database itself. A race (double form submit, two overlapping requests)
 * could still insert two rows for the same account. Scoped by user_id,
 * not just (platform, external_id), matching the multi-tenant pattern
 * already used for ad_accounts - two different Socialeaz users are
 * allowed to each connect the same underlying platform account.
 *
 * Deduplicates first (keeping the lowest/oldest id per group) since
 * ALTER TABLE ... ADD UNIQUE fails outright if any real duplicates
 * already exist on a live table - this is a one-way cleanup, not
 * reversible in down(), same as any other dedup migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            DELETE t1 FROM message_channels t1
            INNER JOIN message_channels t2
                ON t1.user_id = t2.user_id
                AND t1.platform = t2.platform
                AND t1.external_id = t2.external_id
            WHERE t1.id > t2.id
                AND t1.external_id IS NOT NULL
        ');

        Schema::table('message_channels', function (Blueprint $table) {
            $table->unique(['user_id', 'platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('message_channels', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'platform', 'external_id']);
        });
    }
};
