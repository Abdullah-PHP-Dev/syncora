<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deduplicated "the AI Copilot found nothing" question, one row per
     * distinct (seller, normalized question) pair - populated from
     * ProcessAiCopilotReply's existing no_match branch, not a separate
     * tracking mechanism. question_hash is a simple lowercase/punctuation-
     * stripped/whitespace-collapsed dedup key, deliberately not semantic
     * clustering - this is a lightweight triage list, not a second
     * embedding pipeline.
     */
    public function up(): void
    {
        Schema::create('knowledge_gap_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->string('question_hash');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('last_occurred_at');
            $table->enum('status', ['new', 'under_review', 'faq_created', 'ignored', 'resolved'])->default('new');
            $table->foreignId('suggested_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'question_hash']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_gap_reports');
    }
};
