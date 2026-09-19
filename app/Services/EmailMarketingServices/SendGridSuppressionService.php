<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailSubaccount;

/**
 * SendGrid's Suppression Groups / ASM (unsubscribe groups) - /v3/asm/groups.
 * Fetched live from each seller's own subaccount rather than mirrored into
 * a local table: a seller's groups are lightweight, rarely change, and can
 * be edited directly on SendGrid's side (renamed, deleted) - a local copy
 * would just be a second, potentially-stale source of truth for something
 * SendGrid itself is the only real owner of. Every marketing campaign must
 * carry a real group id (see EmailCampaign.suppression_group_id and
 * SendGridCampaignService::preflight()'s "Unsubscribe group selected"
 * check) - this service is what lets a seller actually pick or create one
 * instead of the app silently sending with none, which is what the old
 * single global `email_marketing.sendgrid.suppression_group_id` admin
 * setting effectively did for any seller whose subaccount didn't happen to
 * own that specific group id.
 */
class SendGridSuppressionService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    private function forSubaccount(EmailSubaccount $subaccount): SendGridClient
    {
        return $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region);
    }

    public function listGroups(EmailSubaccount $subaccount): array
    {
        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.', 'groups' => []];
        }

        $response = $this->forSubaccount($subaccount)->get('asm/groups');

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'groups' => []];
        }

        return ['success' => true, 'groups' => $response['data'] ?? []];
    }

    public function createGroup(EmailSubaccount $subaccount, string $name, ?string $description = null): array
    {
        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.'];
        }

        $response = $this->forSubaccount($subaccount)->post('asm/groups', [
            'name'        => $name,
            'description' => $description ?? '',
            'is_default'  => false,
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'group' => $response['data']];
    }
}
