<?php

namespace App\Support\Google;

use App\Models\SocialConnection;
use App\Support\Connections\GoogleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Authenticated calls to Google's reporting APIs for one Google Suite
 * connection: refreshes the access token when it is about to expire,
 * retries quota and outage errors with backoff, and turns every failure
 * into a GoogleApiException with a customer-safe code.
 */
class GoogleApi
{
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Seconds before expiry a token is renewed. */
    private const REFRESH_MARGIN = 120;

    public function __construct(private SocialConnection $connection)
    {
    }

    public function connection(): SocialConnection
    {
        return $this->connection;
    }

    public function get(string $url, array $query = []): array
    {
        return $this->send('get', $url, $query);
    }

    public function post(string $url, array $body = []): array
    {
        return $this->send('post', $url, $body);
    }

    private function send(string $method, string $url, array $payload): array
    {
        $attempt = function () use ($method, $url, $payload) {
            return Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(25)
                ->retry(3, fn (int $attempt) => $attempt * 600, function ($exception) {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && in_array($exception->response->status(), [429, 500, 502, 503, 504], true));
                }, throw: false)
                ->{$method}($url, $payload);
        };

        try {
            $response = $attempt();

            // A token Google stopped accepting before its expiry: one forced refresh.
            if ($response->status() === 401 && $this->connection->refresh_token) {
                $this->refresh();
                $response = $attempt();
            }
        } catch (ConnectionException $e) {
            throw new GoogleApiException(GoogleApiException::PROVIDER_OUTAGE, 'Connection failed');
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw $this->classify($response, $url);
    }

    private function accessToken(): string
    {
        $expiresAt = $this->connection->expires_at;

        if (! $this->connection->access_token || ($expiresAt && $expiresAt->lte(now()->addSeconds(self::REFRESH_MARGIN)))) {
            $this->refresh();
        }

        if (! $this->connection->access_token) {
            throw new GoogleApiException(GoogleApiException::AUTH_EXPIRED);
        }

        return $this->connection->access_token;
    }

    private function refresh(): void
    {
        if (! $this->connection->refresh_token) {
            $this->markNeedsReauth('No refresh token stored - reconnect.');

            throw new GoogleApiException(GoogleApiException::AUTH_EXPIRED);
        }

        // A refresh token only works with the client that issued it.
        $client = GoogleClient::credentials($this->connection->provider_app ?: GoogleClient::current());

        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'refresh_token' => $this->connection->refresh_token,
            ]);
        } catch (ConnectionException $e) {
            throw new GoogleApiException(GoogleApiException::PROVIDER_OUTAGE, 'Token refresh unreachable');
        }

        if (! $response->successful()) {
            if ($response->json('error') === 'invalid_grant') {
                $this->markNeedsReauth((string) ($response->json('error_description') ?: 'invalid_grant'));

                throw new GoogleApiException(GoogleApiException::AUTH_EXPIRED);
            }

            throw new GoogleApiException($response->serverError() ? GoogleApiException::PROVIDER_OUTAGE : GoogleApiException::NOT_CONFIGURED, 'Token refresh failed', $response->status());
        }

        $this->connection->forceFill([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $this->connection->refresh_token,
            'expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?: 3600)),
            'last_refreshed_at' => now(),
        ])->save();
    }

    private function markNeedsReauth(string $reason): void
    {
        $this->connection->forceFill([
            'status' => SocialConnection::NEEDS_REAUTH,
            'last_error' => mb_substr($reason, 0, 250),
            'last_checked_at' => now(),
        ])->save();
    }

    /**
     * Google reports errors as {error: {code, status, message, details|errors}}
     * across the Data, Admin and Search Console APIs; the reason / status
     * strings decide the code.
     */
    private function classify(Response $response, string $url): GoogleApiException
    {
        $status = $response->status();
        $error = (array) $response->json('error');
        $reasons = collect($error['details'] ?? [])->pluck('reason')
            ->merge(collect($error['errors'] ?? [])->pluck('reason'))
            ->push($error['status'] ?? null)
            ->filter()->map(fn ($r) => strtoupper((string) $r))->all();
        $has = fn (string ...$needles) => (bool) array_intersect($needles, $reasons);

        $code = match (true) {
            $status === 401 => GoogleApiException::AUTH_EXPIRED,
            $status === 429 || $has('RESOURCE_EXHAUSTED', 'RATELIMITEXCEEDED', 'USERRATELIMITEXCEEDED', 'QUOTAEXCEEDED') => GoogleApiException::QUOTA_EXCEEDED,
            $has('ACCESS_TOKEN_SCOPE_INSUFFICIENT')
                || ($has('INSUFFICIENTPERMISSIONS') && str_contains(strtolower((string) ($error['message'] ?? '')), 'scope')) => GoogleApiException::MISSING_SCOPE,
            $has('SERVICE_DISABLED', 'ACCESSNOTCONFIGURED') => GoogleApiException::API_DISABLED,
            $status === 403 => GoogleApiException::INSUFFICIENT_PERMISSIONS,
            $status === 404 => GoogleApiException::NOT_FOUND,
            $status >= 500 => GoogleApiException::PROVIDER_OUTAGE,
            default => GoogleApiException::BAD_REQUEST,
        };

        if ($code === GoogleApiException::AUTH_EXPIRED) {
            $this->markNeedsReauth('Google rejected the access token.');
        }

        // Status and reason only - never the body, which can echo report data.
        Log::warning('Google Suite API call failed.', [
            'connection_id' => $this->connection->id,
            'host' => parse_url($url, PHP_URL_HOST),
            'status' => $status,
            'reasons' => $reasons,
            'code' => $code,
        ]);

        return new GoogleApiException($code, (string) ($error['status'] ?? 'HTTP ' . $status), $status);
    }
}
