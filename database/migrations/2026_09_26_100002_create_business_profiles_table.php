<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured business data (hours/policies/contact) for a seller -
     * the "prefer the structured source instead of asking the AI to
     * guess" input. Deliberately not queried directly by AiCopilotService;
     * BusinessProfileFaqSyncService projects populated fields into the
     * seller's own faqs rows instead, so retrieval has exactly one code
     * path (see that service's docblock).
     */
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            // {"mon":"9:00-18:00", ..., "closed":["sun"]}
            $table->json('business_hours')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('delivery_policy')->nullable();
            $table->text('return_policy')->nullable();
            // Free-form key/value for anything not modeled as its own
            // column - kept small and generic rather than adding a new
            // migration every time a seller needs one more fact type.
            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
