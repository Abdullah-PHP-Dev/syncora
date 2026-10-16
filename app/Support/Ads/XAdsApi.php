<?php

namespace App\Support\Ads;

use App\Models\SocialAccount;
use App\Support\AdAccountSelection;
use App\Support\Connections\XOAuth1;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * X Ads API (v12) client for one seller's selected X ad account. Every
 * request is OAuth 1.0a signed (XOAuth1) with the account's user token -
 * X does not accept Bearer tokens for Ads API writes. Parameters go in a
 * signed query string, as in X's own examples; the JSON-body endpoints
 * (batch, cards, audience_estimate) sign the URL only, since OAuth 1.0a
 * never signs non-form bodies. Tokens never leave the server.
 */
class XAdsApi
{
    private const DEFAULT_BASE = 'https://ads-api.x.com/12/';

    private const DEFAULT_UPLOAD = 'https://api.x.com/2/media/upload';

    public function __construct(public readonly SocialAccount $account, private readonly string $base, private readonly string $tokenSecret)
    {
    }

    /** The seller's selected X ad account, when it is connected with Ads (OAuth 1.0a) access. */
    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'x');

        if (! $account || ! $account->access_token || ! $account->platform_account_id
            || ! AdAccountSelection::options($userId, 'x')->contains('id', $account->id)) {
            return null;
        }

        $secret = (string) ($account->connection?->token_secret ?? $account->metadata['legacy_token_secret'] ?? '');
        [$key, $consumerSecret] = XOAuth1::consumer();
        if ($secret === '' || $key === '' || $consumerSecret === '') {
            return null;
        }

        $base = rtrim((string) (adminSetting('ads.x.base_url') ?: self::DEFAULT_BASE), '/') . '/';

        return new self($account, $base, $secret);
    }

    public function accountId(): string
    {
        return (string) $this->account->platform_account_id;
    }

    /** The numeric X user id the account was connected with (promotable user). */
    public function userId(): ?string
    {
        $id = $this->account->metadata['profile_id'] ?? $this->account->metadata['x_user_id'] ?? null;

        return $id ? (string) $id : null;
    }

    /** "accounts/:id/..." helper. */
    public function path(string $suffix = ''): string
    {
        return 'accounts/' . $this->accountId() . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '');
    }

    /** GET - returns the whole envelope (data, next_cursor, ...). */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $this->base . ltrim($path, '/'), $query, null, true);
    }

    /** POST with parameters in the signed query string. */
    public function post(string $path, array $query = []): array
    {
        return $this->send('POST', $this->base . ltrim($path, '/'), $query, null, false);
    }

    /** PUT with parameters in the signed query string (status changes). */
    public function put(string $path, array $query = []): array
    {
        return $this->send('PUT', $this->base . ltrim($path, '/'), $query, null, false);
    }

    /** POST with a JSON body (batch endpoints, cards, audience_estimate). */
    public function postJson(string $path, array $body): array
    {
        return $this->send('POST', $this->base . ltrim($path, '/'), [], $body, false);
    }

    /**
     * Every page of a cursor-paginated GET, up to $maxPages pages.
     *
     * @return array<int, array>
     */
    public function all(string $path, array $query = [], int $maxPages = 10): array
    {
        $items = [];
        $cursor = null;
        $page = 0;

        do {
            $envelope = $this->get($path, array_filter($query + ['cursor' => $cursor], fn ($v) => $v !== null && $v !== ''));
            $items = array_merge($items, is_array($envelope['data'] ?? null) ? $envelope['data'] : []);
            $cursor = $envelope['next_cursor'] ?? null;
        } while ($cursor && ++$page < $maxPages);

        return $items;
    }

    /**
     * X API v2 user lookup by @handle (same OAuth 1.0a user context). Needs
     * the X developer app to have X API v2 read access.
     */
    public function usersByHandle(array $handles): array
    {
        $url = rtrim((string) (adminSetting('ads.x.v2_base_url') ?: 'https://api.x.com/2'), '/') . '/users/by';

        return $this->send('GET', $url, ['usernames' => implode(',', $handles), 'user.fields' => 'profile_image_url,public_metrics,verified'], null, true);
    }

    /** Signed request to the v2 media upload endpoints (initialize / append / finalize / status). */
    public function upload(string $method, string $suffix = '', array $query = [], ?array $json = null, ?array $file = null): array
    {
        $configured = (string) adminSetting('ads.x.upload_url');
        $base = $configured && ! str_contains($configured, '/1.1/') ? rtrim($configured, '/') : self::DEFAULT_UPLOAD;

        return $this->send($method, $base . $suffix, $query, $json, $method === 'GET', $file);
    }

    private function send(string $method, string $url, array $query, ?array $json, bool $idempotent, ?array $file = null): array
    {
        $query = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? implode(',', $v) : (string) $v), $query);

        for ($attempt = 1; ; $attempt++) {
            try {
                [$consumerKey, $consumerSecret] = XOAuth1::consumer();
                $header = XOAuth1::header($method, $url, $query, $consumerKey, $consumerSecret, (string) $this->account->access_token, $this->tokenSecret);
                $full = $url . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
                $http = Http::withHeaders(['Authorization' => $header])->acceptJson()->timeout($file ? 300 : 60);

                $response = match (true) {
                    $method === 'GET' => $http->get($full),
                    $file !== null => $http->attach($file['field'], $file['contents'], $file['name'])->post($full, $file['fields'] ?? []),
                    $json !== null => $http->asJson()->post($full, $json),
                    default => $http->send($method, $full),
                };

                return $this->parse($response);
            } catch (XApiException $e) {
                $retry = $e->isRateLimited() || ($idempotent && $e->status >= 500);
                if (! $retry || $attempt >= 2) {
                    throw $e;
                }
                usleep(1_500_000);
            } catch (ConnectionException) {
                if (! $idempotent || $attempt >= 2) {
                    throw new XApiException(__('x_builder.server.network'));
                }
            }
        }
    }

    private function parse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful() && empty($body['errors']) && empty($body['operation_errors'])) {
            return $body;
        }

        $error = $body['errors'][0] ?? collect($body['operation_errors'] ?? [])->flatten(1)->first(fn ($e) => is_array($e)) ?? [];
        $message = (string) ($error['message'] ?? $body['detail'] ?? $body['title'] ?? __('x_builder.server.http_error', ['status' => $response->status()]));
        if (! empty($error['parameter'])) {
            $message .= ' (' . $error['parameter'] . ')';
        }

        $exception = new XApiException($message, $response->status(), isset($error['code']) ? (string) $error['code'] : null, $error['parameter'] ?? null);

        if ($exception->isAuthError()) {
            throw new XApiException(__('x_builder.server.reconnect', ['reason' => $message]), $exception->status, $exception->errorCode);
        }
        if ($exception->isRateLimited()) {
            throw new XApiException(__('x_builder.server.rate_limited'), 429, $exception->errorCode);
        }

        throw $exception;
    }
}
