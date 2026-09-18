<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ad_account_id is a real FK on six tables (ad_campaigns, ad_adgroups,
     * ad_creatives, ad_media, ads, platform_pages), not just ad_campaigns -
     * all six must move together or the chain breaks once ad_accounts is
     * dropped in drop_legacy_account_columns_and_tables.
     */
    public function up(): void
    {
        foreach (['ad_campaigns', 'ad_adgroups', 'ad_creatives', 'ad_media', 'ads'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('social_account_id')->nullable()->after('ad_account_id')
                    ->constrained('social_accounts')->cascadeOnDelete();
            });
        }

        Schema::table('platform_pages', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('ad_account_id')
                ->constrained('social_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['ad_campaigns', 'ad_adgroups', 'ad_creatives', 'ad_media', 'ads', 'platform_pages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('social_account_id');
            });
        }
    }
};
