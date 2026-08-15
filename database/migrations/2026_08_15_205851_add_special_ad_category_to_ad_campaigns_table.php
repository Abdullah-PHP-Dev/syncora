<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meta's Special Ad Category is a compliance-locked, immutable-after-
     * creation Campaign-level field (HOUSING/EMPLOYMENT/CREDIT/
     * ISSUES_ELECTIONS_POLITICS) that legally restricts age/gender/zip-
     * radius targeting on every Ad Set under it - see FacebookAdService::
     * storeAdGroup()'s docblock. Persisted here (rather than re-read from
     * the request on every update) so updateAdGroup() can enforce those
     * restrictions even when the edit form doesn't resubmit it.
     */
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('special_ad_category')->nullable()->after('objective');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('special_ad_category');
        });
    }
};
