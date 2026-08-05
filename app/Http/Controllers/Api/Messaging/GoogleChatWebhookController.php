<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\GoogleChatMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Messaging\Conversation;
use Throwable;
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
        try {
Conversation::create([
                'platform'               => 'google',
                'message_channel_id' => 5,
                'external_conversation_id' => null,
                'meta'                   =>json_encode($request->all()),
                'user_id'                => 1,
                'customer_external_id'   => '34543',
                'unread_count'           => 1,
                'status'                 => true,
                'assigned_user_id'       => 1,
            ]);
            // 1. Verify channel platform & token before saving or processing
            if ($channel->platform !== 'google_chat' || !$this->service->verifyRequestToken($request, $channel)) {
            Conversation::create([
                'platform'               => 'google',
                'message_channel_id' => 5,
                'external_conversation_id' => null,
                'meta'                   =>json_encode($request->all()),
                'user_id'                => 1,
                'customer_external_id'   => '34543',
                'unread_count'           => 1,
                'status'                 => true,
                'assigned_user_id'       => 1,
            ]);
                Log::warning('Google Chat webhook token invalid', [
                    'channel_id' => $channel->id, 
                    'ip' => $request->ip()
                ]);

                return response('Unauthorized', 401);
            }

            // 2. Create the conversation record
            Conversation::create([
                'platform'               => 'google',
                'external_conversation_id' => null,
                'meta'                   => json_encode($request->all()),
                'user_id'                => 1,
                'customer_external_id'   => '34543',
                'unread_count'           => 1,
                'status'                 => true,
                'assigned_user_id'       => 1,
            ]);

            // 3. Process the webhook event
            $this->service->handleEvent($request->all(), $channel);

            return response()->json([]);

        } catch (Throwable $e) {
            // Log to default Laravel log files
            Log::error('Google Chat receive webhook failed: ' . $e->getMessage(), [
                'channel_id' => $channel->id ?? null,
                'exception'  => $e->getTraceAsString(),
                'payload'    => $request->all(),
            ]);

            // Save error details directly into your database log table
            try {
                Conversation::create([
                'platform'               => 'google',
                'external_conversation_id' => null,
                'meta'                   => json_encode($e->getMessage()),
                'user_id'                => 1,
                'customer_external_id'   => '34543',
                'unread_count'           => 1,
                'status'                 => true,
                'assigned_user_id'       => 1,
            ]);
            } catch (Throwable $dbEx) {
                // Fallback in case database insert fails
                Log::critical('Failed to save webhook error to database: ' . $dbEx->getMessage());
            }

            return response()->json([
                'error'   => 'Internal server error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
