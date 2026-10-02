<?php

namespace App\Services\MessagingServices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Uploads a file for a LEGACY (unencrypted) X Direct Message using the v2
 * chunked media upload (https://docs.x.com/x-api/media/quickstart/media-upload-chunked):
 *
 *   POST /2/media/upload/initialize  {media_type, total_bytes, media_category: dm_*}
 *   POST /2/media/upload/{id}/append  multipart: segment_index + media (<= 5 MB chunks)
 *   POST /2/media/upload/{id}/finalize
 *   GET  /2/media/upload?command=STATUS&media_id={id}  (videos/GIFs: until succeeded)
 *
 * The returned media_id goes in the DM's attachments. Encrypted X Chat
 * conversations can't use this - their files go to X Chat's own encrypted
 * media store (XChat\XChatMediaService).
 */
class XDmMediaUploader
{
    private const API = 'https://api.x.com/2/';
    // X's server limit is 8 MB per chunk; the guide recommends <= 5 MB.
    private const CHUNK_BYTES = 4 * 1024 * 1024;
    private const MAX_STATUS_CHECKS = 20;

    /** Per media_category size limits from the guide (dm_video: default tier). */
    private const LIMITS = [
        'dm_image' => 5 * 1024 * 1024,
        'dm_gif'   => 15 * 1024 * 1024,
        'dm_video' => 512 * 1024 * 1024,
    ];

    /**
     * @return string the media_id to attach to the DM
     * @throws RuntimeException with a seller-readable message
     */
    public function upload(string $token, string $bytes, ?string $mimeType = null): string
    {
        $mimeType = $mimeType ?: ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream');
        $category = self::categoryFor($mimeType);

        if (!$category) {
            throw new RuntimeException("X Direct Messages can only send images, GIFs and videos (this file is {$mimeType}).");
        }
        if (strlen($bytes) > self::LIMITS[$category]) {
            throw new RuntimeException('File is too large for an X Direct Message (' . $category . ' limit: ' . (self::LIMITS[$category] / 1024 / 1024) . ' MB).');
        }

        $init = $this->request($token)->asJson()->post(self::API . 'media/upload/initialize', [
            'media_type'     => $mimeType,
            'total_bytes'    => strlen($bytes),
            'media_category' => $category,
        ]);
        $mediaId = (string) $init->json('data.id');

        if (!$init->successful() || $mediaId === '') {
            $this->fail('initialize', $init);
        }

        foreach (str_split($bytes, self::CHUNK_BYTES) as $index => $chunk) {
            $append = $this->request($token, 120)
                ->attach('media', $chunk, 'chunk-' . $index)
                ->post(self::API . "media/upload/{$mediaId}/append", ['segment_index' => $index]);

            if (!$append->successful()) {
                $this->fail('append', $append);
            }
        }

        $finalize = $this->request($token)->post(self::API . "media/upload/{$mediaId}/finalize");
        if (!$finalize->successful()) {
            $this->fail('finalize', $finalize);
        }

        $this->waitForProcessing($token, $mediaId, $finalize->json('data.processing_info'));

        return $mediaId;
    }

    /** dm_image / dm_gif / dm_video for a MIME type, or null if X DMs can't carry it. */
    public static function categoryFor(string $mimeType): ?string
    {
        return match (true) {
            $mimeType === 'image/gif'            => 'dm_gif',
            str_starts_with($mimeType, 'image/') => 'dm_image',
            str_starts_with($mimeType, 'video/') => 'dm_video',
            default                              => null,
        };
    }

    /** Videos/GIFs are processed asynchronously - poll STATUS until done. */
    private function waitForProcessing(string $token, string $mediaId, ?array $info): void
    {
        for ($check = 0; $info && $check < self::MAX_STATUS_CHECKS; $check++) {
            $state = $info['state'] ?? null;

            if ($state === 'succeeded') {
                return;
            }
            if ($state === 'failed') {
                throw new RuntimeException('X could not process this file: ' . ($info['error']['message'] ?? 'unknown error') . '.');
            }

            Sleep::for(max(1, (int) ($info['check_after_secs'] ?? 1)))->seconds();

            $status = $this->request($token)->get(self::API . 'media/upload', ['command' => 'STATUS', 'media_id' => $mediaId]);
            if (!$status->successful()) {
                $this->fail('status', $status);
            }
            $info = $status->json('data.processing_info');
        }

        if ($info) {
            throw new RuntimeException('X is still processing this file - try sending it again in a minute.');
        }
    }

    /** Retries transient 5xx/429 and connection errors (1s, 3s); failures are returned, not thrown. */
    private function request(string $token, int $timeout = 30): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($token)
            ->timeout($timeout)
            ->acceptJson()
            ->retry([1000, 3000], when: fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false);
    }

    private function fail(string $step, Response $response): never
    {
        Log::warning('X DM media upload failed.', [
            'step'             => $step,
            'status'           => $response->status(),
            'x_transaction_id' => $response->header('x-transaction-id') ?: null,
            'title'            => $response->json('title'),
            'detail'           => \Illuminate\Support\Str::limit((string) ($response->json('detail') ?? $response->body()), 300),
        ]);

        $hint = $response->status() === 403 ? ' Reconnect the X account in Channels so it has the media.write permission.' : '';

        throw new RuntimeException("X media upload failed at {$step} (HTTP {$response->status()}): " . ($response->json('detail') ?? $response->json('title') ?? 'unknown error') . '.' . $hint);
    }
}
