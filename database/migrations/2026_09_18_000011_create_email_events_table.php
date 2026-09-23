<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nullable: an event can arrive for a message this app can't
            // (yet) tie back to a specific campaign row - stored anyway
            // rather than dropped, same "log unknowns rather than discard"
            // approach used elsewhere in this app's webhook handlers.
            $table->foreignId('email_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sendgrid_message_id')->nullable();
            $table->string('event_type');
            $table->string('recipient_email');
            $table->timestamp('event_at')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->text('reason')->nullable();
            // SendGrid's own per-event id - the real idempotency key.
            // Unique so ProcessSendGridEvent's upsert-or-skip can't ever
            // double-count the same event, including SendGrid's own
            // documented at-least-once redelivery.
            $table->string('sg_event_id')->unique();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['email_campaign_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
