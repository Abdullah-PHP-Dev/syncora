<?php

namespace App\Http\Controllers\Api\EmailMarketing;

use App\Http\Controllers\Controller;
use App\Services\EmailMarketingServices\EmailMarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One shared endpoint for every Mailgun event type this module cares about
 * (delivered/opened/clicked/unsubscribed/complained/failed) - Mailgun lets
 * each event type point at a different URL, but they all carry the same
 * signature + event-data envelope, so registering all of them at this one
 * URL in the Mailgun dashboard's Webhooks settings is simplest, the same
 * "one shared endpoint" pattern SlackWebhookController and
 * ZaloWebhookController already use for that platform's various event
 * types.
 */
class MailgunWebhookController extends Controller
{
    public function __construct(protected EmailMarketingService $service)
    {
    }

    public function receive(Request $request): JsonResponse
    {
        if (!$this->service->verifyWebhookSignature($request->input('signature', []))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $this->service->handleWebhookEvent($request->all());

        return response()->json(['message' => 'ok']);
    }
}
