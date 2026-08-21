<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok comment read/reply lives on the TikTok Business API
 * (business-api.tiktok.com), which identifies an account by a business_id
 * - a different identifier from the Login Kit `open_id` already stored in
 * `account_id`. TiktokPostService was passing open_id as business_id,
 * which the Business API never accepts. Nullable and platform-agnostic:
 * other platforms with a separate business/advertiser identity can reuse
 * it rather than each inventing their own column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->string('business_id')->nullable()->after('parent_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('post_accounts', function (Blueprint $table) {
            $table->dropColumn('business_id');
        });
    }
};
