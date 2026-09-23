<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A campaign targeting a segment (audience_type = 'segment') has no
     * list at all - email_list_id was originally NOT NULL from the
     * Mailgun-only schema, where every campaign had exactly one list.
     * Caught by a real test (TenantIsolationTest) failing with a NOT NULL
     * constraint violation on a segment-audience campaign, not
     * anticipated up front.
     */
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->foreignId('email_list_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->foreignId('email_list_id')->nullable(false)->change();
        });
    }
};
