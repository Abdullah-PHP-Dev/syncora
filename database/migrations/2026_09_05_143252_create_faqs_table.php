<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One table for both knowledge layers (System FAQs and each seller's
     * own business FAQ), distinguished by user_id (null = system, set =
     * that seller's own) - not two parallel tables. Mirrors the BRD's own
     * "faqs is a single table distinguished by scope + tenant_id" design
     * note, translated to this app's real user_id-based scoping.
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question');
            $table->longText('answer');
            $table->string('language', 10)->default('en');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->json('tags')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'language', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
