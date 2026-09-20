<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\VerifiedDomain;
use App\Services\ApiService;
use Illuminate\Support\Facades\Log;

/**
 * Automates DNS record creation for SendGrid Domain Authentication on the
 * platform's own Cloudflare zone (config('services.cloudflare.zone_id') -
 * one zone, not a per-seller credential). Reuses ApiService, the same
 * generic HTTP wrapper SendGridClient itself wraps, rather than adding a
 * second HTTP layer.
 *
 * Scope, deliberately narrow: this only ever touches the ONE configured
 * zone, and only when a seller's requested domain actually resolves under
 * it (checked live via getZone() + domainBelongsToZone(), never assumed -
 * see that method's docblock). Any domain that isn't on this zone falls
 * back to the existing manual DNS-instructions flow in
 * resources/views/admin/email/setup/index.blade.php, completely
 * unchanged - this service is an additional capability, not a
 * replacement for it.
 *
 * Current official Cloudflare API v4 (developers.cloudflare.com/api/),
 * Bearer-token auth (not the legacy X-Auth-Email/X-Auth-Key scheme).
 */
class CloudflareDnsService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    public function __construct(protected ApiService $apiService)
    {
    }

    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare.api_token'))
            && filled(config('services.cloudflare.zone_id'));
    }

    /**
     * GET /user/tokens/verify - the documented way to check a token is
     * live and active without needing any other permission (this alone
     * works even for the most narrowly-scoped token, since token
     * verification is not itself a permission-gated capability).
     */
    public function validateCredentials(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Cloudflare is not configured (CLOUDFLARE_API_TOKEN/CLOUDFLARE_ZONE_ID missing).'];
        }

        $response = $this->request('get', '/user/tokens/verify');

        if (!$response['success']) {
            Log::warning('cloudflare.token.invalid', ['error' => $response['error']]);

            return $response;
        }

        Log::info('cloudflare.token.validated', ['status' => $response['data']['status'] ?? null]);

        return ['success' => true, 'status' => $response['data']['status'] ?? null];
    }

    /**
     * GET /zones/{zone_id} - the configured zone's own record, including
     * its real root domain name (eg. "socialeaz.com"). This is the
     * ground truth domainBelongsToZone() compares against - never
     * hard-coded, since the zone's actual name is the one thing that
     * can't be assumed from a bare zone id.
     */
    public function getZone(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Cloudflare is not configured.'];
        }

        $response = $this->request('get', '/zones/' . config('services.cloudflare.zone_id'));

        if (!$response['success']) {
            Log::warning('cloudflare.zone.fetch_failed', ['error' => $response['error']]);

            return $response;
        }

        return ['success' => true, 'zone' => $response['data']];
    }

    /**
     * Real ownership check, not an assumption: fetches the configured
     * zone's actual name from Cloudflare and checks whether the
     * requested domain IS that zone or a subdomain of it. A seller
     * entering an unrelated domain (or a domain from a different
     * customer's own Cloudflare account entirely) always fails this
     * check and never reaches any write call - this is what keeps one
     * seller from being able to touch DNS for a domain that isn't
     * actually on this platform's zone.
     */
    public function domainBelongsToZone(string $domain): array
    {
        $zoneResult = $this->getZone();

        if (!$zoneResult['success']) {
            return ['success' => false, 'error' => $zoneResult['error'], 'belongs' => false];
        }

        $zoneName = strtolower($zoneResult['zone']['name'] ?? '');
        $domain = strtolower($domain);
        $belongs = $zoneName !== '' && ($domain === $zoneName || str_ends_with($domain, '.' . $zoneName));

        return ['success' => true, 'belongs' => $belongs, 'zone_name' => $zoneName];
    }

    /**
     * GET /zones/{zone_id}/dns_records?type=...&name=... - filtered
     * server-side rather than fetching the whole zone and searching in
     * memory, so this stays cheap regardless of how many unrelated
     * records (MX, other CNAMEs, TXT, etc.) the zone already has.
     */
    public function listRecords(?string $type = null, ?string $name = null): array
    {
        $query = array_filter(['type' => $type, 'name' => $name]);

        $response = $this->request('get', '/zones/' . config('services.cloudflare.zone_id') . '/dns_records', $query);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'records' => []];
        }

        return ['success' => true, 'records' => $response['data'] ?? []];
    }

    public function findRecord(string $type, string $name): ?array
    {
        $result = $this->listRecords($type, $name);

        return $result['records'][0] ?? null;
    }

    /**
     * ttl=1 is Cloudflare's own documented sentinel for "Automatic" (not
     * a literal 1-second TTL) - matches what the Cloudflare dashboard
     * itself shows as "Auto".
     */
    public function createRecord(string $type, string $name, string $content, bool $proxied = false, int $ttl = 1): array
    {
        $response = $this->request('post', '/zones/' . config('services.cloudflare.zone_id') . '/dns_records', [
            'type'    => $type,
            'name'    => $name,
            'content' => $content,
            'ttl'     => $ttl,
            'proxied' => $proxied,
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'record' => $response['data']];
    }

    public function updateRecord(string $recordId, array $fields): array
    {
        $response = $this->request('patch', '/zones/' . config('services.cloudflare.zone_id') . '/dns_records/' . $recordId, $fields);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'record' => $response['data']];
    }

    /**
     * The idempotency core (safe to call any number of times - see the
     * migration/service docblocks for why no separate "already
     * configured" flag is needed beyond this real, live check each time):
     *
     *   - No record at that type+name at all         -> create it.
     *   - Record exists, content matches, not proxied -> already correct,
     *     skip (no API write at all).
     *   - Record exists, content matches, IS proxied  -> auto-fix: flip
     *     proxied to false. This is the one case safe to change
     *     automatically without asking, because a proxied CNAME at a
     *     SendGrid authentication hostname is never intentionally
     *     correct for anything else - it only ever breaks verification
     *     (confirmed live this session: this exact misconfiguration was
     *     the real, only reason em2305.socialeaz.com failed to verify
     *     until the proxy flag was switched off manually).
     *   - Record exists, content differs entirely      -> genuine
     *     conflict. Never overwritten automatically - reported back so
     *     a human decides, since an unrelated existing record at that
     *     exact hostname might be intentional.
     */
    public function ensureRecord(string $type, string $name, string $content): array
    {
        $existing = $this->findRecord($type, $name);

        if (!$existing) {
            $result = $this->createRecord($type, $name, $content, proxied: false);

            if (!$result['success']) {
                Log::warning('cloudflare.dns.record.create_failed', ['name' => $name, 'type' => $type, 'error' => $result['error']]);

                return ['outcome' => 'error', 'name' => $name, 'type' => $type, 'error' => $result['error']];
            }

            Log::info('cloudflare.dns.record.created', ['name' => $name, 'type' => $type]);

            return ['outcome' => 'created', 'name' => $name, 'type' => $type];
        }

        $sameContent = rtrim(strtolower($existing['content'] ?? ''), '.') === rtrim(strtolower($content), '.');

        if (!$sameContent) {
            Log::warning('cloudflare.dns.record.conflict', ['name' => $name, 'type' => $type]);

            return [
                'outcome'  => 'conflict',
                'name'     => $name,
                'type'     => $type,
                'expected' => $content,
                'existing' => $existing['content'] ?? null,
            ];
        }

        if ($existing['proxied'] ?? false) {
            $result = $this->updateRecord($existing['id'], ['proxied' => false]);

            if (!$result['success']) {
                Log::warning('cloudflare.dns.record.unproxy_failed', ['name' => $name, 'error' => $result['error']]);

                return ['outcome' => 'error', 'name' => $name, 'type' => $type, 'error' => $result['error']];
            }

            Log::info('cloudflare.dns.record.unproxied', ['name' => $name, 'type' => $type]);

            return ['outcome' => 'updated', 'name' => $name, 'type' => $type, 'reason' => 'was proxied, switched to DNS only'];
        }

        return ['outcome' => 'skipped', 'name' => $name, 'type' => $type];
    }

    /**
     * Orchestrates the whole thing for one VerifiedDomain: validates the
     * domain is actually on the configured zone, then ensures every DNS
     * record SendGrid returned (already stored in domain_dns_records by
     * SendGridDomainService::authenticate() - never re-derived or
     * hard-coded here) exists correctly. Returns a structured, per-record
     * summary the setup page renders as the requested
     * created/skipped/conflicted table.
     */
    public function configureSendGridRecords(VerifiedDomain $domain): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Cloudflare is not configured for this platform yet.'];
        }

        $zoneCheck = $this->domainBelongsToZone($domain->domain);

        if (!$zoneCheck['success']) {
            return ['success' => false, 'error' => $zoneCheck['error']];
        }

        if (!$zoneCheck['belongs']) {
            Log::info('cloudflare.dns.domain_not_in_zone', ['domain' => $domain->domain, 'zone' => $zoneCheck['zone_name']]);

            return [
                'success' => false,
                'error'   => "This domain isn't on the platform's Cloudflare zone ({$zoneCheck['zone_name']}) - add the DNS records below manually with your own DNS provider.",
            ];
        }

        $results = [];

        foreach ($domain->dnsRecords as $record) {
            $results[] = array_merge(
                ['purpose' => $record->record_purpose, 'host' => $record->host],
                $this->ensureRecord(strtoupper($record->type), $record->host, $record->data)
            );
        }

        $domain->update(['dns_last_synced_at' => now()]);

        Log::info('cloudflare.dns.sync_completed', [
            'domain'     => $domain->domain,
            'created'    => collect($results)->where('outcome', 'created')->count(),
            'skipped'    => collect($results)->where('outcome', 'skipped')->count(),
            'updated'    => collect($results)->where('outcome', 'updated')->count(),
            'conflicted' => collect($results)->where('outcome', 'conflict')->count(),
            'errored'    => collect($results)->where('outcome', 'error')->count(),
        ]);

        return ['success' => true, 'records' => $results];
    }

    /**
     * Bearer-token auth (current documented method - the legacy
     * X-Auth-Email/X-Auth-Key global-key scheme is deliberately not
     * supported here). Never logs the token itself - only ApiService's
     * own generic exception logger sees the request, and that logs the
     * URL/method/message, never headers.
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $headers = ['Authorization' => 'Bearer ' . config('services.cloudflare.api_token')];
        $url = self::BASE_URL . $path;

        $response = $this->apiService->{$method}($url, $headers, $payload, 'json');

        if (!($response['success'] ?? false)) {
            return ['success' => false, 'status' => $response['status'] ?? null, 'error' => $this->extractError($response)];
        }

        $data = $response['data'] ?? [];

        if (($data['success'] ?? true) === false) {
            return ['success' => false, 'status' => $response['status'] ?? null, 'error' => $this->extractError($response)];
        }

        return ['success' => true, 'status' => $response['status'] ?? null, 'data' => $data['result'] ?? null];
    }

    /**
     * Cloudflare's error envelope is {"success":false,"errors":[{"code":...,
     * "message":...}],...} - mirrors SendGridClient::extractError()'s own
     * "some responses aren't the expected shape at all" fallback.
     */
    private function extractError(array $response): string
    {
        $message = $response['data']['errors'][0]['message']
            ?? $response['error']
            ?? trim((string) ($response['body'] ?? ''));

        return $message !== '' ? $message : 'Cloudflare API request failed.';
    }
}
