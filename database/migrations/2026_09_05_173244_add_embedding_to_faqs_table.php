<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precomputed at save time (see FaqController/KnowledgeBaseController)
     * rather than recomputed per incoming customer message - the AI
     * Copilot's per-message cost is then one embedding call (for the
     * message) plus in-PHP cosine similarity against every candidate
     * FAQ's already-stored vector, not one call per FAQ per message.
     * embedding_model is stored alongside so a future model swap can
     * detect and re-embed stale rows instead of silently comparing
     * vectors from two incompatible embedding spaces.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('tags');
            $table->string('embedding_model')->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model']);
        });
    }
};
