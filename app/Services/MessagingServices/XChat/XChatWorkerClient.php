<?php

namespace App\Services\MessagingServices\XChat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the localhost X Chat worker (xchat-worker/server.mjs),
 * which runs X's official Chat XDK. Laravel never performs X Chat
 * cryptography itself - X ships no PHP Chat XDK - it only passes encrypted
 * payloads, public keys and (for unlock) the stored PIN to the worker.
 */
class XChatWorkerClient
{
    public function unlock(int $sessionId, string $userId, string $signingKeyVersion, ?string $juiceboxConfig, string $pin): void
    {
        $this->post('/v1/sessions/unlock', [
            'session_id'          => (string) $sessionId,
            'user_id'             => $userId,
            'signing_key_version' => $signingKeyVersion,
            'juicebox_config'     => $juiceboxConfig,
            'pin'                 => $pin,
        ]);
    }

    public function lock(int $sessionId): void
    {
        try {
            $this->post('/v1/sessions/lock', ['session_id' => (string) $sessionId]);
        } catch (XChatException) {
            // Worker down = nothing unlocked in memory anyway.
        }
    }

    /**
     * @return array{event: array<string, mixed>, keyVersions: string[], keyChangeErrors: int}
     */
    public function decrypt(int $sessionId, string $event, array $keyChangeEvents, array $signingKeys): array
    {
        return $this->post('/v1/decrypt', [
            'session_id'        => (string) $sessionId,
            'event'             => $event,
            'key_change_events' => array_values($keyChangeEvents),
            'signing_keys'      => array_values($signingKeys),
        ]);
    }

    /**
     * @return array{message_id: string, encoded_message_create_event: string, encoded_message_event_signature: string, conversation_key_version: string}
     */
    public function encrypt(int $sessionId, string $conversationId, string $text, array $keyChangeEvents, array $signingKeys): array
    {
        return $this->post('/v1/encrypt', [
            'session_id'        => (string) $sessionId,
            'conversation_id'   => $conversationId,
            'text'              => $text,
            'key_change_events' => array_values($keyChangeEvents),
            'signing_keys'      => array_values($signingKeys),
        ])['payload'];
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(3)->get(rtrim(config('services.xchat_worker.url'), '/') . '/health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function post(string $path, array $body): array
    {
        $token = config('services.xchat_worker.token');

        if (!$token) {
            throw new XChatException('worker_unavailable', 'XCHAT_WORKER_TOKEN is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(config('services.xchat_worker.timeout', 20))
                ->acceptJson()
                ->post(rtrim(config('services.xchat_worker.url'), '/') . $path, $body);
        } catch (ConnectionException $e) {
            throw new XChatException('worker_unavailable', 'X Chat worker is not reachable: ' . $e->getMessage());
        }

        if ($response->successful()) {
            return $response->json();
        }

        throw new XChatException(
            $response->json('error') ?? 'worker_error',
            $response->json('message') ?? ('X Chat worker returned HTTP ' . $response->status())
        );
    }
}
