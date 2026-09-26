<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a Faq row as auto-generated from a seller's Business Profile
     * (source='business_profile') rather than hand-authored, and which
     * profile field it represents (source_key, eg. 'business_hours') -
     * lets BusinessProfileFaqSyncService find-or-create/update/remove the
     * exact row for a given field idempotently on every profile save,
     * without guessing from the question text.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('source')->nullable()->after('tags');
            $table->string('source_key')->nullable()->after('source');

            $table->index(['user_id', 'source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source', 'source_key']);
            $table->dropColumn(['source', 'source_key']);
        });
    }
};
