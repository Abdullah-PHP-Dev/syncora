<?php

namespace App\Services\MessagingServices\XChat;

use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * X Chat attachments (https://docs.x.com/xchat/media). Media in X Chat is
 * end-to-end encrypted with the same conversation key as the text:
 *
 *  inbound:  GET /2/chat/media/{conversation}/{media_hash_key} (ciphertext)
 *            -> worker decryptStream with the MESSAGE's key version
 *            -> plain file stored on R2 -> normal message_attachments row
 *  outbound: file bytes -> worker encryptStream (latest key)
 *            -> POST /2/chat/media/upload/initialize | {id}/append | {id}/finalize
 *            -> media_hash_key -> attached to the encrypted message
 *
 * Uploading needs the media.write scope (part of
 * XMessagingService::OAUTH_SCOPES).
 */
class XChatMediaService
{
    private const API = 'https://api.x.com/2/';
    private const MAX_BYTES = 25 * 1024 * 1024;
    private const SEGMENT_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private XChatWorkerClient $worker,
        private XChatKeyService $keys,
    ) {
    }

    /**
     * Download + decrypt a decrypted event's attachments.
     *
     * @param array<int, array<string, mixed>> $attachments normalized by the worker
     * @return array{attachments: array<int, array{type: string, url: string, mime_type: ?string, file_name: ?string, file_size: ?int}>, links: string[], failed: int}
     */
    public function fetchAttachments(SocialAccount $account, string $conversationId, ?string $keyVersion, array $attachments): array
    {
        $result = ['attachments' => [], 'links' => [], 'failed' => 0];

        foreach ($attachments as $attachment) {
            $kind = $attachment['attachment_type'] ?? null;

            // Link previews and shared posts carry a URL, not encrypted bytes.
            if (in_array($kind, ['url', 'post'], true) || (empty($attachment['media_hash_key']) && !empty($attachment['url']))) {
                if (!empty($attachment['url'])) {
                    $result['links'][] = (string) $attachment['url'];
                }
                continue;
            }

            if (empty($attachment['media_hash_key']) || !$keyVersion) {
                $result['failed']++;
                continue;
            }

            try {
                $result['attachments'][] = $this->fetchOne($account, $conversationId, $keyVersion, $attachment);
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::warning('X Chat: attachment could not be downloaded/decrypted.', [
                    'social_account_id' => $account->id,
                    'reason'            => $e instanceof XChatException ? $e->reason : class_basename($e),
                    'message'           => Str::limit($e->getMessage(), 200),
                ]);
            }
        }

        return $result;
    }

    private function fetchOne(SocialAccount $account, string $conversationId, string $keyVersion, array $attachment): array
    {
        $path = str_replace(':', '-', XChatKeyService::canonicalConversationId($conversationId));
        $response = Http::withToken($this->accessToken($account))
            ->timeout(60)
            ->get(self::API . "chat/media/{$path}/" . rawurlencode($attachment['media_hash_key']));

        if (!$response->successful()) {
            throw new XChatException('media_unavailable', 'X media download failed (HTTP ' . $response->status() . ').');
        }

        $ciphertext = $response->body();
        if (strlen($ciphertext) > self::MAX_BYTES) {
            throw new XChatException('media_unavailable', 'Attachment is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.');
        }

        $plain = $this->withSession($account, fn () => $this->worker->decryptMedia(
            $account->id,
            $ciphertext,
            $keyVersion,
            $this->keys->keyChangeEventsFor($conversationId),
            $this->keys->signingKeysFor($account, $this->participants($account, $conversationId))
        ));

        $bytes = base64_decode($plain['plaintext_b64'], true);
        $mime = $plain['mime_type'] ?: 'application/octet-stream';
        $fileName = $this->safeFileName($attachment['file_name'] ?? null, $mime);
        $storagePath = 'uploads/messaging/x/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);

        Storage::disk('r2')->put($storagePath, $bytes, ['visibility' => 'public', 'ContentType' => $mime]);

        return [
            'type'      => $this->messageType($mime),
            'url'       => Storage::disk('r2')->url($storagePath),
            'mime_type' => $mime,
            'file_name' => $fileName,
            'file_size' => strlen($bytes),
        ];
    }

    /**
     * Encrypt + upload a file for an outgoing X Chat message.
     *
     * @return array{attachment_type: string, media_hash_key: string, width: int, height: int, filesize_bytes: int, filename: string}
     */
    public function uploadForMessage(SocialAccount $account, string $conversationId, string $bytes, string $fileName): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new XChatException('send_failed', 'File is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.');
        }

        $encrypted = $this->withSession($account, fn () => $this->worker->encryptMedia(
            $account->id,
            $bytes,
            $this->keys->keyChangeEventsFor($conversationId),
            $this->keys->signingKeysFor($account, $this->participants($account, $conversationId))
        ));

        $ciphertext = base64_decode($encrypted['ciphertext_b64'], true);
        $apiConversationId = str_replace(':', '-', XChatKeyService::canonicalConversationId($conversationId));
        $token = $this->accessToken($account);

        $init = Http::withToken($token)->timeout(30)->post(self::API . 'chat/media/upload/initialize', [
            'conversation_id' => $apiConversationId,
            'total_bytes'     => strlen($ciphertext),
        ]);
        $sessionId = $init->json('data.session_id');
        $mediaHashKey = $init->json('data.media_hash_key');

        if (!$init->successful() || !$sessionId || !$mediaHashKey) {
            throw new XChatException('send_failed', 'X media upload could not start (HTTP ' . $init->status() . '): ' . ($init->json('detail') ?? $init->json('title') ?? 'unknown error') . $this->scopeHint($init->status()));
        }

        $parts = str_split($ciphertext, self::SEGMENT_BYTES);
        foreach ($parts as $index => $segment) {
            $append = Http::withToken($token)->timeout(120)
                ->attach('media', $segment, 'segment.bin')
                ->post(self::API . "chat/media/upload/{$sessionId}/append", [
                    'conversation_id' => $apiConversationId,
                    'media_hash_key'  => $mediaHashKey,
                    'segment_index'   => $index,
                ]);

            if (!$append->successful()) {
                throw new XChatException('send_failed', "X media upload failed at part {$index} (HTTP {$append->status()}).");
            }
        }

        $finalize = Http::withToken($token)->timeout(60)->post(self::API . "chat/media/upload/{$sessionId}/finalize", [
            'conversation_id' => $apiConversationId,
            'media_hash_key'  => $mediaHashKey,
            'num_parts'       => (string) count($parts),
        ]);

        if (!$finalize->successful()) {
            throw new XChatException('send_failed', 'X media upload could not be finalized (HTTP ' . $finalize->status() . ').');
        }

        Log::info('X Chat: media uploaded.', ['social_account_id' => $account->id, 'mime_type' => $encrypted['mime_type'], 'size' => $encrypted['plaintext_size']]);

        return [
            'attachment_type' => 'media',
            'media_hash_key'  => $mediaHashKey,
            'width'           => (int) $encrypted['width'],
            'height'          => (int) $encrypted['height'],
            'filesize_bytes'  => (int) $encrypted['plaintext_size'],
            'filename'        => $this->safeFileName($fileName, $encrypted['mime_type']),
        ];
    }

    /** Run a worker call, unlocking the account's session once if the worker restarted. */
    private function withSession(SocialAccount $account, callable $call): array
    {
        try {
            return $call();
        } catch (XChatException $e) {
            if ($e->reason !== 'session_locked') {
                throw $e;
            }
            $this->keys->unlockSession($account);

            return $call();
        }
    }

    /** @return array<string, null> x_user_id => any key version */
    private function participants(SocialAccount $account, string $conversationId): array
    {
        $ids = [(string) $account->platform_account_id => null];
        foreach (explode(':', XChatKeyService::canonicalConversationId($conversationId)) as $id) {
            if (ctype_digit($id)) {
                $ids[$id] = null;
            }
        }

        return $ids;
    }

    private function messageType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default                          => 'file',
        };
    }

    private function safeFileName(?string $name, string $mime): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $name)));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($name === '' || $ext === '') {
            $guessed = (new \Symfony\Component\Mime\MimeTypes())->getExtensions($mime)[0] ?? 'bin';
            $name = ($name !== '' ? $name : 'attachment') . '.' . $guessed;
        }

        return Str::limit($name, 200, '');
    }

    private function scopeHint(int $status): string
    {
        return $status === 403 ? ' Reconnect the X account in Channels so it has the media.write permission.' : '';
    }

    private function accessToken(SocialAccount $account): string
    {
        $channel = MessageChannel::where('social_account_id', $account->id)->where('platform', 'x')->first();

        return $channel ? app(XMessagingService::class)->ensureFreshToken($channel) : (string) $account->access_token;
    }
}
