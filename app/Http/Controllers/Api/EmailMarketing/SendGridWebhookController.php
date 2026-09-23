<?php

namespace App\Http\Controllers\Api\EmailMarketing;

use App\Http\Controllers\Controller;
use App\Jobs\EmailMarketing\ProcessSendGridEvent;
use App\Models\WebhookLog;
use App\Services\EmailMarketingServices\SendGridWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One shared app-level URL for every seller's SendGrid Event Webhook
 * (see SendGridWebhookService's docblock for how the right subaccount is
 * identified without a URL segment). Signature must be verified against
 * the RAW request body - SendGrid's own docs warn that re-serializing the
 * parsed JSON can silently change bytes used in the signature, so
 * $request->getContent() is used here rather than $request->all()/json().
 */
class SendGridWebhookController extends Controller
{
    public function __construct(protected SendGridWebhookService $service)
    {
    }

    public function receive(Request $request): JsonResponse
    {
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $rawBody = $request->getContent();

        $subaccount = ($timestamp && $signature)
            ? $this->service->verifyAndIdentify($timestamp, $signature, $rawBody)
            : null;

        if (!$subaccount) {
            WebhookLog::create([
                'platform'        => 'sendgrid',
                'event_type'      => null,
                'signature_valid' => false,
                'processed'       => false,
                'note'            => 'Signature verification failed or no matching subaccount - request rejected before handling.',
                'payload'         => $request->all(),
                'ip'              => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        // SendGrid delivers a JSON array of events per request, not one
        // event per delivery - each is queued independently.
        $events = json_decode($rawBody, true) ?: [];

        foreach ($events as $event) {
            ProcessSendGridEvent::dispatch($subaccount->id, $event);
        }

        WebhookLog::create([
            'platform'        => 'sendgrid',
            'event_type'      => 'batch',
            'signature_valid' => true,
            'processed'       => true,
            'note'            => count($events) . ' event(s) queued for processing.',
            'payload'         => ['count' => count($events)],
            'ip'              => $request->ip(),
        ]);

        return response()->json(['message' => 'ok']);
    }
}
