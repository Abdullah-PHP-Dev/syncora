<?php

namespace App\Services\MessagingServices;

use App\Jobs\Messaging\ProcessInboundMessage;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\Messaging\MessageChannel;
use App\Services\ApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Matrix (matrix.org) - an open, federated protocol rather than a single
 * company's product, with two distinct APIs that fit this module very
 * differently:
 *
 * 1. The Application Service API is the "real" bridge/bot mechanism, and
 *    it DOES get pushed events over HTTP - but only after a homeserver's
 *    own admin manually registers the service via a YAML config file on
 *    that server. There's no way to get that on a public homeserver like
 *    matrix.org as an outside developer, so it isn't self-service the way
 *    every other platform added to this module has been.
 *
 * 2. The Client-Server API is what every normal Matrix account (human or
 *    bot) uses, and it's fully self-service: register or reuse any
 *    account on any homeserver (matrix.org or self-hosted), grab an
 *    access token, done. Its cost is the same one Discord's Gateway
 *    already introduced to this module: there's no webhook, so receiving
 *    messages needs a persistent process - here, one that long-polls
 *    `/sync` (see RunMatrixSyncListener) rather than holding a WebSocket
 *    open. This class deliberately builds on the Client-Server API for
 *    that reason, matching the standard set for Discord: build the real,
 *    correct thing rather than a degraded version, and be upfront about
 *    what it actually requires to run.
 *
 * Because the homeserver itself is chosen by whoever connects a channel
 * (not a fixed company API host), it's stored per-channel in
 * message_channels.meta rather than as a hardcoded/admin-configured base
 * URL the way every fixed-provider platform in this module works.
 *
 * One further, real limitation worth being upfront about (documented
 * here rather than silently mishandled): Matrix rooms can be end-to-end
 * encrypted, and many Matrix clients default new direct-message rooms to
 * encrypted. Implementing Olm/Megolm decryption in PHP is a substantial
 * undertaking with no mature library to build on here, so - consistent
 * with how most Matrix bots and bridges behave without deliberately
 * added E2EE support - encrypted-room messages arrive as undecipherable
 * ciphertext and are detected and skipped rather than processed as
 * garbage (see handleMessageEvent()). Unencrypted rooms, which is what a
 * bot account gets by default when it isn't the one initiating the room,
 * work normally.
 *
 * Endpoints/shapes verified this session against spec.matrix.org: the
 * account/whoami verification call, the m.room.message send/sync shapes,
 * invite/join semantics, and the current (post-MSC3916, Matrix v1.11+)
 * authenticated media download endpoint - the older unauthenticated
 * /_matrix/media/v3/download path is deprecated for anything uploaded
 * after that spec version shipped.
 */
class MatrixMessagingService
{
    public function __construct(protected ApiService $apiService)
    {
    }

    private function authHeader(MessageChannel $channel): array
    {
        return ['Authorization' => 'Bearer ' . $channel->access_token];
    }

    private function homeserverUrl(MessageChannel $channel): string
    {
        return rtrim($channel->meta['homeserver_url'] ?? '', '/');
    }

    /**
     * Matrix actually has a clean "who am I" endpoint - unlike Teams/
     * Google Chat's service-account credentials, there's no need to mint
     * a throwaway token just to confirm one works.
     */
    public function verifyCredentials(string $homeserverUrl, string $accessToken): array
    {
        $response = $this->apiService->get(
            rtrim($homeserverUrl, '/') . '/_matrix/client/v3/account/whoami',
            ['Authorization' => 'Bearer ' . $accessToken]
        );

        if (!$response['success'] || empty($response['data']['user_id'])) {
            return ['success' => false];
        }

        return ['success' => true, 'user_id' => $response['data']['user_id']];
    }

    /**
     * Every send needs a client-generated transaction id for idempotency
     * (a retried request with the same txnId is a no-op, not a
     * duplicate) - uniqid() is sufficient here since this app never
     * itself retries a send with the same id.
     */
    public function sendMessage(Conversation $conversation, array $data)
    {
        $channel = $conversation->channel;

        if (!$conversation->external_conversation_id) {
            return ['success' => false, 'error' => 'Cannot start a Matrix conversation from this side - wait for the customer to message the account first.'];
        }

        $text = $data['body'] ?? '';

        // Same "append the URL" simplification used for every other
        // platform in this module without a straightforward direct-
        // attachment send.
        if (!empty($data['media_url'])) {
            $text = trim($text . ' ' . $data['media_url']);
        }

        $txnId = (string) uniqid('syncora_', true);
        $roomId = $conversation->external_conversation_id;

        $response = $this->apiService->put(
            $this->homeserverUrl($channel) . "/_matrix/client/v3/rooms/{$roomId}/send/m.room.message/{$txnId}",
            $this->authHeader($channel),
            ['msgtype' => 'm.text', 'body' => $text]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Matrix API request failed.'];
        }

        return ['success' => true, 'external_message_id' => $response['data']['event_id'] ?? null];
    }

    /**
     * Matrix has no in-place "edit" call - an edit is a brand new event
     * sent to the room, linked back to the original via an m.replace
     * relation (m.relates_to.rel_type) and carrying the real new content
     * under m.new_content. The top-level msgtype/body is a fallback
     * rendering for clients that don't understand edits at all, which is
     * why it's conventionally prefixed with "*".
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $channel = $message->conversation->channel;
        $roomId = $message->conversation->external_conversation_id;
        $txnId = (string) uniqid('syncora_edit_', true);

        $response = $this->apiService->put(
            $this->homeserverUrl($channel) . "/_matrix/client/v3/rooms/{$roomId}/send/m.room.message/{$txnId}",
            $this->authHeader($channel),
            [
                'msgtype'       => 'm.text',
                'body'          => '* ' . $newBody,
                'm.new_content' => ['msgtype' => 'm.text', 'body' => $newBody],
                'm.relates_to'  => ['rel_type' => 'm.replace', 'event_id' => $message->external_message_id],
            ]
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Matrix API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Matrix calls this "redaction" - it strips the event down to just
     * the protocol-required keys rather than truly erasing it (servers
     * keep a stub so room state/ordering stays consistent), but the
     * effect is the same as a delete: the message content is gone.
     */
    public function deleteMessage(Message $message): array
    {
        $channel = $message->conversation->channel;
        $roomId = $message->conversation->external_conversation_id;
        $txnId = (string) uniqid('syncora_redact_', true);

        $response = $this->apiService->put(
            $this->homeserverUrl($channel) . "/_matrix/client/v3/rooms/{$roomId}/redact/{$message->external_message_id}/{$txnId}",
            $this->authHeader($channel),
            []
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['data']['error'] ?? 'Matrix API request failed.'];
        }

        return ['success' => true];
    }

    /**
     * Called by RunMatrixSyncListener for each timeline event in a joined
     * room. Filters out anything that isn't a genuine inbound text/media
     * message: non-message event types (membership changes, reactions,
     * etc.), this bot's own messages echoing back through the timeline,
     * and encrypted events it has no way to read (see class docblock).
     */
    public function handleMessageEvent(array $event, string $roomId, MessageChannel $channel): void
    {
        $type = $event['type'] ?? null;

        if ($type === 'm.room.encrypted') {
            Log::info('Skipping encrypted Matrix message - this bot does not support E2EE.', ['channel_id' => $channel->id, 'room_id' => $roomId]);

            return;
        }

        if ($type !== 'm.room.message' || ($event['sender'] ?? null) === $channel->external_id) {
            return;
        }

        $content = $event['content'] ?? [];
        $msgtype = $content['msgtype'] ?? 'm.text';

        $attachments = [];

        if (in_array($msgtype, ['m.image', 'm.video', 'm.audio', 'm.file'], true) && !empty($content['url'])) {
            $attachment = $this->rehostMedia($channel, $content['url'], $msgtype, $content['body'] ?? null);

            if ($attachment) {
                $attachments[] = $attachment;
            }
        }

        ProcessInboundMessage::dispatch(
            messageChannelId: $channel->id,
            customerExternalId: $event['sender'],
            externalConversationId: $roomId,
            externalMessageId: $event['event_id'] ?? null,
            type: !empty($attachments) ? $attachments[0]['type'] : 'text',
            body: $msgtype === 'm.text' ? ($content['body'] ?? null) : null,
            attachments: $attachments,
        );
    }

    /**
     * Media in Matrix is addressed by an mxc:// URI (mxc://{serverName}/
     * {mediaId}), not a plain HTTPS URL - resolved here through the
     * current authenticated download endpoint (Matrix v1.11+ / MSC3916;
     * the older unauthenticated /_matrix/media/v3/download path is
     * deprecated for anything uploaded after a homeserver adopted that
     * spec version) and re-hosted to S3, the same auth-gated-media
     * pattern as LINE/Slack/Teams.
     */
    private function rehostMedia(MessageChannel $channel, string $mxcUri, string $msgtype, ?string $fileName): ?array
    {
        if (!str_starts_with($mxcUri, 'mxc://')) {
            return null;
        }

        [$serverName, $mediaId] = array_pad(explode('/', substr($mxcUri, 6), 2), 2, null);

        if (!$serverName || !$mediaId) {
            return null;
        }

        // Raw Http facade throws a ConnectionException on a DNS/network
        // failure rather than returning an unsuccessful Response - a
        // homeserver that's temporarily unreachable must not crash the
        // sync listener's message processing.
        try {
            $response = Http::withToken($channel->access_token)
                ->get($this->homeserverUrl($channel) . "/_matrix/client/v1/media/download/{$serverName}/{$mediaId}");
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $type = match ($msgtype) {
            'm.image' => 'image',
            'm.video' => 'video',
            'm.audio' => 'audio',
            default   => 'file',
        };

        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $s3Path = "uploads/matrix/media/" . uniqid() . '_' . ($fileName ?: $mediaId);

        Storage::disk('r2')->put($s3Path, $response->body(), ['visibility' => 'public']);

        return [
            'type'      => $type,
            'url'       => Storage::disk('r2')->url($s3Path),
            'mime_type' => $mimeType,
            'file_name' => $fileName,
        ];
    }
}
