<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified connected-account table replacing post_accounts, ad_accounts,
     * and the identity/token half of message_channels. One row per platform
     * identity (a Page, a channel, an ad account, a user profile) shared
     * across posting/messaging/ads via the has_*_permission flags, instead
     * of each module keeping its own disconnected copy of the same login.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // No workspaces table exists yet - kept as a plain nullable
            // column (not a real FK) so this schema is ready for one
            // without needing another migration when it's introduced.
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('platform');
            $table->string('platform_account_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->text('avatar_url')->nullable();

            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('subscribers_count')->nullable();
            $table->unsignedBigInteger('likes_count')->nullable();
            $table->string('category')->nullable();
            $table->string('account_type')->nullable();
            $table->json('metadata')->nullable();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('token_type')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_token_valid')->default(true);

            $table->boolean('has_posting_permission')->default(false);
            $table->boolean('has_messaging_permission')->default(false);
            $table->boolean('has_ads_permission')->default(false);

            $table->timestamps();

            $table->unique(['platform', 'platform_account_id', 'user_id'], 'social_accounts_platform_account_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
