<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connection Hub step 0c (docs/connection-hub-design.md §1c): Facebook Page
 * and Instagram tokens get their own column instead of being duplicated
 * into refresh_token (Meta issues no refresh tokens), and the Meta user
 * token that issued them is kept separately for the social_connections
 * backfill. Schema only - existing rows are moved by
 * `connections:move-page-tokens` (dry-run first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('asset_token')->nullable()->after('refresh_token');
            $table->text('user_token')->nullable()->after('asset_token');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['asset_token', 'user_token']);
        });
    }
};
