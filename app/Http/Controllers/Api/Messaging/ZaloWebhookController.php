<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\ZaloMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * One shared webhook URL per Zalo App (configured once in the Zalo
 * Developers Console), potentially covering several linked Official
 * Accounts - same "shared endpoint, disambiguate from the payload" shape
 * as the three Meta platforms, using the event's own `recipient.id` (the
 * receiving OA's ID) to resolve which local channel it belongs to. No GET
 * verification handshake the way Meta's webhooks need - Zalo's console
 * just takes the URL directly.
 */
class ZaloWebhookController extends Controller
{
    public function __construct(protected ZaloMessagingService $service)
    {
    }

    public function receive(Request $request)
    {
        $payload = $request->all();
        $oaId = $payload['recipient']['id'] ?? null;

        $channel = $oaId ? MessageChannel::where('platform', 'zalo')->where('external_id', $oaId)->first() : null;

        if (!$channel) {
            Log::warning('Zalo webhook for unknown OA', ['oa_id' => $oaId]);

            return response('OK', 200);
        }

        if (!$this->service->verifySignature($request, $channel, $payload)) {
            Log::warning('Zalo webhook signature mismatch', ['channel_id' => $channel->id, 'ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $this->service->handleWebhook($payload, $channel);

        return response('OK', 200);
    }
}
