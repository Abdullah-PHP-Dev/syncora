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
        // Only 'one_time' and 'newsletter' are real, selectable options -
        // both send through the exact same one-time dispatch mechanism
        // (EmailMarketingService::dispatchCampaign()), 'newsletter' is
        // purely a label for organizing/filtering campaigns, not a
        // different send engine. 'automated'/'drip' are NOT in this enum:
        // those would need a real trigger engine and a multi-step
        // scheduled-sequence engine that don't exist yet - the UI shows
        // them as disabled "Coming Soon" cards rather than selectable
        // values with no backing implementation.
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->enum('campaign_type', ['one_time', 'newsletter'])->default('one_time')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('campaign_type');
        });
    }
};
