<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Suite (Analytics & Insights): the GA4 properties / Search Console
 * sites a workspace picked, its reporting preferences, and a short sync log.
 * Tokens stay on social_connections (step google.analytics) - nothing here
 * holds a credential. Re-runnable: every table is guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_properties')) {
            Schema::create('google_properties', function (Blueprint $table) {
                $table->id();
                // Workspace owner - UseSellerWorkspace swaps members to it.
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('social_connection_id')->nullable()->constrained()->nullOnDelete();
                $table->string('service', 32); // ga4 | search_console
                $table->string('external_id'); // properties/123 | sc-domain:example.com
                $table->string('display_name');
                $table->string('account_name')->nullable();
                $table->json('meta')->nullable();
                $table->boolean('is_selected')->default(false);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_error_code', 40)->nullable();
                $table->string('last_error')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'service', 'external_id']);
                $table->index(['user_id', 'service', 'is_selected']);
            });
        }

        if (! Schema::hasTable('google_suite_settings')) {
            Schema::create('google_suite_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->json('preferences')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_sync_events')) {
            Schema::create('google_sync_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('service', 32);
                $table->string('event', 40); // connected | property_selected | synced | failed | disconnected
                $table->string('status', 16)->default('ok'); // ok | warning | error
                $table->string('message')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sync_events');
        Schema::dropIfExists('google_suite_settings');
        Schema::dropIfExists('google_properties');
    }
};
