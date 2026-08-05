<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\GoogleChatMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Messaging\Conversation;

/**
 * Like Telegram/LINE/Teams, each Google Chat app's HTTP endpoint is
 * configured individually (Google Cloud Console > Google Chat API >
 * Configuration > App URL) - one Cloud project/service account per
 * channel, not a shared endpoint the way Slack/Zalo/Meta work.
 *
 * Google Chat apps can reply synchronously by returning a Message JSON
 * object directly in this response, but that only fits a request/response
 * bot, not this module's async admin-inbox reply flow - so, consistent
 * with every other webhook controller here, this just acknowledges
 * receipt and lets handleEvent() queue the actual processing.
 */
class GoogleChatWebhookController extends Controller
{
    protected $service;

    public function __construct(GoogleChatMessagingService $service)
    {
        $this->service = $service;
    }

    public function receive(Request $request, MessageChannel $channel)
    {
        Conversation::Create([
            'platform'      => 'google',
            'external_conversation_id'        => null,
            'meta'        => json_encode($request->all()),
            'user_id'     => 1,
            'customer_external_id' =>'34543',
            'unread_count'   => 1,
            'status' => true,
            'assigned_user_id' => 1
        ]);
        if ($channel->platform !== 'google_chat' || !$this->service->verifyRequestToken($request, $channel)) {
            Log::warning('Google Chat webhook token invalid', ['channel_id' => $channel->id, 'ip' => $request->ip()]);

            return response('Unauthorized', 401);
        }

        $this->service->handleEvent($request->all(), $channel);

        return response()->json([]);
    }
}
