<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\TeamsMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Like Telegram and LINE, each Azure Bot registration gets its own
 * "Messaging endpoint" URL, set by hand in the Azure Portal - so (unlike
 * Slack/Zalo/Meta's one-shared-endpoint-per-app shape) the channel is
 * identified straight from the route, not looked up from the payload.
 */
class TeamsWebhookController extends Controller
{
    public function __construct(protected TeamsMessagingService $service)
    {
    }

    public function receive(Request $request, MessageChannel $channel)
    {
        if ($channel->platform !== 'teams' || !$this->service->verifyActivityToken($request, $channel)) {
            Log::warning('Teams webhook token invalid', ['channel_id' => $channel->id, 'ip' => $request->ip()]);

            return response('Unauthorized', 401);
        }

        $this->service->handleActivity($request->all(), $channel);

        return response('', 200);
    }
}
