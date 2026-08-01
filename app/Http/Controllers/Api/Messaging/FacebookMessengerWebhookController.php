<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\FacebookMessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookMessengerWebhookController extends Controller
{
    public function __construct(protected FacebookMessengerService $service)
    {
    }

    /**
     * The one-time GET handshake Meta performs when the webhook URL is
     * configured (and periodically re-verifies).
     */
    public function verify(Request $request)
    {
        $challenge = $this->service->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    /**
     * Every subsequent event delivery. Must ack fast (Meta retries
     * aggressively on slow/failed responses and can eventually disable the
     * subscription) - the actual DB write + broadcast happens in a queued
     * job dispatched from inside the service, so this just verifies
     * authenticity and hands the payload off.
     */
    public function receive(Request $request)
    {
        if (!$this->service->verifySignature($request)) {
            Log::warning('Facebook Messenger webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all());

        return response('EVENT_RECEIVED', 200);
    }
}
