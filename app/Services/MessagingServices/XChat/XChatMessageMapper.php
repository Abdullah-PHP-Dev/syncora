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
            'text' => [
                'action' => 'create',
                'type'   => ($event['attachment_count'] ?? 0) > 0 && trim((string) $event['text']) === '' ? 'file' : 'text',
                'body'   => ($event['attachment_count'] ?? 0) > 0
                    ? trim(($event['text'] ?? '') . "\n[" . $event['attachment_count'] . ' encrypted attachment(s) - open X to view]')
                    : (string) ($event['text'] ?? ''),
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
