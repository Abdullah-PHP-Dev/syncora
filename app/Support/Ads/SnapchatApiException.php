<?php

namespace App\Support\Ads;

/**
 * A Snapchat Marketing API failure. The message is already readable for
 * the seller; the HTTP status and Snap's error_code are kept for the log.
 */
class SnapchatApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /** Token revoked / expired, or the Snap user lost access to the ad account. */
    public function isAuthError(): bool
    {
        return $this->status === 401;
    }
}
