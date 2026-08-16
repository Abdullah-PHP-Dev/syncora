<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProcessInboundMessage already skips a duplicate delivery via an
 * explicit `Message::where('external_message_id', ...)->exists()` check
 * before its transaction - the right call for messages (an immutable
 * event should be skipped outright, not merged the way updateOrCreate()
 * would). But like message_channels, that guard was only ever
 * application-level: nothing stopped two overlapping queue workers (eg.
 * during a Gateway reconnect replaying an event, see
 * RunDiscordGatewayListener) from both passing the exists() check before
 * either had inserted. Scoped to (conversation_id, external_message_id)
 * rather than external_message_id alone, since two different platforms'
 * ID schemes could otherwise coincidentally collide on the same string.
 * MySQL treats each NULL as distinct in a unique index, so the many
 * platforms/rows with no external_message_id are unaffected.
 *
 * Deduplicates first (keeping the lowest/oldest id per group), same
 * reasoning as the message_channels migration alongside this one - not
 * reversible in down().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            DELETE t1 FROM messages t1
            INNER JOIN messages t2
                ON t1.conversation_id = t2.conversation_id
                AND t1.external_message_id = t2.external_message_id
            WHERE t1.id > t2.id
                AND t1.external_message_id IS NOT NULL
        ');

        Schema::table('messages', function (Blueprint $table) {
            $table->unique(['conversation_id', 'external_message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'external_message_id']);
        });
    }
};
