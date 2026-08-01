<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Google Search Responsive Search Ads carry several headlines/
        // descriptions rather than the single headline/message the other
        // platforms use, plus Demand Gen-specific asset fields for YouTube
        // video ads (business_name, logo/video asset resource names).
        Schema::table('ad_creatives', function (Blueprint $table) {
            $table->json('headlines')->nullable()->after('headline');
            $table->json('descriptions')->nullable()->after('message');
            $table->json('long_headlines')->nullable()->after('descriptions');
            $table->string('business_name')->nullable()->after('brand_name');
            $table->string('logo_asset_resource')->nullable()->after('business_name');
            $table->string('video_asset_resource')->nullable()->after('logo_asset_resource');
            $table->json('final_urls')->nullable()->after('url');
        });

        // Keyword targeting (Search campaigns) lives at the ad group level
        // in Google's model (AdGroupCriterion), so it's stored alongside the
        // rest of this adgroup's targeting rather than on the creative.
        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('location_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_creatives', function (Blueprint $table) {
            $table->dropColumn(['headlines', 'descriptions', 'long_headlines', 'business_name', 'logo_asset_resource', 'video_asset_resource', 'final_urls']);
        });

        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
