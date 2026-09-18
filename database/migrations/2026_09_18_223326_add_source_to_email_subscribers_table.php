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
        // Nullable, no backfill for existing rows - there's no reliable
        // way to know in hindsight whether a subscriber added before this
        // column existed came from the manual-add form or a CSV import,
        // so those rows stay null ("Unknown") rather than being guessed
        // at. Every subscriber created from here on gets a real value
        // from EmailSubscriberController::store()/import().
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->string('source')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
