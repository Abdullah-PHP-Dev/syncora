<?php

namespace App\Services\MessagingServices\XChat;

use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outgoing X Chat (encrypted) messages: the reply is encrypted + signed by
 * the X Chat XDK (worker) under the conversation's current verified key,
 * then posted to POST /2/chat/conversations/{id}/messages. Plaintext never
 * goes to the X Chat API.
 *
 * Replies only: starting a brand-new encrypted conversation needs a fresh
 * conversation-key exchange (prepareConversationKeyChange + POST .../keys),
 * which the inbox never does - customers always message first.
 */
class XChatService
{
    private const API = 'https://api.x.com/2/';

    public function __construct(
        private XChatWorkerClient $worker,
        private XChatKeyService $keys,
    ) {
    }

    /**
     * @param array{url: string, file_name?: ?string}|null $media a file to attach (eg. the inbox upload on R2)
     * @return array{success: bool, external_message_id?: string, error?: string}
     */
    public function sendText(SocialAccount $account, string $conversationId, string $customerId, string $text, ?array $media = null): array
    {
        if (!$this->keys->credentialFor($account)) {
            return ['success' => false, 'error' => 'This X conversation is end-to-end encrypted. Enable encrypted X Chat for this account (Channels > X > Enable X Chat) to reply from the inbox.'];
        }

        $required = [(string) $account->platform_account_id => null, $customerId => null];
        $retried = [];
        $attachments = [];

        if ($media && !empty($media['url'])) {
            try {
                $file = Http::timeout(60)->get($media['url']);
                if (!$file->successful()) {
                    return ['success' => false, 'error' => 'Could not read the file to send (HTTP ' . $file->status() . ').'];
                }
                $fileName = $media['file_name'] ?? basename((string) parse_url($media['url'], PHP_URL_PATH));
                // Encrypted with the conversation key, uploaded to X's media store.
                $attachments[] = app(XChatMediaService::class)->uploadForMessage($account, $conversationId, $file->body(), $fileName);
            } catch (XChatException $e) {
                Log::warning('X Chat: media upload failed.', ['social_account_id' => $account->id, 'reason' => $e->reason]);

                return ['success' => false, 'error' => $e->getMessage(), 'reason' => $e->reason];
            }
        }

        while (true) {
            try {
                $payload = $this->worker->encrypt(
                    $account->id,
                    $conversationId,
                    $text,
                    $this->keys->keyChangeEventsFor($conversationId),
                    $this->keys->signingKeysFor($account, $required),
                    $attachments
                );
                break;
            } catch (XChatException $e) {
                if (isset($retried[$e->reason]) || !in_array($e->reason, ['session_locked', 'missing_conversation_key'], true)) {
                    Log::warning('X Chat: encrypt failed.', ['social_account_id' => $account->id, 'reason' => $e->reason]);

                    return ['success' => false, 'error' => $e->getMessage()];
                }
                $retried[$e->reason] = true;

                $e->reason === 'session_locked'
                    ? $this->keys->unlockSession($account)
                    : $this->keys->backfillKeyChangeEvents($account, $conversationId);
            }
        }

        $path = str_replace(':', '-', XChatKeyService::canonicalConversationId($conversationId));
        $response = Http::withToken($this->accessToken($account))->timeout(20)->post(self::API . "chat/conversations/{$path}/messages", [
            'message_id'                      => $payload['message_id'],
            'encoded_message_create_event'    => $payload['encoded_message_create_event'],
            'encoded_message_event_signature' => $payload['encoded_message_event_signature'],
        ]);

        if (!$response->successful()) {
            Log::warning('X Chat: send failed.', ['social_account_id' => $account->id, 'status' => $response->status(), 'title' => $response->json('title')]);

            return ['success' => false, 'error' => $response->json('detail') ?? $response->json('title') ?? 'X Chat send failed (HTTP ' . $response->status() . ').'];
        }

        Log::info('X Chat: message sent.', ['social_account_id' => $account->id, 'conversation_id' => $conversationId]);

        return ['success' => true, 'external_message_id' => $payload['message_id']];
    }

    private function accessToken(SocialAccount $account): string
    {
        $channel = MessageChannel::where('social_account_id', $account->id)->where('platform', 'x')->first();

        return $channel ? app(XMessagingService::class)->ensureFreshToken($channel) : (string) $account->access_token;
    }
}
