<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing/ads-side account details, split out of social_accounts for
     * the same reason as social_account_post_details. Previously this data
     * (currency, business id, account status) only ever lived inside the
     * generic social_accounts.metadata JSON blob - real columns here so it
     * is queryable/reportable, without breaking the ~50 existing ads blade
     * views that already read $account->metadata['currency'] (that write
     * continues unchanged; this table is populated alongside it).
     */
    public function up(): void
    {
        Schema::create('social_account_ad_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->unique()->constrained('social_accounts')->cascadeOnDelete();

            $table->string('currency', 10)->nullable();
            $table->string('account_status')->nullable();
            $table->string('business_id')->nullable();
            $table->string('timezone')->nullable();
            $table->decimal('spend_cap', 15, 2)->nullable();
            $table->decimal('amount_spent', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->string('funding_source')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_ad_details');
    }
};
