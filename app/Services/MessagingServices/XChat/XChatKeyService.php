<?php

namespace App\Services\MessagingServices\XChat;

use App\Models\Messaging\MessageChannel;
use App\Models\Messaging\XChatConversationKeyEvent;
use App\Models\Messaging\XChatCredential;
use App\Models\Messaging\XChatPublicKey;
use App\Models\SocialAccount;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Key material the X Chat XDK needs, minus anything private:
 *  - participants' PUBLIC identity/signing keys (GET /2/users/{id}/public_keys),
 *    cached per key version and refetched when a webhook references a
 *    version we haven't seen (key rotation);
 *  - conversation key-change events (wrapped keys - ciphertext), recorded
 *    from webhooks and backfilled from GET /2/chat/conversations/{id}/events
 *    when missing;
 *  - the account's own X Chat credential (encrypted PIN + juicebox_config),
 *    validated by actually unlocking through the worker before it's saved.
 */
class XChatKeyService
{
    private const API = 'https://api.x.com/2/';

    public function __construct(private XChatWorkerClient $worker)
    {
    }

    // ------------------------------------------------------------------
    // Account credential (PIN)
    // ------------------------------------------------------------------

    /**
     * Save + verify the account owner's X Chat PIN. The PIN is only stored
     * after the XDK successfully recovers the keys with it, so a wrong PIN
     * never lands in the database (and a typo doesn't silently break every
     * future webhook).
     */
    public function enable(SocialAccount $account, string $pin): XChatCredential
    {
        $own = $this->fetchOwnKeyRecord($account);

        $this->worker->unlock($account->id, (string) $account->platform_account_id, $own['public_key_version'], $own['juicebox_config'], $pin);

        $credential = XChatCredential::updateOrCreate(
            ['social_account_id' => $account->id],
            [
                'x_user_id'          => (string) $account->platform_account_id,
                'pin'                => $pin,
                'juicebox_config'    => $own['juicebox_config'],
                'public_key_version' => $own['public_key_version'],
                'status'             => 'active',
                'last_error'         => null,
                'verified_at'        => now(),
            ]
        );

        Log::info('X Chat enabled for account.', ['social_account_id' => $account->id, 'public_key_version' => $own['public_key_version']]);

        return $credential;
    }

    public function disable(SocialAccount $account): void
    {
        XChatCredential::where('social_account_id', $account->id)->delete();
        $this->worker->lock($account->id);
    }

    public function credentialFor(SocialAccount $account): ?XChatCredential
    {
        return XChatCredential::where('social_account_id', $account->id)->first();
    }

    /**
     * Unlock the worker session from the stored credential. Called on a
     * session_locked response (worker restart / idle timeout). The
     * juicebox_config is refreshed first: its realm auth tokens can expire.
     */
    public function unlockSession(SocialAccount $account): void
    {
        $credential = $this->credentialFor($account);

        if (!$credential) {
            throw new XChatException('not_configured', 'X Chat PIN has not been provided for this account.');
        }

        try {
            $own = $this->fetchOwnKeyRecord($account);
            $credential->forceFill(['juicebox_config' => $own['juicebox_config'], 'public_key_version' => $own['public_key_version']])->save();
        } catch (XChatException $e) {
            Log::warning('X Chat: could not refresh juicebox_config, using the stored one.', ['social_account_id' => $account->id, 'reason' => $e->reason]);
        }

        try {
            $this->worker->unlock($account->id, $credential->x_user_id, (string) $credential->public_key_version, $credential->juicebox_config, $credential->pin);
            $credential->forceFill(['status' => 'active', 'last_error' => null])->save();
        } catch (XChatException $e) {
            if ($e->reason === 'unlock_failed') {
                // PIN changed on X or backup reset - stop retrying with it.
                $credential->forceFill(['status' => 'error', 'last_error' => 'X Chat unlock failed - re-enter the X Chat PIN.'])->save();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Participant public/signing keys
    // ------------------------------------------------------------------

    /**
     * SigningKeyEntry list for the given users. $required maps
     * x_user_id => the public_key_version a webhook referenced; a version not
     * yet cached triggers one refetch for that user (key rotation).
     *
     * @param array<string, string|null> $required
     */
    public function signingKeysFor(SocialAccount $account, array $required): array
    {
        $entries = [];

        foreach ($required as $userId => $version) {
            $userId = (string) $userId;
            $query = XChatPublicKey::where('x_user_id', $userId);
            $hasVersion = $version ? (clone $query)->where('public_key_version', $version)->exists() : $query->exists();

            if (!$hasVersion) {
                $this->refreshPublicKeys($account, $userId);
            }

            foreach (XChatPublicKey::where('x_user_id', $userId)->get() as $key) {
                $entries[] = $key->toSigningKeyEntry();
            }
        }

        return $entries;
    }

    public function refreshPublicKeys(SocialAccount $account, string $userId): void
    {
        $records = $this->getPublicKeys($account, $userId);

        foreach ($records as $record) {
            if (empty($record['public_key_version']) || empty($record['signing_public_key']) || empty($record['public_key'])) {
                continue;
            }

            XChatPublicKey::updateOrCreate(
                ['x_user_id' => $userId, 'public_key_version' => (string) $record['public_key_version']],
                [
                    'public_key'                    => $record['public_key'],
                    'signing_public_key'            => $record['signing_public_key'],
                    'identity_public_key_signature' => $record['identity_public_key_signature'] ?? '',
                    'fetched_at'                    => now(),
                ]
            );
        }

        Log::info('X Chat public keys refreshed.', ['x_user_id' => $userId, 'versions' => count($records)]);
    }

    // ------------------------------------------------------------------
    // Conversation key-change events
    // ------------------------------------------------------------------

    public function recordKeyChangeEvent(string $conversationId, ?string $keyVersion, string $encodedEvent): void
    {
        XChatConversationKeyEvent::firstOrCreate(
            ['conversation_id' => self::canonicalConversationId($conversationId), 'event_hash' => hash('sha256', $encodedEvent)],
            ['key_version' => $keyVersion, 'encoded_event' => $encodedEvent]
        );
    }

    /** @return string[] base64 key-change events, oldest first */
    public function keyChangeEventsFor(string $conversationId): array
    {
        return XChatConversationKeyEvent::where('conversation_id', self::canonicalConversationId($conversationId))
            ->orderBy('id')
            ->pluck('encoded_event')
            ->all();
    }

    /**
     * Pull the conversation's key-change events from X (used when a message
     * references a key version we never received a key change for, eg. the
     * conversation predates the webhook subscription).
     */
    public function backfillKeyChangeEvents(SocialAccount $account, string $conversationId): int
    {
        $token = $this->accessToken($account);
        $path = str_replace(':', '-', self::canonicalConversationId($conversationId));

        $response = Http::withToken($token)->timeout(20)->get(self::API . "chat/conversations/{$path}/events", ['max_results' => 50]);

        if (!$response->successful()) {
            Log::warning('X Chat key-event backfill failed.', ['social_account_id' => $account->id, 'status' => $response->status()]);

            return 0;
        }

        $events = $response->json('meta.conversation_key_events') ?? [];

        foreach ($events as $event) {
            if (is_string($event) && $event !== '') {
                $this->recordKeyChangeEvent($conversationId, null, $event);
            }
        }

        return count($events);
    }

    /** "A-B" (URL form) and "A:B" (event form) refer to the same 1:1 conversation. */
    public static function canonicalConversationId(string $conversationId): string
    {
        return str_replace('-', ':', $conversationId);
    }

    // ------------------------------------------------------------------
    // X API
    // ------------------------------------------------------------------

    /** @return array{public_key_version: string, juicebox_config: ?string} */
    private function fetchOwnKeyRecord(SocialAccount $account): array
    {
        $records = $this->getPublicKeys($account, (string) $account->platform_account_id);

        if (!$records) {
            throw new XChatException('public_keys_unavailable', 'This X account has no X Chat keys yet - open Messages in the X app once to set up encrypted chat, then try again.');
        }

        $latest = collect($records)->sortByDesc(fn ($r) => (int) ($r['public_key_version'] ?? 0))->first();

        return [
            'public_key_version' => (string) $latest['public_key_version'],
            'juicebox_config'    => isset($latest['juicebox_config']) ? json_encode($latest['juicebox_config']) : null,
        ];
    }

    private function getPublicKeys(SocialAccount $account, string $userId): array
    {
        $response = Http::withToken($this->accessToken($account))->timeout(20)->get(self::API . "users/{$userId}/public_keys", [
            'public_key.fields' => 'public_key,signing_public_key,identity_public_key_signature,public_key_version,juicebox_config',
        ]);

        if (!$response->successful()) {
            throw new XChatException('public_keys_unavailable', 'X public-keys request failed (HTTP ' . $response->status() . ').');
        }

        $data = $response->json('data') ?? [];

        return array_is_list($data) ? $data : [$data];
    }

    private function accessToken(SocialAccount $account): string
    {
        $channel = MessageChannel::where('social_account_id', $account->id)->where('platform', 'x')->first();

        // Resolved lazily: XMessagingService itself depends on the X Chat
        // services, so constructor injection here would loop.
        return $channel ? app(XMessagingService::class)->ensureFreshToken($channel) : (string) $account->access_token;
    }
}
