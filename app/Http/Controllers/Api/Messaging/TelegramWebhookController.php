<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\TelegramMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Unlike the three Meta platforms (one app-level webhook URL for every
 * connected Page/account), each Telegram bot has its own webhook URL set
 * individually via setWebhook() - so the channel is identified straight
 * from the route rather than looked up from the payload body.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(protected TelegramMessagingService $service)
    {
    }

    public function receive(Request $request, MessageChannel $channel)
    {
        if ($channel->platform !== 'telegram' || !$this->service->verifySignature($request, $channel)) {
            Log::warning('Telegram webhook secret token mismatch', ['channel_id' => $channel->id, 'ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($request->all(), $channel);

        return response('OK', 200);
    }
}
