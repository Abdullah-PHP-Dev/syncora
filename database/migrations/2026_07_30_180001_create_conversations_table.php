<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One thread per external customer per channel. Customers aren't
     * backed by a local user/contact record (same "opaque external
     * identity" pattern this app already uses for post_comments) - just
     * denormalized name/avatar fields alongside their platform-specific ID
     * (PSID for Messenger, IGSID for Instagram, wa_id for WhatsApp, chat_id
     * for Telegram, participant id for X).
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_channel_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            // X's dm_conversation_id - null for the other four platforms,
            // which are naturally addressed by the customer's own ID.
            $table->string('external_conversation_id')->nullable();
            $table->string('customer_external_id');
            $table->string('customer_name')->nullable();
            $table->string('customer_avatar_url')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('status')->default('open'); // open|closed|archived
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['message_channel_id', 'customer_external_id']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
