<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending email-verification credentials: one row per user, replaced on every send.
 * Only hashes are stored (never the six-digit code or the link token).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_verification_codes')) {
            return;
        }
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('email');                      // the address these credentials prove
            $table->string('code_hash', 64);              // HMAC-SHA256 of the 6-digit code
            $table->string('token_hash', 64)->unique();   // SHA-256 of the magic-link token
            $table->timestamp('code_expires_at');
            $table->timestamp('token_expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->foreign('user_id', 'email_verification_codes_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
