<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same pre-existing model/schema mismatch as the posts table fix
     * earlier - PostAccount already declares `settings` (and `metadata`)
     * as fillable/cast, but neither column exists. Only `settings` is
     * added here since it's the only one actually used
     * (PinterestPostService's default board_id, and WhatsApp Embedded
     * Signup's waba_id in PostAccountController).
     */
    public function up(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
