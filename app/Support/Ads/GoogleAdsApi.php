<?php

namespace App\Support\Ads;

use App\Models\SocialAccount;
use App\Support\AdAccountSelection;
use App\Support\Connections\GoogleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Thin Google Ads REST client for the campaign builder: GAQL search,
 * googleAds:mutate (atomic, temporary resource names) and the few
 * non-customer services (geoTargetConstants:suggest). The access token is
 * refreshed when it has expired, and once more on a 401.
 */
class GoogleAdsApi
{
    private const DEFAULT_BASE = 'https://googleads.googleapis.com/v24/';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(public SocialAccount $account, private readonly string $base = self::DEFAULT_BASE)
    {
    }

    public static function forUser(int $userId): ?self
    {
        $account = AdAccountSelection::resolve($userId, 'google');

        if (! $account || (! $account->access_token && ! $account->refresh_token) || ! $account->platform_account_id
            || ! AdAccountSelection::options($userId, 'google')->contains('id', $account->id)) {
            return null;
        }

        $base = rtrim((string) (adminSetting('ads.google.base_url') ?: self::DEFAULT_BASE), '/') . '/';

        return new self($account, $base);
    }

    public function customerId(): string
    {
        return str_replace('-', '', (string) $this->account->platform_account_id);
    }

    /** customers/{id}/{collection}/{id} */
    public function resource(string $collection, string|int $id): string
    {
        return 'customers/' . $this->customerId() . '/' . $collection . '/' . $id;
    }

    /** GAQL rows, following nextPageToken (search pages are fixed at 10,000 rows). */
    public function search(string $query, int $maxPages = 5): array
    {
        $rows = [];
        $token = null;
        $page = 0;

        do {
            $body = $this->post('customers/' . $this->customerId() . '/googleAds:search', array_filter(['query' => $query, 'pageToken' => $token]));
            array_push($rows, ...($body['results'] ?? []));
            $token = $body['nextPageToken'] ?? null;
        } while ($token && ++$page < $maxPages);

        return $rows;
    }

    /** One atomic googleAds:mutate - every operation succeeds or none does. */
    public function mutate(array $operations): array
    {
        return $this->post('customers/' . $this->customerId() . '/googleAds:mutate', ['mutateOperations' => $operations])['mutateOperationResponses'] ?? [];
    }

    public function post(string $path, array $body): array
    {
        $refreshed = false;

        while (true) {
            try {
                $response = Http::withHeaders(GoogleClient::adsHeaders($this->token()))
                    ->acceptJson()->asJson()->timeout(120)
                    ->post($this->base . ltrim($path, '/'), $body ?: (object) []);
            } catch (ConnectionException $e) {
                throw new GoogleAdsApiException(__('google_builder.server.unreachable'), 0);
            }

            if ($response->status() === 401 && ! $refreshed && $this->account->refresh_token) {
                $refreshed = true;
                $this->refresh();
                continue;
            }

            return $this->parse($response);
        }
    }

    private function parse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $failures = collect($error['details'] ?? [])->flatMap(fn ($d) => $d['errors'] ?? []);
        $message = $failures->map(function ($f) {
            $field = collect($f['location']['fieldPathElements'] ?? [])->pluck('fieldName')->filter()->implode('.');

            return trim(($f['message'] ?? '') . ($field ? " ({$field})" : ''));
        })->filter()->unique()->implode(' ');
        $code = array_key_first($failures->first()['errorCode'] ?? []) ?: null;

        throw new GoogleAdsApiException($message ?: ($error['message'] ?? __('google_builder.server.api_failed')), $response->status(), $code);
    }

    private function token(): string
    {
        $expires = $this->account->expires_at;
        if ($this->account->access_token && $expires && now()->lt(Carbon::parse($expires)->subMinute())) {
            return (string) $this->account->access_token;
        }
        if (! $this->account->refresh_token) {
            return (string) $this->account->access_token;
        }

        return $this->refresh();
    }

    private function refresh(): string
    {
        $client = GoogleClient::credentials(GoogleClient::forAccount($this->account, 'ads.google'));
        $response = Http::asForm()->timeout(30)->post(adminSetting('ads.google.access_token') ?: self::TOKEN_URL, [
            'client_id' => $client['client_id'] ?? null,
            'client_secret' => $client['client_secret'] ?? null,
            'refresh_token' => $this->account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new GoogleAdsApiException(__('google_builder.server.reconnect'), 401, 'authenticationError');
        }

        $this->account->forceFill([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?? 3600)),
        ])->save();

        return (string) $this->account->access_token;
    }
}
