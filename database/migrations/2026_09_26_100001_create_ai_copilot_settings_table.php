<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-seller AI Copilot configuration - one row per seller (unique
     * user_id), read via AiCopilotSetting::forSeller(). Deliberately not
     * folded into the existing adminSetting()/admin_settings table: that
     * table is a single flat, installation-wide key-value store (see
     * AiCopilotService's docblock), not seller-scoped, so it can't hold a
     * per-tenant confidence threshold at all.
     */
    public function up(): void
    {
        Schema::create('ai_copilot_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Opt-in on both dimensions - a seller who never visits this
            // settings page must never have the bot silently replying to
            // their customers.
            $table->boolean('ai_enabled')->default(false);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->unsignedTinyInteger('confidence_threshold_auto')->default(80);
            $table->unsignedTinyInteger('confidence_threshold_suggested')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_copilot_settings');
    }
};
