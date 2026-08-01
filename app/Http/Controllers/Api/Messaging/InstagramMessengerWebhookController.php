<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\InstagramMessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstagramMessengerWebhookController extends Controller
{
    public function __construct(protected InstagramMessengerService $service)
    {
    }

    public function verify(Request $request)
    {
        $challenge = $this->service->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (!$this->service->verifySignature($request)) {
            Log::warning('Instagram webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all());

        return response('EVENT_RECEIVED', 200);
    }
}
