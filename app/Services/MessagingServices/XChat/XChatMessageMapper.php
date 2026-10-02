<?php

namespace App\Services\MessagingServices\XChat;

/**
 * Maps a decrypted X Chat event (normalized by the worker from the XDK's
 * Event: type + content.contentType/text/emoji/...) to what the inbox
 * stores. Shared by the webhook and the pending-decryption retry so both
 * produce identical messages.
 */
class XChatMessageMapper
{
    /** Placeholder bodies - never contain message content. */
    public const PENDING_BODY = 'Encrypted X Chat message - waiting to be decrypted.';
    public const UNVERIFIED_BODY = 'Encrypted X Chat message could not be verified and was not shown.';

    /**
     * Resolve a mapped message's attachments into stored files: the message
     * type follows the first file (image/video/audio/file), link/post
     * attachments are appended to the body, and anything that couldn't be
     * fetched is noted instead of silently dropped.
     *
     * @return array{type: string, body: ?string, attachments: array}
     */
    public static function withMedia(array $mapped, XChatMediaService $media, \App\Models\SocialAccount $account, string $conversationId, ?string $keyVersion): array
    {
        $body = (string) ($mapped['body'] ?? '');
        $type = $mapped['type'] ?? 'text';

        if (empty($mapped['attachments'])) {
            return ['type' => $type, 'body' => $body, 'attachments' => []];
        }

        $fetched = $media->fetchAttachments($account, $conversationId, $keyVersion, $mapped['attachments']);

        if ($fetched['links']) {
            $body = trim($body . "\n" . implode("\n", array_unique($fetched['links'])));
        }
        if ($fetched['failed']) {
            $body = trim($body . "\n[" . $fetched['failed'] . ' attachment(s) could not be loaded - open X to view]');
        }
        if ($fetched['attachments'] && trim((string) $mapped['body']) === '') {
            $type = $fetched['attachments'][0]['type'];
        }

        return ['type' => $type, 'body' => $body !== '' ? $body : null, 'attachments' => $fetched['attachments']];
    }

    /**
     * @return array{action: 'create'|'edit'|'ignore', type?: string, body?: ?string, target_message_id?: ?string, attachments?: array}
     */
    public static function map(array $event): array
    {
        // Only real messages reach the inbox; key changes, read receipts,
        // typing, delivery failures, etc. are protocol events.
        if (($event['type'] ?? null) !== 'message') {
            return ['action' => 'ignore'];
        }

        $contentType = strtolower((string) ($event['content_type'] ?? ''));

        return match ($contentType) {
            // Text, optionally with attachments: the caption is the body; the
            // attachments themselves are downloaded + decrypted by
            // XChatMediaService (see withMedia()).
            'text' => [
                'action'      => 'create',
                'type'        => 'text',
                'body'        => (string) ($event['text'] ?? ''),
                'attachments' => $event['attachments'] ?? [],
            ],
            'reaction' => [
                'action' => 'create',
                'type'   => 'reaction',
                'body'   => (string) ($event['emoji'] ?? ''),
                'target_message_id' => $event['target_message_id'] ?? null,
            ],
            'edit' => [
                'action'            => 'edit',
                'body'              => (string) ($event['text'] ?? ''),
                'target_message_id' => $event['target_message_id'] ?? null,
            ],
            // reactionRemoved, markRead, markUnread: state changes, not new messages.
            'reactionremoved', 'markread', 'markunread' => ['action' => 'ignore'],
            default => [
                'action' => 'create',
                'type'   => 'unsupported',
                'body'   => '[Unsupported X Chat message type: ' . ($contentType ?: 'unknown') . ' - open X to view]',
            ],
        };
    }
}
