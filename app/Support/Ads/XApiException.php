<?php

namespace App\Support\Ads;

/**
 * An X Ads API failure. The message is already readable for the seller;
 * the HTTP status, X's error `code` (e.g. INVALID_PARAMETER) and the
 * offending parameter are kept for the log.
 */
class XApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?string $errorCode = null, public readonly ?string $parameter = null)
    {
        parent::__construct($message, $status);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /** Token revoked / expired, or the X user lost access to the ad account. */
    public function isAuthError(): bool
    {
        return $this->status === 401 || in_array($this->errorCode, ['UNAUTHORIZED_ACCESS', 'INVALID_ACCOUNT', 'NOT_AUTHORIZED_FOR_ACCOUNT'], true);
    }
}
