<?php

namespace App\Services\MessagingServices\XChat;

use App\Models\Messaging\MessageChannel;
use App\Models\SocialAccount;
use App\Services\MessagingServices\XMessagingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
    // 3 MB, as in X's reference client (chat-xdk examples x_api upload_media).
    private const SEGMENT_BYTES = 3 * 1024 * 1024;

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

        return $this->storeFile(base64_decode($plain['plaintext_b64'], true), $plain['mime_type'] ?: 'application/octet-stream', $attachment['file_name'] ?? null);
    }

    /**
     * A regular (unencrypted) DM with media shows up in an X Chat
     * conversation as text only, with a t.co link in place of the media.
     * The link redirects to x.com/messages/media/{dm_event_id}: fetch that
     * DM event's media (GET /2/dm_events/{id}), store it as a real
     * attachment and drop the link from the body. Links that can't be
     * resolved are left in the text, so nothing is lost.
     *
     * @return array{body: string, attachments: array}
     */
    public function resolveDmMediaLinks(SocialAccount $account, string $body): array
    {
        $attachments = [];

        if (!preg_match_all('#https?://t\.co/[A-Za-z0-9]+#', $body, $matches)) {
            return ['body' => $body, 'attachments' => []];
        }

        foreach (array_unique($matches[0]) as $link) {
            try {
                $location = (string) Http::withoutRedirecting()->timeout(10)->get($link)->header('Location');
                if (!preg_match('#(?:x|twitter)\.com/messages/media/(\d{1,19})#', $location, $m)) {
                    continue; // an ordinary link, not DM media
                }

                // 1. Sent from this app (regular-DM fallback): the file is
                //    already in our storage, on the outbound message whose
                //    external_message_id is this dm_event_id.
                // 2. Otherwise ask X. X's DM media host (ton.twitter.com)
                //    only accepts OAuth 1.0a, so with the OAuth 2.0 token
                //    this usually fails (403) - kept for when it doesn't.
                $files = $this->ownSentFiles($m[1]) ?: $this->fetchDmEventMedia($account, $m[1]);

                $body = $files
                    ? trim(str_replace($link, '', $body))
                    : trim(str_replace($link, "[Media - open X to view: {$link}]", $body));
                array_push($attachments, ...$files);
            } catch (\Throwable $e) {
                Log::warning('X Chat: DM media link could not be resolved.', [
                    'social_account_id' => $account->id,
                    'error'             => class_basename($e) . ': ' . Str::limit($e->getMessage(), 200),
                ]);
            }
        }

        return ['body' => $body, 'attachments' => $attachments];
    }

    /** Files of a message this app sent as DM event $eventId. */
    private function ownSentFiles(string $eventId): array
    {
        $message = \App\Models\Messaging\Message::where('external_message_id', $eventId)
            ->where('direction', 'outbound')
            ->with('attachments')
            ->first();

        return $message
            ? $message->attachments->map(fn ($a) => $a->only(['type', 'url', 'mime_type', 'file_name', 'file_size']))->all()
            : [];
    }

    /** @return array<int, array{type: string, url: string, mime_type: string, file_name: string, file_size: int}> */
    private function fetchDmEventMedia(SocialAccount $account, string $eventId): array
    {
        $token = $this->accessToken($account);
        $response = Http::withToken($token)->timeout(20)->acceptJson()->get(self::API . "dm_events/{$eventId}", [
            'dm_event.fields' => 'attachments',
            'expansions'      => 'attachments.media_keys',
            'media.fields'    => 'type,url,preview_image_url,variants',
        ]);

        if (!$response->successful()) {
            Log::warning('X Chat: DM event for a media link could not be read.', ['social_account_id' => $account->id, 'status' => $response->status(), 'title' => $response->json('title')]);

            return [];
        }

        $files = [];
        foreach ($response->json('includes.media') ?? [] as $media) {
            // Photos: url. Videos/GIFs: the best MP4 variant.
            $url = $media['url'] ?? collect($media['variants'] ?? [])
                ->where('content_type', 'video/mp4')
                ->sortByDesc(fn ($v) => (int) ($v['bit_rate'] ?? 0))
                ->value('url');

            if (!$url) {
                continue;
            }

            // DM media is private: X serves it to the participants' token.
            $download = Http::withToken($token)->timeout(60)->get($url);
            if (!$download->successful() || strlen($download->body()) > self::MAX_BYTES) {
                Log::warning('X Chat: DM media download failed.', ['social_account_id' => $account->id, 'status' => $download->status()]);
                continue;
            }

            $bytes = $download->body();
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
            $files[] = $this->storeFile($bytes, $mime, basename((string) parse_url($url, PHP_URL_PATH)));
        }

        return $files;
    }

    private function storeFile(string $bytes, string $mime, ?string $fileName): array
    {
        $fileName = $this->safeFileName($fileName, $mime);
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
        // The media upload endpoints take the COLON form of the conversation
        // id in the request body ("A:B") - the hyphen form is only for URL
        // paths (download / send). Sending "A-B" here made X answer 503.
        // Matches X's reference client (chat-xdk examples, upload_media).
        $bodyConversationId = XChatKeyService::canonicalConversationId($conversationId);
        $token = $this->accessToken($account);

        $init = $this->postWithRetry($token, 'chat/media/upload/initialize', [
            'conversation_id' => $bodyConversationId,
            'total_bytes'     => strlen($ciphertext),
        ]);

        $sessionId = $init->json('data.session_id');
        $mediaHashKey = $init->json('data.media_hash_key');

        if ($init->status() === 503) {
            // X-side: the same token is accepted by /2/media/upload, only the
            // X Chat media service refuses (see messaging:x-chat-diagnose).
            // XMessagingService::sendMessage() falls back to a regular DM on this reason.
            throw new XChatException('chat_media_unavailable', 'X is not accepting encrypted file uploads right now (HTTP 503). Send the message as text, or send the file from the X app.');
        }

        if (!$init->successful() || !$sessionId || !$mediaHashKey) {
            throw new XChatException('send_failed', 'X media upload could not start (HTTP ' . $init->status() . '): ' . ($init->json('detail') ?? $init->json('title') ?? 'unknown error') . $this->scopeHint($init->status()));
        }

        $parts = str_split($ciphertext, self::SEGMENT_BYTES);
        foreach ($parts as $index => $segment) {
            // JSON body with base64 segment bytes (the documented JSON form).
            $append = $this->postWithRetry($token, "chat/media/upload/{$sessionId}/append", [
                'conversation_id' => $bodyConversationId,
                'media_hash_key'  => $mediaHashKey,
                'segment_index'   => (string) $index,
                'media'           => base64_encode($segment),
            ], 120);

            if (!$append->successful()) {
                throw new XChatException('send_failed', "X media upload failed at part {$index} (HTTP {$append->status()}).");
            }
        }

        $finalize = $this->postWithRetry($token, "chat/media/upload/{$sessionId}/finalize", [
            'conversation_id' => $bodyConversationId,
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

    /**
     * POST to the X API, retrying transient 5xx/429 responses with backoff
     * (X's media guide: "Retry transient 5xx with backoff"). Logs the
     * response title/detail of a final failure - never request bodies.
     *
     * A failed response is RETURNED, not thrown (throw: false), so callers
     * can map it to an XChatException with a readable message.
     */
    private function postWithRetry(string $token, string $path, array $body, int $timeout = 30): \Illuminate\Http\Client\Response
    {
        $attempts = 0;

        $response = Http::withToken($token)
            ->timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->beforeSending(function () use (&$attempts) {
                $attempts++; // runs once per attempt, retries included
            })
            // 3 attempts: wait 1s before the 2nd, 3s before the 3rd. Laravel
            // calls `when` with ($exception, $pendingRequest) - the failed
            // response is $exception->response, never a second argument.
            ->retry([1000, 3000], when: fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false)
            ->post(self::API . $path, $body);

        if (!$response->successful()) {
            Log::warning('X Chat media API call failed.', [
                'path'              => preg_replace('#/upload/[^/]+/#', '/upload/{session}/', $path),
                'status'            => $response->status(),
                'x_transaction_id'  => $response->header('x-transaction-id') ?: null,
                'attempts'          => $attempts,
                'title'             => $response->json('title'),
                'detail'            => Str::limit((string) ($response->json('detail') ?? $response->body()), 300),
            ]);
        }

        return $response;
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
