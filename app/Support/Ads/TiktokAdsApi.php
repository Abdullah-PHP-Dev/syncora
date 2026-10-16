<?php

namespace App\Support\Ads;

use App\Models\SocialAccount;
use App\Support\AdAccountSelection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Marketing API (v1.3) client for one seller's selected TikTok ad
 * account. TikTok answers HTTP 200 for most failures - the result is the
 * envelope's `code` (0 = ok) - so every call is checked here and turned
 * into a TiktokApiException with a readable message. The access token
 * never leaves the server.
 */
class TiktokAdsApi
{
    private const DEFAULT_BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    public function __construct(public readonly SocialAccount $account, private readonly string $base)
    {
    }

    /** The seller's selected TikTok ad account, when it is connected with ads access. */
    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'tiktok');

        if (! $account || ! $account->access_token || ! $account->platform_account_id
            || ! AdAccountSelection::options($userId, 'tiktok')->contains('id', $account->id)) {
            return null;
        }

        $base = rtrim((string) (adminSetting('ads.tiktok.base_url') ?: self::DEFAULT_BASE), '/') . '/';

        return new self($account, $base);
    }

    public function advertiserId(): string
    {
        return (string) $this->account->platform_account_id;
    }

    /** GET - array parameters are JSON-encoded, as TikTok expects in the query string. */
    public function get(string $path, array $query = []): array
    {
        $query = ['advertiser_id' => $this->advertiserId()] + $query;
        $query = array_map(fn ($v) => is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v), $query);

        return $this->send(fn () => $this->http()->get($this->base . ltrim($path, '/'), $query), true);
    }

    public function post(string $path, array $body = []): array
    {
        $body = ['advertiser_id' => $this->advertiserId()] + $body;

        return $this->send(fn () => $this->http()->asJson()->post($this->base . ltrim($path, '/'), $body), false);
    }

    /** Multipart file upload (file/image/ad/upload/, file/video/ad/upload/). */
    public function upload(string $path, array $fields, string $fileField, string $contents, string $fileName): array
    {
        $fields = ['advertiser_id' => $this->advertiserId()] + $fields;

        return $this->send(fn () => $this->http(300)->attach($fileField, $contents, $fileName)->post($this->base . ltrim($path, '/'), $fields), false);
    }

    private function http(int $timeout = 60)
    {
        return Http::withHeaders(['Access-Token' => (string) $this->account->access_token])->acceptJson()->timeout($timeout);
    }

    /**
     * Runs a call; a rate limit (or, for reads, a TikTok server error) is
     * retried once after a short pause. Writes are never retried blindly -
     * the builder's run log handles retries for those.
     */
    private function send(callable $call, bool $idempotent): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->parse($call());
            } catch (TiktokApiException $e) {
                $retry = $e->isRateLimited() || ($idempotent && $e->apiCode >= 50000);
                if (! $retry || $attempt >= 2) {
                    throw $e;
                }
                usleep(1_200_000);
            } catch (ConnectionException $e) {
                if (! $idempotent || $attempt >= 2) {
                    throw new TiktokApiException(__('tiktok_builder.server.network'));
                }
            }
        }
    }

    private function parse(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('code', $body)) {
            throw new TiktokApiException(__('tiktok_builder.server.http_error', ['status' => $response->status()]), $response->status() >= 500 ? 50000 : 0);
        }

        $code = (int) $body['code'];
        if ($code !== 0) {
            $exception = new TiktokApiException((string) ($body['message'] ?? 'TikTok error'), $code, $body['request_id'] ?? null);

            if ($exception->isAuthError()) {
                throw new TiktokApiException(__('tiktok_builder.server.reconnect', ['reason' => $exception->getMessage()]), $code, $exception->requestId);
            }
            if ($exception->isRateLimited()) {
                throw new TiktokApiException(__('tiktok_builder.server.rate_limited'), $code, $exception->requestId);
            }

            throw $exception;
        }

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }
}
