<?php

namespace App\Support\Ads;

/**
 * A failed Google Ads API call. The message is the field-level
 * GoogleAdsFailure text (error.details[].errors[].message), not the generic
 * "Request contains an invalid argument."
 */
class GoogleAdsApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?string $errorCode = null)
    {
        parent::__construct($message, $status);
    }

    /** Token revoked / expired, or the Google user lost access to the customer. */
    public function isAuthError(): bool
    {
        return $this->status === 401
            || ($this->status === 403 && in_array($this->errorCode, ['authenticationError', 'authorizationError'], true));
    }
}
