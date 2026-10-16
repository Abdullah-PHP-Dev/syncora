<?php

namespace App\Support\Ads;

use App\Models\SocialAccount;
use App\Support\AdAccountSelection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Snapchat Marketing API (v1) client for one seller's selected Snapchat ad
 * account. Snap access tokens live ~30 minutes, so the token is refreshed
 * here (under a lock, and copied to every ad account row that shares it -
 * one OAuth grant covers all of the seller's Snap ad accounts). Snap can
 * answer HTTP 200 with request_status ERROR/PARTIAL, so every call is
 * checked here and turned into a SnapchatApiException with a readable
 * message. Tokens never leave the server.
 */
class SnapchatAdsApi
{
    private const DEFAULT_BASE = 'https://adsapi.snapchat.com/v1/';

    /** Public Profiles are served by the Business API host (Public Profile API). */
    private const BUSINESS_BASE = 'https://businessapi.snapchat.com/v1/';

    private const TOKEN_URL = 'https://accounts.snapchat.com/login/oauth2/access_token';

    public function __construct(public SocialAccount $account, private readonly string $base = self::DEFAULT_BASE)
    {
    }

    /** The seller's selected Snapchat ad account, when it is connected with ads access. */
    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'snapchat');

        if (! $account || (! $account->access_token && ! $account->refresh_token) || ! $account->platform_account_id
            || ! AdAccountSelection::options($userId, 'snapchat')->contains('id', $account->id)) {
            return null;
        }

        $base = rtrim((string) (adminSetting('ads.snapchat.base_url') ?: self::DEFAULT_BASE), '/') . '/';

        return new self($account, $base);
    }

    public function adAccountId(): string
    {
        return (string) $this->account->platform_account_id;
    }

    /** The Snap organization the ad account belongs to (stored at connect time). */
    public function organizationId(): ?string
    {
        $id = $this->account->metadata['organization_id'] ?? $this->account->adDetails?->business_id ?? null;

        return $id ? (string) $id : null;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $this->url($path), ['query' => $query], true);
    }

    public function post(string $path, array $body): array
    {
        return $this->send('POST', $this->url($path), ['json' => $body], false);
    }

    /** JSON Patch (RFC 6902) - Snap's partial update for campaigns, ad squads and ads. */
    public function patch(string $path, array $operations): array
    {
        return $this->send('PATCH', $this->url($path), ['patch' => $operations], false);
    }

    /** Multipart upload of a media file (POST media/{id}/upload). */
    public function upload(string $path, string $contents, string $fileName): array
    {
        return $this->send('POST', $this->url($path), ['file' => [$contents, $fileName]], false);
    }

    /** GET against the Business API host (Public Profiles). */
    public function business(string $path, array $query = []): array
    {
        $base = rtrim((string) (adminSetting('ads.snapchat.business_base_url') ?: self::BUSINESS_BASE), '/') . '/';

        return $this->send('GET', $base . ltrim($path, '/'), ['query' => $query], true);
    }

    /**
     * Every page of a GET collection (Snap pages with paging.next_link), up
     * to $maxPages pages; returns the unwrapped items under $key/$item.
     *
     * @return array<int, array>
     */
    public function all(string $path, string $key, string $item, array $query = [], int $maxPages = 10): array
    {
        $items = [];
        $url = $this->url($path);
        $page = 0;

        do {
            $body = $this->send('GET', $url, ['query' => $page === 0 ? $query : []], true);
            foreach ($body[$key] ?? [] as $entry) {
                if (is_array($entry[$item] ?? null)) {
                    $items[] = $entry[$item];
                }
            }
            $url = $body['paging']['next_link'] ?? null;
        } while ($url && str_starts_with($url, 'https://') && ++$page < $maxPages);

        return $items;
    }

    private function url(string $path): string
    {
        return $this->base . ltrim($path, '/');
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

    private function send(string $method, string $url, array $options, bool $idempotent): array
    {
        $refreshed = false;

        for ($attempt = 1; ; $attempt++) {
            try {
                $http = Http::withToken($this->token())->acceptJson()->timeout(isset($options['file']) ? 300 : 60);

                $response = match (true) {
                    $method === 'GET' => $http->get($url, $options['query'] ?? []),
                    isset($options['file']) => $http->attach('file', $options['file'][0], $options['file'][1])->post($url),
                    isset($options['patch']) => $http->withBody(json_encode($options['patch']), 'application/json-patch+json')->patch($url),
                    default => $http->asJson()->send($method, $url, ['json' => $options['json'] ?? []]),
                };

                return $this->parse($response);
            } catch (SnapchatApiException $e) {
                // An access token can be revoked before its expiry: one
                // forced refresh, then the same request again.
                if ($e->status === 401 && ! $refreshed && $this->account->refresh_token) {
                    $refreshed = true;
                    $this->refresh(true);

                    continue;
                }
                $retry = $e->isRateLimited() || ($idempotent && $e->status >= 500);
                if (! $retry || $attempt >= 2) {
                    if ($e->isAuthError()) {
                        throw new SnapchatApiException(__('snapchat_builder.server.reconnect', ['reason' => $e->getMessage()]), $e->status, $e->errorCode);
                    }
                    throw $e;
                }
                usleep(1_500_000);
            } catch (ConnectionException) {
                if (! $idempotent || $attempt >= 2) {
                    throw new SnapchatApiException(__('snapchat_builder.server.network'));
                }
            }
        }
    }

    private function parse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $status = strtoupper((string) ($body['request_status'] ?? ''));

        if ($response->successful() && ! in_array($status, ['ERROR', 'PARTIAL'], true)) {
            return $body;
        }

        // Batch endpoints report the failing item as a sub-request.
        $sub = collect($body)->filter(fn ($v) => is_array($v) && array_is_list($v))->flatten(1)
            ->first(fn ($entry) => is_array($entry) && strtoupper((string) ($entry['sub_request_status'] ?? 'SUCCESS')) !== 'SUCCESS');
        $message = (string) ($sub['sub_request_error_reason'] ?? $body['display_message'] ?? $body['debug_message'] ?? $body['error_description'] ?? '');
        if ($message === '') {
            $message = __('snapchat_builder.server.http_error', ['status' => $response->status()]);
        }

        $httpStatus = $response->successful() ? 422 : $response->status();
        if ($httpStatus === 429) {
            throw new SnapchatApiException(__('snapchat_builder.server.rate_limited'), 429, $body['error_code'] ?? null);
        }

        throw new SnapchatApiException($message, $httpStatus, isset($body['error_code']) ? (string) $body['error_code'] : null);
    }

    // ------------------------------------------------------------------
    // Tokens
    // ------------------------------------------------------------------

    private function token(): string
    {
        $expires = $this->account->expires_at ? Carbon::parse($this->account->expires_at) : null;

        if ($this->account->refresh_token && (! $this->account->access_token || ! $expires || $expires->lte(now()->addMinute()))) {
            $this->refresh(false);
        }

        return (string) $this->account->access_token;
    }

    /**
     * Refresh the OAuth token. Under a lock, and re-reading the row first, so
     * two requests don't both spend the same refresh token; the new tokens
     * are copied to every ad account row that shared the old one.
     */
    private function refresh(bool $force): void
    {
        $old = (string) $this->account->refresh_token;
        $lock = Cache::lock('snapchat-token:' . $this->account->user_id . ':' . sha1($old), 30);

        try {
            $lock->block(20);
        } catch (\Throwable) {
            throw new SnapchatApiException(__('snapchat_builder.server.token_busy'));
        }

        try {
            $fresh = $this->account->fresh();
            if ($fresh && $fresh->access_token && $fresh->access_token !== $this->account->access_token
                && $fresh->expires_at && Carbon::parse($fresh->expires_at)->gt(now()->addMinute())) {
                $this->account = $fresh;

                return;
            }
            if (! $force && $fresh && $fresh->expires_at && Carbon::parse($fresh->expires_at)->gt(now()->addMinute())) {
                $this->account = $fresh;

                return;
            }

            $response = Http::asForm()->acceptJson()->timeout(30)->post(adminSetting('ads.snapchat.access_token') ?: self::TOKEN_URL, [
                'client_id' => adminSetting('ads.snapchat.client_id'),
                'client_secret' => adminSetting('ads.snapchat.client_secret'),
                'refresh_token' => $old,
                'grant_type' => 'refresh_token',
            ]);
            $data = $response->json() ?: [];

            if (! $response->successful() || empty($data['access_token'])) {
                throw new SnapchatApiException(__('snapchat_builder.server.reconnect', ['reason' => $data['error_description'] ?? $data['error'] ?? 'refresh failed']), 401);
            }

            $attributes = [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $old,
                'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 1800)),
            ];
            // Tokens are encrypted at rest, so the shared ones are matched in PHP.
            SocialAccount::where('user_id', $this->account->user_id)->where('platform', 'snapchat')->get()
                ->filter(fn ($a) => $a->id === $this->account->id || (string) $a->refresh_token === $old)
                ->each(fn ($a) => $a->forceFill($attributes)->save());
            $this->account->forceFill($attributes);
        } finally {
            $lock->release();
        }
    }
}
