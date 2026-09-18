<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * post_comments had no read-tracking column at all before this - every
     * existing row (and every row already synced from a platform webhook)
     * is treated as unread until the notification center's mark-read action
     * touches it. Nullable, defaulting to NULL (= unread), so no backfill
     * is needed.
     */
    public function up(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_comments', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
