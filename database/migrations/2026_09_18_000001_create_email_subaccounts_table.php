<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_subaccounts', function (Blueprint $table) {
            $table->id();
            // One subaccount per seller - unique, not just indexed.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sendgrid_user_id')->nullable();
            $table->string('sendgrid_username')->nullable();
            $table->string('sendgrid_email')->nullable();
            $table->enum('status', ['pending', 'provisioning', 'active', 'failed', 'suspended'])->default('pending');
            // Encrypted at rest (see EmailSubaccount model) - stores both
            // the key value and SendGrid's own api_key_id (needed to
            // rotate/revoke later without guessing which key is "the one").
            $table->text('api_key')->nullable();
            $table->string('region')->default('global');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_subaccounts');
    }
};
