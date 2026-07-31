<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\SlackMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * One shared webhook URL for the whole Slack app (set once, in the app's
 * "Event Subscriptions" Request URL field) covering every workspace that
 * installs it - same "shared endpoint, disambiguate from the payload"
 * shape as Meta/Zalo, using the event envelope's own `team_id` to resolve
 * which local channel it belongs to.
 *
 * Unlike Meta's webhook (a separate GET for the verification handshake),
 * Slack's handshake is a one-time POST with `type: url_verification` sent
 * to this same URL the first time it's saved in the app config - answered
 * inline here, before any channel lookup, since it isn't tied to a
 * specific installed workspace at all.
 */
class SlackWebhookController extends Controller
{
    public function __construct(protected SlackMessagingService $service)
    {
    }

    public function receive(Request $request)
    {
        if (!$this->service->verifySignature($request)) {
            Log::warning('Slack webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $payload = $request->all();

        if (($payload['type'] ?? null) === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge'] ?? '']);
        }

        if (($payload['type'] ?? null) !== 'event_callback') {
            return response('OK', 200);
        }

        $teamId = $payload['team_id'] ?? null;
        $channel = $teamId ? MessageChannel::where('platform', 'slack')->where('external_id', $teamId)->first() : null;

        if (!$channel) {
            Log::warning('Slack webhook for unknown workspace', ['team_id' => $teamId]);

            return response('OK', 200);
        }

        $this->service->handleWebhook($payload, $channel);

        return response('OK', 200);
    }
}
