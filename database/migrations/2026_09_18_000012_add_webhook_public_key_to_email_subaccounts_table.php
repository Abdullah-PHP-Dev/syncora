<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_subaccounts', function (Blueprint $table) {
            // Not a secret (it's the PUBLIC half of SendGrid's ECDSA
            // Event Webhook signing key pair) - plain text column, unlike
            // api_key.
            $table->text('webhook_public_key')->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('email_subaccounts', function (Blueprint $table) {
            $table->dropColumn('webhook_public_key');
        });
    }
};
