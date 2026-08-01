<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only six of the twelve platforms in this module let a bot edit or
 * delete a message it already sent (Telegram, Discord, Slack, Teams,
 * Google Chat, Matrix - confirmed against each platform's own API docs;
 * Facebook/Instagram/WhatsApp/X/LINE/Zalo have no such endpoint at all).
 * edited_at/deleted_at are kept generic - not platform-specific columns -
 * since the concept ("this outbound message was later changed/removed")
 * is the same everywhere it's supported; MessagingManagerService is what
 * knows which platforms it applies to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('read_at');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }
};
