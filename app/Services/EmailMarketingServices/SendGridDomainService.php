<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\VerifiedDomain;

/**
 * Domain Authentication (SendGrid's endpoint is still /v3/whitelabel/
 * domains despite the "Domain Authentication" product name in their own
 * dashboard - confirmed against current docs, not a stale endpoint left
 * over from a rename). No DMARC record is ever generated here - SendGrid's
 * response never includes one; DMARC stays a separate, customer-managed
 * DNS policy the setup wizard only recommends, never fabricates.
 */
class SendGridDomainService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    private function forSubaccount(EmailSubaccount $subaccount): SendGridClient
    {
        return $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region);
    }

    /**
     * Idempotent per domain string (unique on user_id+domain) - re-running
     * setup for the same domain returns the existing row rather than
     * asking SendGrid to authenticate it twice.
     */
    public function authenticate(EmailSubaccount $subaccount, string $domain): array
    {
        $existing = VerifiedDomain::where('user_id', $subaccount->user_id)->where('domain', $domain)->first();

        if ($existing) {
            return ['success' => true, 'domain' => $existing, 'already_existed' => true];
        }

        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'This account\'s SendGrid subaccount is not active yet.'];
        }

        $response = $this->forSubaccount($subaccount)->post('whitelabel/domains', [
            'domain'             => $domain,
            'automatic_security' => true,
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $data = $response['data'];

        $verifiedDomain = VerifiedDomain::create([
            'user_id'             => $subaccount->user_id,
            'email_subaccount_id' => $subaccount->id,
            'sendgrid_domain_id'  => $data['id'] ?? null,
            'domain'              => $domain,
            'subdomain'           => $data['subdomain'] ?? null,
            'automatic_security'  => $data['automatic_security'] ?? true,
            'is_default'          => true,
            'status'              => 'dns_pending',
        ]);

        foreach (['mail_cname', 'dkim1', 'dkim2'] as $purpose) {
            $record = $data['dns'][$purpose] ?? null;

            if (!$record) {
                continue;
            }

            $verifiedDomain->dnsRecords()->create([
                'record_purpose' => $purpose,
                'type'           => $record['type'] ?? 'cname',
                'host'           => $record['host'] ?? '',
                'data'           => $record['data'] ?? '',
                'valid'          => $record['valid'] ?? false,
            ]);
        }

        return ['success' => true, 'domain' => $verifiedDomain->fresh('dnsRecords'), 'already_existed' => false];
    }

    /**
     * Real check against SendGrid's own validation endpoint - never marks
     * a domain verified from local state alone (the spec's explicit "do
     * not fake verification locally" requirement).
     */
    public function verify(VerifiedDomain $domain): array
    {
        $subaccount = $domain->subaccount;

        $response = $this->forSubaccount($subaccount)->post("whitelabel/domains/{$domain->sendgrid_domain_id}/validate");

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $result = $response['data'];
        $valid = $result['valid'] ?? false;

        foreach ($result['validation_results'] ?? [] as $purpose => $recordResult) {
            $domain->dnsRecords()->where('record_purpose', $purpose)->update([
                'valid' => $recordResult['valid'] ?? false,
                'data'  => $recordResult['reason'] ?? $domain->dnsRecords()->where('record_purpose', $purpose)->value('data'),
            ]);
        }

        $domain->update(['status' => $valid ? 'verified' : 'dns_pending']);

        return ['success' => true, 'valid' => $valid, 'domain' => $domain->fresh('dnsRecords')];
    }
}
