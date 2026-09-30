<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns the redesigned seller Knowledge Base needs:
     *  - faqs.status gains 'archived' - retired entries kept for reference,
     *    never shown in the Help Center or matched by the AI Copilot
     *    (both already filter on status = 'published').
     *  - faqs.copilot_enabled - a published entry can still be withheld
     *    from the AI Copilot's automatic answers (the "AI Copilot" toggle
     *    on the Add FAQ modal); AiCopilotService::findBestMatch() filters
     *    on it.
     *  - faq_categories.icon - a boxicons class shown next to the category
     *    in the listing (Shipping -> truck, Payments -> credit card, ...).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE faqs MODIFY status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'");

        Schema::table('faqs', function (Blueprint $table) {
            $table->boolean('copilot_enabled')->default(true)->after('status');
        });

        Schema::table('faq_categories', function (Blueprint $table) {
            $table->string('icon', 50)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('faq_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn('copilot_enabled');
        });

        DB::table('faqs')->where('status', 'archived')->update(['status' => 'draft']);
        DB::statement("ALTER TABLE faqs MODIFY status ENUM('draft', 'published') NOT NULL DEFAULT 'draft'");
    }
};
