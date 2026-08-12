<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A connected inbox source - one Facebook Page, one Instagram
     * professional account, one WhatsApp business phone number, one
     * Telegram bot, or one X account authorized for DMs. Mirrors the
     * ad_accounts/post_accounts pattern already used elsewhere in this app
     * for per-platform connected-account rows.
     */
    public function up(): void
    {
        Schema::create('message_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook|instagram|whatsapp|telegram|x
            $table->string('name')->nullable();
            $table->string('external_id')->nullable(); // page_id / ig_user_id / phone_number_id / bot id / x user id
            $table->string('username')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            // Per-channel webhook verification secret (Meta's hub.verify_token
            // challenge, and reused as Telegram's X-Telegram-Bot-Api-Secret-Token).
            $table->string('verify_token')->nullable();
            // Platform-specific extras that don't warrant their own column
            // (WhatsApp's waba_id, X's DM poll pagination_token/since_id,
            // Telegram's webhook secret confirmation, etc.) - flexible
            // without schema bloat, same rationale as this app's other JSON
            // "extra config" columns.
            $table->json('meta')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_channels');
    }
};
