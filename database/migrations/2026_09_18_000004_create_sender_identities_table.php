<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_subaccount_id')->constrained()->cascadeOnDelete();
            $table->string('sendgrid_sender_id')->nullable();
            $table->string('nickname');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to')->nullable();
            // CAN-SPAM requires a physical mailing address on every sender
            // identity - SendGrid's own /v3/senders endpoint rejects
            // creation without these, not an extra validation we invented.
            $table->string('address');
            $table->string('address_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country');
            $table->enum('status', ['pending', 'verification_sent', 'verified', 'failed'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_identities');
    }
};
