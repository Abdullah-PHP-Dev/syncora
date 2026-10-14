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
        $message = $error['error_user_msg'] ?? $error['message'] ?? __('ads_builder.server.meta_http', ['status' => $response->status()]);

        $message = match (true) {
            $code === 190 => __('ads_builder.server.meta_revoked'),
            in_array($code, [10, 200], true) || ($code >= 200 && $code < 300) => __('ads_builder.server.meta_permission', ['message' => $message]),
            in_array($code, [4, 17, 32, 613, 80004], true) => __('ads_builder.server.meta_rate_limit'),
            default => ($title ? rtrim($title, '.') . ': ' : '') . $message,
        };

        return new self(trim($message), $code, $subcode, $error['fbtrace_id'] ?? null);
    }
}
