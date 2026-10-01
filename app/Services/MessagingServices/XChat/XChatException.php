<?php

namespace App\Services\MessagingServices\XChat;

use RuntimeException;

/**
 * Typed X Chat failure. `code` values (stable, from the worker or Laravel):
 * session_locked, unlock_failed, missing_conversation_key, signature_invalid,
 * decrypt_failed, malformed_event, worker_unavailable, not_configured,
 * public_keys_unavailable, send_failed.
 */
class XChatException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /** Failures worth retrying later (eg. after the PIN is added or the worker restarts). */
    public function isRetryable(): bool
    {
        return in_array($this->reason, ['session_locked', 'missing_conversation_key', 'worker_unavailable', 'not_configured', 'public_keys_unavailable'], true);
    }
}
