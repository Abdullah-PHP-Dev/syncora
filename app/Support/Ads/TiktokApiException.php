<?php

namespace App\Support\Ads;

/**
 * A TikTok Marketing API failure (non-zero envelope `code`, or HTTP error).
 * The message is already readable for the seller; `code` and `requestId`
 * are kept for the log.
 */
class TiktokApiException extends \RuntimeException
{
    /** Access token missing, expired, revoked or lacking the scope. */
    public const AUTH_CODES = [40001, 40102, 40104, 40105, 40106];

    public const RATE_LIMIT_CODE = 40100;

    public function __construct(string $message, public readonly int $apiCode = 0, public readonly ?string $requestId = null)
    {
        parent::__construct($message, $apiCode);
    }

    public function isRateLimited(): bool
    {
        return $this->apiCode === self::RATE_LIMIT_CODE;
    }

    public function isAuthError(): bool
    {
        return in_array($this->apiCode, self::AUTH_CODES, true);
    }
}
