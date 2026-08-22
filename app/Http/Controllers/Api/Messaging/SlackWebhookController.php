<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\SlackMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Messaging\Conversation;

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
         Conversation::create([
            'message_channel_id'       => 26,
            'platform'                 => 'slack',
            'assigned_user_id'         => 1,
            'external_conversation_id' => 'debug-hit',
            'customer_external_id'     => 'debug-' . now()->format('YmdHis'),
            'customer_name'            => 'DEBUG webhook hit',
            'last_message_preview'     => substr(json_encode($request->all()), 0, 500),
            'meta'                     => [
                'debug'     => true,
                'event_type' => null,
                'subtype'   => null,
                'has_files' => '',
                'ip'        => $request->ip(),
                'raw_body'  => $request->all(),
            ],
        ]);
        // Nothing writes to the database before this point. The debug
        // insert below used to run first, unconditionally, against a
        // hardcoded message_channel_id - which both let any unauthenticated
        // caller create conversation rows and 500'd the whole endpoint with
        // an FK violation whenever that literal id didn't exist locally.
        // Slack retries a failing Request URL and eventually disables it,
        // so that crash took down every delivery, images included.
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

        $this->recordDebugHit($request, $channel, $payload);

        $this->service->handleWebhook($payload, $channel);

        return response('OK', 200);
    }

    /**
     * Temporary diagnostic left over from tracing why inbound Slack
     * deliveries weren't landing - writes one row per verified delivery so
     * the raw envelope can be inspected from the admin. Runs against the
     * channel already resolved from the payload's team_id, and only after
     * verifySignature() has passed, so it can't be driven by an
     * unauthenticated caller. Delete this method and its call once inbound
     * Slack messages are confirmed working.
     */
    private function recordDebugHit(Request $request, MessageChannel $channel, array $payload): void
    {
        Conversation::create([
            'message_channel_id'       => $channel->id,
            'platform'                 => 'slack',
            'assigned_user_id'         => 1,
            'external_conversation_id' => 'debug-hit',
            'customer_external_id'     => 'debug-' . now()->format('YmdHis'),
            'customer_name'            => 'DEBUG webhook hit',
            'last_message_preview'     => substr(json_encode($payload), 0, 500),
            'meta'                     => [
                'debug'     => true,
                'event_type' => $payload['event']['type'] ?? null,
                'subtype'   => $payload['event']['subtype'] ?? null,
                'has_files' => !empty($payload['event']['files']),
                'ip'        => $request->ip(),
                'raw_body'  => $payload,
            ],
        ]);
    }
}
