<?php

namespace App\Services\MessagingServices\XChat;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

/**
 * Turns an encrypted X Chat webhook payload (chat.received / chat.sent:
 * data.payload) into the verified, decrypted event, via X's official Chat
 * XDK running in the worker:
 *
 *   conversation_key_change_event (if present) -> recorded FIRST
 *   participants' signing keys (cached; refetched on new versions)
 *   worker: decryptEvents(key changes) -> verified conversation key
 *           decryptEvent(encoded_event)  -> verified message
 *
 * Self-healing retries, each at most once per call:
 *   session_locked            -> unlock from the stored PIN, retry
 *   missing_conversation_key  -> backfill key events from X, retry
 *   signature_invalid         -> refetch the sender's public keys, retry
 */
class XChatDecryptionService
{
    public function __construct(
        private XChatWorkerClient $worker,
        private XChatKeyService $keys,
    ) {
    }

    /**
     * @param array<string, mixed> $payload data.payload of the webhook
     * @return array<string, mixed> normalized event (type, message_id, sender_id, conversation_id, content_type, text, verified, ...)
     */
    public function decryptIncomingEvent(array $payload, SocialAccount $account): array
    {
        $encodedEvent = $payload['encoded_event'] ?? null;
        $conversationId = $payload['conversation_id'] ?? null;

        if (!is_string($encodedEvent) || $encodedEvent === '' || !is_string($conversationId) || $conversationId === '') {
            throw new XChatException('malformed_event', 'Payload is missing encoded_event or conversation_id.');
        }

        $context = ['social_account_id' => $account->id, 'conversation_id' => $conversationId, 'event_id' => $payload['id'] ?? null];
        Log::info('X Chat: encrypted event received.', $context);

        if (!empty($payload['conversation_key_change_event']) && is_string($payload['conversation_key_change_event'])) {
            $this->keys->recordKeyChangeEvent($conversationId, $payload['conversation_key_version'] ?? null, $payload['conversation_key_change_event']);
            Log::info('X Chat: conversation key-change event recorded.', $context + ['key_version' => $payload['conversation_key_version'] ?? null]);
        }

        if (!$this->keys->credentialFor($account)) {
            throw new XChatException('not_configured', 'X Chat PIN has not been provided for this account.');
        }

        $required = $this->requiredSigners($payload, $account);
        $retried = [];

        while (true) {
            try {
                $signingKeys = $this->keys->signingKeysFor($account, $required);
                $result = $this->worker->decrypt($account->id, $encodedEvent, $this->keys->keyChangeEventsFor($conversationId), $signingKeys);
                $event = $result['event'];

                Log::info('X Chat: decryption successful.', $context + [
                    'type'         => $event['type'] ?? null,
                    'content_type' => $event['content_type'] ?? null,
                    'verified'     => $event['verified'] ?? false,
                ]);

                return $event;
            } catch (XChatException $e) {
                if (isset($retried[$e->reason])) {
                    throw $e;
                }
                $retried[$e->reason] = true;

                Log::notice('X Chat: decryption attempt failed, recovering.', $context + ['reason' => $e->reason]);

                match ($e->reason) {
                    'session_locked'           => $this->keys->unlockSession($account),
                    'missing_conversation_key' => $this->keys->backfillKeyChangeEvents($account, $conversationId),
                    'signature_invalid'        => $this->refreshSigners($account, array_keys($required)),
                    default                    => throw $e,
                };
            }
        }
    }

    /**
     * x_user_id => public_key_version for everyone whose signature the XDK
     * must verify: the sender (and any listed signers) plus this account
     * itself (key changes in a 1:1 are signed by either participant).
     *
     * @return array<string, string|null>
     */
    private function requiredSigners(array $payload, SocialAccount $account): array
    {
        $required = [(string) $account->platform_account_id => null];

        if (!empty($payload['sender_id'])) {
            $required[(string) $payload['sender_id']] = $payload['message_event_signature']['public_key_version'] ?? null;
        }

        foreach ($payload['message_event_signature']['message_signing_key_info_list'] ?? [] as $info) {
            if (!empty($info['member_id'])) {
                $required[(string) $info['member_id']] = $info['public_key_version'] ?? ($required[(string) $info['member_id']] ?? null);
            }
        }

        // Participants of a 1:1 conversation id "A:B".
        foreach (explode(':', XChatKeyService::canonicalConversationId($payload['conversation_id'] ?? '')) as $participant) {
            if (ctype_digit($participant) && !array_key_exists($participant, $required)) {
                $required[$participant] = null;
            }
        }

        return $required;
    }

    private function refreshSigners(SocialAccount $account, array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->keys->refreshPublicKeys($account, (string) $userId);
        }
    }
}
