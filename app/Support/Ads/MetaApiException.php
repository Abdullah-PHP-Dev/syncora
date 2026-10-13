<?php

namespace App\Support\Ads;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A Graph API error with a message people can act on. $code/$subcode are
 * Meta's (eg. 190 = expired token, 200/10 = permission, 100 = invalid
 * parameter, 17/80004 = rate limit).
 */
class MetaApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $metaCode = 0, public readonly ?int $subcode = null, public readonly ?string $fbtraceId = null)
    {
        parent::__construct($message, $metaCode);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error') ?? [];
        $code = (int) ($error['code'] ?? 0);
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;

        $title = $error['error_user_title'] ?? null;
        $message = $error['error_user_msg'] ?? $error['message'] ?? ('Meta returned HTTP ' . $response->status() . '.');

        $message = match (true) {
            $code === 190 => 'Your Facebook connection has expired or was revoked. Reconnect your ad account in Connections.',
            in_array($code, [10, 200], true) || ($code >= 200 && $code < 300) => 'Meta says this ad account or Page lacks the permission for this action. ' . $message,
            in_array($code, [4, 17, 32, 613, 80004], true) => 'Meta is rate-limiting this ad account right now. Wait a few minutes and try again.',
            default => ($title ? rtrim($title, '.') . ': ' : '') . $message,
        };

        return new self(trim($message), $code, $subcode, $error['fbtrace_id'] ?? null);
    }
}
