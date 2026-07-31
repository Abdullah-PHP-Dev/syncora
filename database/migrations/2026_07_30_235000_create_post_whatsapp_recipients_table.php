<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unlike every other platform in the Posts module, a WhatsApp "post"
     * isn't a single feed item with one post_id - it's a template message
     * sent individually to each recipient in a list, each with its own
     * delivery outcome (WhatsApp has no public feed to publish to at all;
     * see WhatsAppPostService for why this reinterprets "posting" as a
     * broadcast). One row per recipient per post keeps that per-recipient
     * status trackable rather than collapsing it into a single pass/fail
     * on the Post row.
     */
    public function up(): void
    {
        Schema::create('post_whatsapp_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->string('external_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_whatsapp_recipients');
    }
};
