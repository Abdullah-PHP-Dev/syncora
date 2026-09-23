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
        // Real, per-campaign SendGrid Suppression Group (ASM) id - each
        // seller has their own SendGrid subaccount, so a suppression
        // group belongs to that specific subaccount's ASM groups, never
        // a single value shared across every seller. Replaces the old
        // single global `email_marketing.sendgrid.suppression_group_id`
        // admin setting SendGridCampaignService::saveDraft() previously
        // applied uniformly to every seller's campaigns regardless of
        // which subaccount was actually sending - architecturally wrong
        // for this multi-tenant, per-seller-subaccount model.
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('suppression_group_id')->nullable()->after('audience_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('suppression_group_id');
        });
    }
};
