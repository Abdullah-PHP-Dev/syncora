<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LinkedIn's targetingCriteria (locations/seniorities/age ranges - see
     * LinkedinAdService::buildTargeting()) is a nested include/and/or
     * structure that doesn't fit the flat location_ids/age_groups columns
     * other platforms already stretch to cover their own simpler
     * geo+demographic targeting - stored here verbatim instead of forcing
     * it into those columns' existing local semantics.
     */
    public function up(): void
    {
        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->json('targeting_criteria')->nullable()->after('age_groups');
        });
    }

    public function down(): void
    {
        Schema::table('ad_adgroups', function (Blueprint $table) {
            $table->dropColumn('targeting_criteria');
        });
    }
};
