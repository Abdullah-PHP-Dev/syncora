<?php

namespace App\Services\EmailMarketingServices;

use App\Services\ApiService;

/**
 * Thin wrapper around ApiService (the same "call the platform's real REST
 * API directly" client every other integration in this app uses) for
 * SendGrid's v3 API. Two calling shapes:
 *
 *   - asSubaccount($apiKey)->post(...)   - every domain/sender/contact/
 *     list/campaign/webhook call, authenticated with that seller's own
 *     subuser API key. This is the normal path once a subaccount exists -
 *     from here on a tenant's integration behaves exactly like talking to
 *     a standalone SendGrid account.
 *   - asParent()->post(...) / asParent($onBehalfOfUsername)->post(...)  -
 *     only for provisioning itself (POST /subusers with the parent key,
 *     then POST /api_keys with the parent key + on-behalf-of to mint a
 *     key that belongs to the new subuser). on-behalf-of does NOT work
 *     for Mail Send/Marketing Campaigns/Contacts (confirmed against
 *     SendGrid's docs this session) - never used for anything past
 *     provisioning.
 *
 * SendGrid's error envelope is {"errors": [{"message": ..., "field": ...}]}
 * - extractError() mirrors EmailMarketingService's own extractError()
 * (this app's Mailgun integration) for the same "some responses aren't
 * JSON at all" fallback.
 */
class SendGridClient
{
    private ?string $bearerToken = null;
    private ?string $onBehalfOf = null;
    private string $baseUrl = 'https://api.sendgrid.com/v3';

    public function __construct(protected ApiService $apiService)
    {
    }

    public function asSubaccount(string $apiKey, string $region = 'global'): self
    {
        $client = clone $this;
        $client->bearerToken = $apiKey;
        $client->onBehalfOf = null;
        $client->baseUrl = $region === 'eu' ? 'https://api.eu.sendgrid.com/v3' : 'https://api.sendgrid.com/v3';

        return $client;
    }

    public function asParent(?string $onBehalfOfUsername = null): self
    {
        $client = clone $this;
        $client->bearerToken = (string) adminSetting('email_marketing.sendgrid.parent_api_key');
        $client->onBehalfOf = $onBehalfOfUsername;
        $client->baseUrl = 'https://api.sendgrid.com/v3';

        return $client;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->request('post', $path, $payload);
    }

    public function patch(string $path, array $payload = []): array
    {
        return $this->request('patch', $path, $payload);
    }

    public function put(string $path, array $payload = []): array
    {
        return $this->request('put', $path, $payload);
    }

    public function delete(string $path): array
    {
        return $this->request('delete', $path);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if (!$this->bearerToken) {
            return ['success' => false, 'error' => 'No SendGrid API key configured for this request.'];
        }

        $headers = ['Authorization' => 'Bearer ' . $this->bearerToken];

        if ($this->onBehalfOf) {
            $headers['on-behalf-of'] = $this->onBehalfOf;
        }

        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $response = $this->apiService->{$method}($url, $headers, $payload, 'json');

        if (!$response['success']) {
            $retryAfter = null;

            if (($response['status'] ?? null) === 429) {
                // SendGrid documents a Retry-After header on 429s, but
                // ApiService::sendRequest() only returns success/status/
                // data/body/restli_id - no response headers - so the real
                // value isn't readable here. A conservative fixed backoff
                // is used instead of guessing at a header we can't see;
                // callers that queue retries treat this as a floor, not an
                // exact value.
                $retryAfter = 30;
            }

            return [
                'success'      => false,
                'status'       => $response['status'] ?? null,
                'error'        => $this->extractError($response),
                'retry_after'  => $retryAfter,
                'raw'          => $response,
            ];
        }

        return [
            'success' => true,
            'status'  => $response['status'] ?? null,
            'data'    => $response['data'] ?? null,
        ];
    }

    private function extractError(array $response): string
    {
        $message = $response['data']['errors'][0]['message']
            ?? $response['error']
            ?? trim((string) ($response['body'] ?? ''));

        return $message !== '' ? $message : 'SendGrid API request failed.';
    }
}
