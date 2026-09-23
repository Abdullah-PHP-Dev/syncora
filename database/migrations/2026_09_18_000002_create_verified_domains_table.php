<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verified_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_subaccount_id')->constrained()->cascadeOnDelete();
            $table->string('sendgrid_domain_id')->nullable();
            $table->string('domain');
            $table->string('subdomain')->nullable();
            $table->boolean('automatic_security')->default(true);
            $table->boolean('is_default')->default(true);
            $table->enum('status', ['pending', 'dns_pending', 'verified', 'failed'])->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_domains');
    }
};
