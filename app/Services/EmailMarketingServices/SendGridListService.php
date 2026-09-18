<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;

/**
 * Mirrors a local EmailList into SendGrid's own Marketing Lists
 * (/v3/marketing/lists) - a Single Send can only target a SendGrid list
 * id, so every list needs one before it can ever be used as a campaign
 * audience. Sync is best-effort: the local row is always the one the rest
 * of this app's UI reads from, matching the existing
 * "save locally, sync the external side in an outer try/catch" pattern
 * PostAccountController already uses for stats/webhooks after connecting
 * a social account.
 */
class SendGridListService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    public function sync(EmailSubaccount $subaccount, EmailList $list): array
    {
        if ($list->sendgrid_list_id) {
            return ['success' => true, 'already_synced' => true];
        }

        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.'];
        }

        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->post('marketing/lists', ['name' => $list->name]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $list->update(['sendgrid_list_id' => $response['data']['id'] ?? null]);

        return ['success' => true, 'already_synced' => false];
    }
}
