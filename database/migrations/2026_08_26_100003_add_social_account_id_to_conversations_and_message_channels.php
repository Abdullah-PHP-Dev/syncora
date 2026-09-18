<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * conversations moves from message_channel_id to social_account_id
     * directly (a conversation belongs to the account, not the operational
     * channel record). message_channels itself becomes a child of
     * social_accounts, keeping only platform-specific operational fields
     * (external_id, verify_token, meta, webhook_subscribed) - see
     * drop_legacy_account_columns_and_tables for the columns it loses.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('message_channel_id')
                ->constrained('social_accounts')->cascadeOnDelete();
        });

        Schema::table('message_channels', function (Blueprint $table) {
            $table->foreignId('social_account_id')->nullable()->after('id')
                ->constrained('social_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
        });

        Schema::table('message_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
        });
    }
};
