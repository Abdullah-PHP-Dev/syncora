<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\GoogleChatMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Like Telegram/LINE/Teams, each Google Chat app's HTTP endpoint is
 * configured individually (Google Cloud Console > Google Chat API >
 * Configuration > App URL) - one Cloud project/service account per
 * channel, not a shared endpoint the way Slack/Zalo/Meta work. The
 * channel id is therefore part of the URL, so this endpoint knows which
 * service account's project_number to verify the inbound JWT against.
 *
 * Google Chat apps can reply synchronously by returning a Message JSON
 * object directly in this response, but that only fits a request/response
 * bot, not this module's async admin-inbox reply flow - so, consistent
 * with every other webhook controller here, this just acknowledges
 * receipt and lets handleEvent() queue the actual processing.
 *
 * Google Chat has no GET challenge/verification handshake (that's a
 * LinkedIn/Meta pattern) - authenticity is established per-request by the
 * bearer JWT that chat@system.gserviceaccount.com signs, which
 * verifyRequestToken() checks against Google's published JWKS with the
 * Cloud project's *project number* as the expected audience.
 */
class GoogleChatWebhookController extends Controller
{
    protected $service;

    public function __construct(GoogleChatMessagingService $service)
    {
        $this->service = $service;
    }

    /**
     * $channel is resolved by implicit route-model binding from the
     * {channel} segment. It was previously declared alongside an unrelated
     * {userId} segment, which meant Laravel injected an *empty* model:
     * $channel->platform was null, so the guard below rejected every real
     * Google Chat delivery with a 401.
     */
    public function receive(MessageChannel $channel, Request $request)
    {
        $tokenValid = $channel->platform === 'google_chat' && $this->service->verifyRequestToken($request, $channel);

        if (!$tokenValid) {
            Log::warning('Google Chat webhook token invalid', [
                'channel_id' => $channel->id,
                'ip'         => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        try {
            $this->service->handleEvent($request->all(), $channel);
        } catch (Throwable $e) {
            // Chat retries on a non-2xx, and a replayed MESSAGE event would
            // just fail the same way - so this is logged and acknowledged
            // rather than surfaced, the same shape as every other inbound
            // webhook here.
            Log::error('Google Chat receive webhook failed: ' . $e->getMessage(), [
                'channel_id' => $channel->id,
                'exception'  => $e->getTraceAsString(),
            ]);
        }

        return response()->json([]);
    }
}
