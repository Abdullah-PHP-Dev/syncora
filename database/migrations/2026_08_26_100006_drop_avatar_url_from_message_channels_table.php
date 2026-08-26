<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Missed in drop_legacy_account_columns_and_tables - avatar_url is a
     * profile field like name/username/access_token, so it belongs solely
     * on the parent social_accounts row (reachable via ->socialAccount),
     * not duplicated here.
     */
    public function up(): void
    {
        if (Schema::hasColumn('message_channels', 'avatar_url')) {
            Schema::table('message_channels', function (Blueprint $table) {
                $table->dropColumn('avatar_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('message_channels', function (Blueprint $table) {
            $table->text('avatar_url')->nullable();
        });
    }
};
