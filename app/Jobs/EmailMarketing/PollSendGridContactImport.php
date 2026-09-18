<?php

namespace App\Jobs\EmailMarketing;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\EmailSubscriber;
use App\Services\EmailMarketingServices\SendGridContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PUT /v3/marketing/contacts only returns a job_id - the contact's real
 * SendGrid id isn't known until that import job finishes, so this
 * self-redispatches on a delay until it does (same shape as
 * ResolveTiktokPublishStatus: constructor takes the attempt count,
 * re-dispatches itself rather than a declarative $tries/$backoff, capped
 * at a hard attempt limit rather than retrying forever).
 */
class PollSendGridContactImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $subaccountId,
        public int $subscriberId,
        public string $jobId,
        public int $attempt = 1,
    ) {
    }

    public function handle(SendGridContactService $service): void
    {
        $subaccount = EmailSubaccount::find($this->subaccountId);
        $subscriber = EmailSubscriber::find($this->subscriberId);

        if (!$subaccount || !$subscriber || $subscriber->sendgrid_contact_id) {
            return;
        }

        $result = $service->checkImportAndResolveId($subaccount, $subscriber, $this->jobId);

        if (($result['success'] ?? false) && ($result['resolved'] ?? false)) {
            return;
        }

        if ($this->attempt >= 10) {
            return;
        }

        self::dispatch($this->subaccountId, $this->subscriberId, $this->jobId, $this->attempt + 1)
            ->delay(now()->addSeconds(10));
    }
}
