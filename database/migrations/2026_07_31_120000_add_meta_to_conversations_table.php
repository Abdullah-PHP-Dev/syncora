<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Microsoft Teams is the first platform in this module where replying
 * needs more than just the external_conversation_id already on this
 * table - the Bot Framework Connector API requires the serviceUrl from
 * the most recently received activity for that conversation (Microsoft's
 * own docs are explicit that this can vary per conversation/cloud and
 * must not be hardcoded), which has nowhere else to live. Mirrors
 * message_channels.meta rather than adding a narrowly-named column, so
 * it can hold whatever a future platform's per-conversation state turns
 * out to need too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('external_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
