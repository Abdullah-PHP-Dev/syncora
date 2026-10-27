<?php

namespace App\Support\Google;

use RuntimeException;

/**
 * A Google API failure reduced to a customer-safe code. The page shows
 * google_suite.errors.<code>; provider bodies are never passed through.
 */
class GoogleApiException extends RuntimeException
{
    public const NOT_CONNECTED = 'not_connected';
    public const AUTH_EXPIRED = 'auth_expired';
    public const MISSING_SCOPE = 'missing_scope';
    public const INSUFFICIENT_PERMISSIONS = 'insufficient_permissions';
    public const API_DISABLED = 'api_disabled';
    public const QUOTA_EXCEEDED = 'quota_exceeded';
    public const PROVIDER_OUTAGE = 'provider_outage';
    public const NOT_FOUND = 'not_found';
    public const BAD_REQUEST = 'bad_request';
    public const NO_PROPERTY = 'no_property';
    public const NOT_CONFIGURED = 'not_configured';
    /** PageSpeed Insights: Google could not load the tested page. */
    public const PAGE_UNREACHABLE = 'page_unreachable';
    /** Google Ads reporting: no ads account in the Connection Hub, or its token stopped working. */
    public const ADS_NOT_CONNECTED = 'ads_not_connected';
    public const ADS_FAILED = 'ads_failed';

    /** Codes the customer fixes by reconnecting Google. */
    public const RECONNECT = [self::AUTH_EXPIRED, self::MISSING_SCOPE, self::NOT_CONNECTED];

    /** Codes worth retrying later without changing anything. */
    public const TRANSIENT = [self::QUOTA_EXCEEDED, self::PROVIDER_OUTAGE];

    public function __construct(public readonly string $errorCode, string $detail = '', public readonly int $status = 0)
    {
        parent::__construct($detail !== '' ? $detail : $errorCode);
    }

    public function customerMessage(): string
    {
        return __('google_suite.errors.' . $this->errorCode);
    }

    /** JSON shape every endpoint returns for a failure. */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->customerMessage(),
            'reconnect' => in_array($this->errorCode, self::RECONNECT, true),
            'retryable' => in_array($this->errorCode, self::TRANSIENT, true),
        ];
    }
}
