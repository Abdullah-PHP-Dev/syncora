<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same "small JSON bag for platform-specific extras" shape as
     * post_accounts.settings - LinkedinAdService uses it to record the
     * ad-events webhook callback URL an admin would register by hand if
     * the org is ever approved for a push-capable LinkedIn product (see
     * LinkedinAdService::registerAdEventsCallback()'s docblock).
     */
    public function up(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
