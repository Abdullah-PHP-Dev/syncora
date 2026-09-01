<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\TiktokMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * No GET verify() handshake here (unlike the Meta-family controllers) -
 * TikTok's webhook subscription is configured via an authenticated POST
 * to business/webhook/update/ (see TiktokMessagingService::
 * subscribeToWebhooks()), not a hub.challenge-style GET challenge.
 */
class TiktokWebhookController extends Controller
{
    public function __construct(protected TiktokMessagingService $service)
    {
    }

    public function receive(Request $request)
    {
        if (!$this->service->verifySignature($request)) {
            Log::warning('TikTok Business Messaging webhook signature mismatch.', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all());

        return response('OK', 200);
    }
}
