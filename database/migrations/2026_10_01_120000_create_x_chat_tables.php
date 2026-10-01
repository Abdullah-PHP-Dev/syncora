<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Storage for decrypting X Chat (end-to-end encrypted DM) webhooks with
     * X's official Chat XDK - see App\Services\MessagingServices\XChat.
     *
     *  - x_chat_credentials: per connected X account, what the XDK needs to
     *    recover that account's private keys from X's secure key backup:
     *    the account owner's X Chat PIN and the juicebox_config returned by
     *    GET /2/users/{id}/public_keys. Both columns use Laravel's
     *    `encrypted` cast (APP_KEY). Raw private keys are never stored: the
     *    XDK worker recovers them into memory only.
     *  - x_chat_public_keys: participants' PUBLIC identity/signing keys per
     *    key version (needed to verify senders). Public data, cached so
     *    every webhook doesn't refetch them; a new version is fetched on
     *    rotation.
     *  - x_chat_conversation_key_events: the base64 conversation_key_change
     *    events seen per conversation. These hold the conversation key
     *    *wrapped* (ECIES) to each participant - ciphertext, not raw keys -
     *    and are replayed into the XDK so it can verify and unwrap the key.
     *  - webhook_event_receipts: one row per delivered webhook event uuid
     *    (unique) for idempotency.
     *  - messages.meta: the encrypted event kept on a message whose
     *    decryption is pending (eg. before the PIN was provided), so it can
     *    be decrypted later instead of being lost.
     */
    public function up(): void
    {
        Schema::create('x_chat_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('x_user_id', 32);
            $table->text('pin');                       // encrypted cast
            $table->longText('juicebox_config')->nullable(); // encrypted cast (holds realm auth tokens)
            $table->string('public_key_version', 32)->nullable();
            $table->string('status', 20)->default('active'); // active | error
            $table->string('last_error', 500)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('x_chat_public_keys', function (Blueprint $table) {
            $table->id();
            $table->string('x_user_id', 32);
            $table->string('public_key_version', 32);
            $table->text('public_key');
            $table->text('signing_public_key');
            $table->text('identity_public_key_signature');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['x_user_id', 'public_key_version']);
        });

        Schema::create('x_chat_conversation_key_events', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 64);
            // Known from webhooks (conversation_key_version); null for events
            // backfilled from GET /2/chat/conversations/{id}/events, whose
            // meta.conversation_key_events carries no version.
            $table->string('key_version', 32)->nullable();
            $table->char('event_hash', 64);   // sha256 of encoded_event - dedupe key
            $table->longText('encoded_event');
            $table->timestamps();

            $table->unique(['conversation_id', 'event_hash']);
        });

        Schema::create('webhook_event_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->string('event_uuid', 100);
            $table->timestamp('received_at');

            $table->unique(['platform', 'event_uuid']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
        Schema::dropIfExists('webhook_event_receipts');
        Schema::dropIfExists('x_chat_conversation_key_events');
        Schema::dropIfExists('x_chat_public_keys');
        Schema::dropIfExists('x_chat_credentials');
    }
};
