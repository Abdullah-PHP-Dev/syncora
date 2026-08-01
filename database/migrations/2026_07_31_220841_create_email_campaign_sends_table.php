<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_subscriber_id')->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'pending', 'sending', 'sent', 'delivered', 'opened',
                'clicked', 'bounced', 'complained', 'unsubscribed', 'failed',
            ])->default('pending');
            // Mailgun's own id for the send, for support/debugging lookups
            // in the Mailgun dashboard - correlation of inbound webhook
            // events back to this row is done via the send id passed as a
            // Mailgun custom variable, not by matching this value.
            $table->string('mailgun_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email_subscriber_id'], 'email_campaign_sends_campaign_subscriber_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_campaign_sends');
    }
};
