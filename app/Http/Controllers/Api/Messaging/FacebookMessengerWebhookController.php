<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Services\MessagingServices\FacebookMessengerService;
use App\Services\PostServices\MetaPostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
/**
 * Facebook Page webhook. Meta only allows ONE registered callback URL per
 * App per object type ("page") - there is no way to have message events
 * (entry[].messaging[]) delivered to one URL and comment events
 * (entry[].changes[]) delivered to a different one. Whichever of this
 * controller or Comments\FacebookCommentWebhookController ends up as the
 * actual registered URL in the App Dashboard must therefore handle both,
 * so both dispatch to both services regardless.
 */
class FacebookMessengerWebhookController extends Controller
{
    public function __construct(
        protected FacebookMessengerService $messengerService,
        protected MetaPostService $postService,
    ) {
    }

    /**
     * The one-time GET handshake Meta performs when the webhook URL is
     * configured (and periodically re-verifies).
     */
    public function verify(Request $request)
    {
        // messaging.meta.* and posts.facebook.* are configured separately
        // even though they're normally the same underlying Meta App - accept
        // whichever verify token Meta was actually configured with.
        $challenge = $this->messengerService->verifyWebhook($request)
            ?? $this->postService->verifyWebhook($request);

        return $challenge !== null
            ? response($challenge, 200)
            : response('Forbidden', 403);
    }

    /**
     * Every subsequent event delivery. Must ack fast (Meta retries
     * aggressively on slow/failed responses and can eventually disable the
     * subscription) - the actual DB write + broadcast happens in a queued
     * job dispatched from inside the service, so this just verifies
     * authenticity and hands the payload off.
     */
    public function receive(Request $request)
    {
        // Crash-proof delivery proof: check storage/logs/laravel.log for
        // this line after a real event to confirm Meta is actually
        // calling this endpoint, without the foreign-key risk a hardcoded
        // DB insert here carries (see git history on this file for why).
        Log::info('Facebook webhook payload received', ['payload' => $request->all()]);

        if (!$this->messengerService->verifySignature($request)) {
            Log::warning('Facebook Messenger webhook signature mismatch', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $payload = $request->all();

        $this->messengerService->handleWebhook($payload);
        $this->postService->handleCommentWebhook($payload, 'facebook');

        return response('EVENT_RECEIVED', 200);
    }
}
