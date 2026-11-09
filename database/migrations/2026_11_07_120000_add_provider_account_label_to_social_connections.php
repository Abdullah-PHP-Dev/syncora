<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which provider account granted a consent, for display only (e.g. the Google
 * account email), so several accounts on one platform can be told apart.
 * Re-runnable: skips when the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_connections') && ! Schema::hasColumn('social_connections', 'provider_account_label')) {
            Schema::table('social_connections', function (Blueprint $table) {
                $table->string('provider_account_label')->nullable()->after('provider_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('social_connections', 'provider_account_label')) {
            Schema::table('social_connections', function (Blueprint $table) {
                $table->dropColumn('provider_account_label');
            });
        }
    }
};
