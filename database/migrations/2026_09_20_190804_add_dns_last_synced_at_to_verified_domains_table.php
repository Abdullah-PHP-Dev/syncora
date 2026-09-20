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
        // The one genuinely new persistent fact worth storing: when this
        // domain's DNS was last synced via Cloudflare automation. The
        // authoritative "is this record actually correct" state already
        // lives in domain_dns_records.valid (refreshed by SendGrid's own
        // verify() call) - a separate cached created/skipped/conflicted
        // status would just be a second, potentially-stale copy of that,
        // so this stays a single timestamp rather than several columns.
        Schema::table('verified_domains', function (Blueprint $table) {
            $table->timestamp('dns_last_synced_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verified_domains', function (Blueprint $table) {
            $table->dropColumn('dns_last_synced_at');
        });
    }
};
