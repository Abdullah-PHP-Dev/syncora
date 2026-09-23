<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->string('sendgrid_contact_id')->nullable()->after('unsubscribe_token');
        });
    }

    public function down(): void
    {
        Schema::table('email_subscribers', function (Blueprint $table) {
            $table->dropColumn('sendgrid_contact_id');
        });
    }
};
