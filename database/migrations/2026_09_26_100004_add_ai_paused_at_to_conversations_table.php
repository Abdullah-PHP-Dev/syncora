<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set the moment a human agent sends a reply on a conversation the AI
     * Copilot was active on - ProcessAiCopilotReply skips any conversation
     * with this set, so the bot never talks over a human who has taken
     * over. Cleared via the "Resume AI" action once the agent is done.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('ai_paused_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('ai_paused_at');
        });
    }
};
