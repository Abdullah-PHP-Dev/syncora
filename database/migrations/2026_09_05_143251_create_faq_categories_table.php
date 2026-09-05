<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * user_id nullable, same "null = system-wide, set = owned by this
     * seller" convention as faqs below - there's no separate tenant/
     * workspace table in this app (every other table scopes by user_id
     * directly, eg. posts/social_accounts), so this reuses that instead
     * of inventing tenancy machinery the rest of the app doesn't have.
     */
    public function up(): void
    {
        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_categories');
    }
};
