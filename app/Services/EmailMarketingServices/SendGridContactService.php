<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;

/**
 * PUT /v3/marketing/contacts is async - it returns a job_id, not the
 * synced contact, so upsert()/upsertBatch() only kick off the sync; the
 * contact's sendgrid_contact_id is filled in later by the queued
 * PollSendGridContactImport job (app/Jobs/EmailMarketing/) checking the
 * job's status (confirmed against current docs: there is no synchronous
 * "add contact" call in the Marketing Contacts API).
 */
class SendGridContactService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    public function upsert(EmailSubaccount $subaccount, EmailSubscriber $subscriber, ?EmailList $list = null): array
    {
        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.'];
        }

        $payload = [
            'contacts' => [[
                'email'      => $subscriber->email,
                'first_name' => $subscriber->name,
            ]],
        ];

        if ($list?->sendgrid_list_id) {
            $payload['list_ids'] = [$list->sendgrid_list_id];
        }

        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->put('marketing/contacts', $payload);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'job_id' => $response['data']['job_id'] ?? null];
    }

    /**
     * Same call, multiple contacts in one request - used by the CSV
     * import path so a large list is one SendGrid API call instead of one
     * per row (SendGrid's limit is 30,000 contacts / 6MB per request,
     * confirmed against current docs - well above any CSV this app's
     * import accepts, which is capped at 5MB by EmailSubscriberController).
     */
    public function upsertBatch(EmailSubaccount $subaccount, array $subscribers, ?EmailList $list = null): array
    {
        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'SendGrid subaccount is not active yet.'];
        }

        if (empty($subscribers)) {
            return ['success' => true, 'job_id' => null];
        }

        $payload = [
            'contacts' => array_map(fn (EmailSubscriber $s) => [
                'email'      => $s->email,
                'first_name' => $s->name,
            ], $subscribers),
        ];

        if ($list?->sendgrid_list_id) {
            $payload['list_ids'] = [$list->sendgrid_list_id];
        }

        $response = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region)
            ->put('marketing/contacts', $payload);

        return $response['success']
            ? ['success' => true, 'job_id' => $response['data']['job_id'] ?? null]
            : ['success' => false, 'error' => $response['error']];
    }

    /**
     * Checks a previously-started import job and, once finished, resolves
     * the real SendGrid contact id via the "get contacts by identifier"
     * lookup (the import job status response itself doesn't carry contact
     * ids, only counts - confirmed against current docs) and stores it on
     * the local subscriber row.
     */
    public function checkImportAndResolveId(EmailSubaccount $subaccount, EmailSubscriber $subscriber, string $jobId): array
    {
        $client = $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region);

        $statusResponse = $client->get("marketing/contacts/imports/{$jobId}");

        if (!$statusResponse['success']) {
            return ['success' => false, 'error' => $statusResponse['error']];
        }

        $status = $statusResponse['data']['status'] ?? null;

        if ($status !== 'completed') {
            return ['success' => true, 'status' => $status, 'resolved' => false];
        }

        $lookup = $client->post('marketing/contacts/search/emails', [
            'emails' => [$subscriber->email],
        ]);

        $contactId = $lookup['success']
            ? ($lookup['data']['result'][$subscriber->email]['contact']['id'] ?? null)
            : null;

        if ($contactId) {
            $subscriber->update(['sendgrid_contact_id' => $contactId]);
        }

        return ['success' => true, 'status' => $status, 'resolved' => (bool) $contactId];
    }
}
