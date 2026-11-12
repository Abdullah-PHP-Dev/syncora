<?php

namespace App\Services\Crm;

use App\Jobs\EmailMarketing\PollSendGridContactImport;
use App\Models\EmailMarketing\EmailList;
use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\EmailMarketingServices\SendGridContactService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * CRM contacts → an Email Marketing list (C-11, automations). Uses the same
 * records and the same "save locally, then best-effort SendGrid sync" path as
 * the Email Marketing CSV import, so nothing is duplicated.
 */
class EmailListBridge
{
    /** @param  Collection<int, \App\Models\Crm\Contact>  $contacts */
    public function add(EmailList $list, Collection $contacts): array
    {
        $workspaceId = $list->user_id;
        $added = [];
        $skipped = 0;
        foreach ($contacts as $contact) {
            if (! $contact->email || ! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }
            $subscriber = EmailSubscriber::firstOrCreate(
                ['user_id' => $workspaceId, 'email' => mb_strtolower($contact->email)],
                ['name' => $contact->full_name, 'status' => 'subscribed', 'source' => 'crm']
            );
            $list->subscribers()->syncWithoutDetaching([$subscriber->id]);
            $added[] = $subscriber;
        }

        $subaccount = $added ? EmailSubaccount::where('user_id', $workspaceId)->where('status', 'active')->first() : null;
        if ($subaccount) {
            try {
                $result = app(SendGridContactService::class)->upsertBatch($subaccount, $added, $list);
                if (($result['success'] ?? false) && ($result['job_id'] ?? null)) {
                    foreach ($added as $subscriber) {
                        if (! $subscriber->sendgrid_contact_id) {
                            PollSendGridContactImport::dispatch($subaccount->id, $subscriber->id, $result['job_id']);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CRM → email list SendGrid sync failed.', ['list_id' => $list->id, 'error' => $e->getMessage()]);
            }
        }

        return ['added' => count($added), 'skipped' => $skipped];
    }
}
