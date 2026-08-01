<?php

namespace App\Jobs\EmailMarketing;

use App\Models\EmailMarketing\EmailCampaignSend;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one campaign email to one subscriber - dispatched once per
 * recipient by EmailMarketingService::dispatchCampaign() rather than
 * batching, so a slow/failed send for one recipient never blocks or fails
 * the others (the same per-recipient isolation reasoning as
 * ProcessInboundMessage handling one inbound message at a time).
 */
class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $sendId)
    {
    }

    public function handle(EmailMarketingService $service): void
    {
        $send = EmailCampaignSend::with('campaign', 'subscriber')->find($this->sendId);

        if (!$send || !$send->campaign || !$send->subscriber) {
            return;
        }

        // Already handled by an earlier attempt/duplicate dispatch -
        // nothing to do (also guards against a retried job re-sending a
        // message that actually succeeded before a transient failure
        // elsewhere in the job).
        if ($send->status !== 'pending') {
            return;
        }

        $subscriber = $send->subscriber;

        if (!$subscriber->isSendable()) {
            $send->update([
                'status'        => $subscriber->status,
                'error_message' => "Subscriber is {$subscriber->status}; send skipped.",
            ]);
            $service->finalizeCampaignIfComplete($send->campaign);

            return;
        }

        $send->update(['status' => 'sending']);

        $result = $service->sendCampaignEmail($send);

        if ($result['success'] ?? false) {
            $send->update([
                'status'              => 'sent',
                'sent_at'             => now(),
                'mailgun_message_id'  => $result['mailgun_message_id'] ?? null,
            ]);
            $send->campaign->increment('sent_count');
        } else {
            $send->update([
                'status'        => 'failed',
                'error_message' => $result['error'] ?? 'Send failed.',
            ]);
            $send->campaign->increment('failed_count');
        }

        $service->finalizeCampaignIfComplete($send->campaign);
    }
}
