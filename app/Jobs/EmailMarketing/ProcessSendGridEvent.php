<?php

namespace App\Jobs\EmailMarketing;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Services\EmailMarketingServices\SendGridWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendGrid batches multiple events per webhook delivery (a single POST
 * body is a JSON array) - the controller dispatches one of these per
 * event in the batch, not one per request, so a slow/failed row never
 * blocks the others in the same delivery (same per-item isolation
 * reasoning as SendCampaignEmailJob/ProcessInboundMessage elsewhere in
 * this app).
 */
class ProcessSendGridEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $subaccountId, public array $payload)
    {
    }

    public function handle(SendGridWebhookService $service): void
    {
        $subaccount = EmailSubaccount::find($this->subaccountId);

        if (!$subaccount) {
            return;
        }

        $service->handleEvent($subaccount, $this->payload);
    }
}
