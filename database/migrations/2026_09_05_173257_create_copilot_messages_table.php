<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for every AI Copilot retrieval attempt (BRD section 7's
     * copilot_messages table / section 8.3's "AI decision logs retain the
     * matched FAQ id, confidence score breakdown, and resolution type for
     * every Copilot message") - one row per customer message the Copilot
     * was asked to answer, whether or not it actually found a match.
     * user_id is the seller who owns the conversation, denormalized here
     * (rather than joined through conversations->channel->user_id every
     * time) since per-seller Copilot performance analytics is exactly the
     * kind of query this table exists to serve (BRD section 12's KPIs).
     */
    public function up(): void
    {
        Schema::create('copilot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faq_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('confidence_breakdown')->nullable();
            $table->enum('resolution_type', ['auto_replied', 'suggested', 'no_match'])->default('no_match');
            $table->text('suggested_reply')->nullable();
            $table->boolean('was_sent')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_messages');
    }
};
