<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connection Hub (docs/connection-hub-design.md §2): one row per
 * user x platform x provider account x auth step - the consent itself -
 * with social_accounts (pages, IG accounts, ad accounts, numbers) as its
 * assets. Existing rows are linked by `connections:backfill` (dry-run first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('platform', 32);           // meta, google, linkedin, tiktok, snapchat, x, threads, pinterest
            $table->string('step', 64);               // meta.login, meta.whatsapp, meta.instagram_login, ...
            $table->string('provider_account_id')->nullable();
            $table->string('provider_app', 64)->nullable(); // adminSetting prefix, e.g. posts.facebook
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('token_secret')->nullable();  // OAuth 1.0a (X)
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('status', 16)->default('active'); // active|expiring|expired|needs_reauth|revoked|error
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'step', 'provider_account_id'], 'social_connections_identity_unique');
            $table->index(['status', 'expires_at']);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->foreignId('social_connection_id')->nullable()->after('workspace_id')
                ->constrained('social_connections')->nullOnDelete();
            // null = every capability the connection grants; a list = the
            // user's choice in the Hub's asset picker.
            $table->json('enabled_capabilities')->nullable()->after('has_ads_permission');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_connection_id');
            $table->dropColumn('enabled_capabilities');
        });

        Schema::dropIfExists('social_connections');
    }
};
