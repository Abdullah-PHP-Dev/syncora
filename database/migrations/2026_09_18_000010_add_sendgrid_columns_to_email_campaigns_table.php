<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->string('sendgrid_single_send_id')->nullable()->after('email_template_id');
            $table->foreignId('sender_identity_id')->nullable()->after('sendgrid_single_send_id')->constrained()->nullOnDelete();
            $table->string('preheader')->nullable()->after('subject');
            // email_list_id (existing column) is kept, nullable-in-practice
            // going forward, as the 'list' case of audience_type/
            // audience_id - a segment-targeted campaign leaves it null.
            // Both were introduced together rather than migrating
            // email_list_id's meaning silently.
            $table->enum('audience_type', ['list', 'segment'])->default('list')->after('email_list_id');
            $table->unsignedBigInteger('audience_id')->nullable()->after('audience_type');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_identity_id');
            $table->dropColumn(['sendgrid_single_send_id', 'preheader', 'audience_type', 'audience_id']);
        });
    }
};
