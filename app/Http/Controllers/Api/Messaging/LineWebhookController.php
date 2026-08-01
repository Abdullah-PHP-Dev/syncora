<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Like Telegram (and unlike the three Meta platforms), each LINE
 * "Messaging API channel" gets its own webhook URL, set manually in the
 * LINE Developers Console - so the channel is identified straight from
 * the route, not looked up from the payload body.
 */
class LineWebhookController extends Controller
{
    public function __construct(protected LineMessagingService $service)
    {
    }

    public function receive(Request $request, MessageChannel $channel)
    {
        if ($channel->platform !== 'line' || !$this->service->verifySignature($request, $channel)) {
            Log::warning('LINE webhook signature mismatch', ['channel_id' => $channel->id, 'ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all(), $channel);

        return response('OK', 200);
    }
}
